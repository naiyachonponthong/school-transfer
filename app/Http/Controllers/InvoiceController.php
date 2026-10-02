<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'open');
        $invoices = Invoice::with('student.classroom')
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['unpaid', 'partial']))
            ->when(! in_array($status, ['open', 'all'], true), fn ($q) => $q->where('status', $status))
            ->when($request->query('classroom'), fn ($q, $id) => $q->whereHas('student', fn ($s) => $s->where('classroom_id', $id)))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('invoice_no', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")
                ->orWhereHas('student', fn ($s) => $s->search($term))))
            ->latest()->paginate(40)->withQueryString();

        $summary = [
            'outstanding' => (float) Invoice::whereIn('status', ['unpaid', 'partial'])->sum(DB::raw('total - discount - paid')),
            'collected_today' => (float) Payment::whereDate('paid_at', today())->sum('amount'),
            'collected_month' => (float) Payment::where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
            'overdue' => Invoice::whereIn('status', ['unpaid', 'partial'])->whereDate('due_date', '<', today())->count(),
        ];

        return view('invoices.index', [
            'invoices' => $invoices,
            'status' => $status,
            'summary' => $summary,
            'classrooms' => Classroom::currentYear()->ordered()->get(),
        ]);
    }

    public function create()
    {
        return view('invoices.create', [
            'classrooms' => Classroom::currentYear()->ordered()->withCount('students')->get(),
            'terms' => Term::orderByDesc('year')->orderByDesc('term')->get(),
            'current' => Term::current(),
        ]);
    }

    /** ออกใบแจ้งหนี้ครั้งเดียวทั้งห้อง/ทั้งชั้น หรือรายคน */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'term_id' => ['nullable', 'exists:terms,id'],
            'due_date' => ['nullable', 'date'],
            'classroom_ids' => ['array'],
            'classroom_ids.*' => ['exists:classrooms,id'],
            'student_code' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $items = collect($data['items'])->filter(fn ($i) => filled($i['description'] ?? null) && ($i['amount'] ?? 0) > 0)->values();
        if ($items->isEmpty()) {
            return back()->withInput()->withErrors(['items' => 'กรุณาใส่รายการอย่างน้อย 1 รายการ']);
        }

        $students = collect();
        if (! empty($data['classroom_ids'])) {
            $students = Student::active()->whereIn('classroom_id', $data['classroom_ids'])->get();
        }
        if (filled($data['student_code'] ?? null)) {
            $codes = preg_split('/[\s,]+/', $data['student_code'], -1, PREG_SPLIT_NO_EMPTY);
            $students = $students->merge(Student::whereIn('student_code', $codes)->get())->unique('id');
        }
        if ($students->isEmpty()) {
            return back()->withInput()->withErrors(['classroom_ids' => 'กรุณาเลือกห้องหรือระบุรหัสนักเรียน']);
        }

        DB::transaction(function () use ($students, $data, $items, $request) {
            foreach ($students as $student) {
                $invoice = Invoice::create([
                    'invoice_no' => Invoice::nextNumber(),
                    'student_id' => $student->id,
                    'term_id' => $data['term_id'] ?? null,
                    'title' => $data['title'],
                    'due_date' => $data['due_date'] ?? null,
                    'total' => $items->sum('amount'),
                    'created_by' => $request->user()->id,
                ]);
                $invoice->items()->createMany($items->map(fn ($i) => ['description' => $i['description'], 'amount' => $i['amount']])->all());
                \App\Services\Notifier::parents($student, "🧾 ใบแจ้งหนี้ใหม่: {$data['title']} ยอด ".baht($items->sum('amount')).' บาท'
                    .(! empty($data['due_date']) ? ' กำหนดชำระ '.thai_date($data['due_date']) : '').' ชำระผ่าน QR พร้อมเพย์ได้ในระบบ', route('invoices.show', $invoice));
            }
        });

        return redirect()->route('invoices.index')->with('success', "ออกใบแจ้งหนี้ \"{$data['title']}\" ให้นักเรียน {$students->count()} คนแล้ว");
    }

    public function show(Request $request, Invoice $invoice)
    {
        $user = $request->user();
        abort_unless($user->isStaff() || $invoice->student->isGuardedBy($user), 403);

        return view('invoices.show', ['invoice' => $invoice->load('student.classroom', 'items', 'payments.receiver', 'term')]);
    }

    public function pay(Request $request, Invoice $invoice)
    {
        abort_if($invoice->status === 'void', 422, 'ใบแจ้งหนี้ถูกยกเลิกแล้ว');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$invoice->balance()],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'paid_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['amount.max' => 'ยอดชำระเกินยอดค้าง ('.number_format($invoice->balance(), 2).' บาท)']);

        $payment = DB::transaction(function () use ($invoice, $data, $request) {
            $p = $invoice->payments()->create([
                'receipt_no' => Payment::nextNumber(),
                'amount' => $data['amount'],
                'method' => $data['method'],
                'paid_at' => $data['paid_at'] ?? now(),
                'note' => $data['note'] ?? null,
                'received_by' => $request->user()->id,
            ]);
            $invoice->refreshTotals();

            return $p;
        });

        return redirect()->route('invoices.show', $invoice)
            ->with('success', "รับชำระ {$payment->receipt_no} แล้ว")
            ->with('receipt', $payment->id);
    }

    public function void(Invoice $invoice)
    {
        abort_if($invoice->paid > 0, 422, 'ใบแจ้งหนี้ที่มีการชำระแล้วยกเลิกไม่ได้');
        $invoice->update(['status' => 'void']);

        return back()->with('success', 'ยกเลิกใบแจ้งหนี้แล้ว');
    }

    public function receipt(Request $request, Payment $payment)
    {
        $payment->load('invoice.student.classroom', 'invoice.items', 'receiver');
        $user = $request->user();
        abort_unless($user->isStaff() || $payment->invoice->student->isGuardedBy($user), 403);

        return view('invoices.receipt', compact('payment'));
    }
}
