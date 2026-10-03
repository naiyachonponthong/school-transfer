<?php

namespace App\Http\Controllers;

use App\Models\CashClosing;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\Payment;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** งานการเงินประจำ: แบ่งงวดชำระ ปิดยอดรายวัน รายงาน */
class FinanceController extends Controller
{
    /* ---------------- ผ่อนชำระ ---------------- */

    /** แบ่งยอดค้างเป็นงวดเท่า ๆ กัน (เศษสตางค์ลงงวดสุดท้าย) ทุก 1 เดือนนับจากงวดแรก */
    public function installments(Request $request, Invoice $invoice)
    {
        abort_if(in_array($invoice->status, ['paid', 'void'], true), 422, 'ใบแจ้งหนี้นี้ชำระครบหรือถูกยกเลิกแล้ว');
        $data = $request->validate([
            'count' => ['required', 'integer', 'between:1,12'],
            'first_due' => ['required_unless:count,1', 'nullable', 'date'],
        ], [], ['count' => 'จำนวนงวด', 'first_due' => 'วันครบกำหนดงวดแรก']);

        DB::transaction(function () use ($invoice, $data) {
            $invoice->installments()->delete();
            if ((int) $data['count'] > 1) {
                $net = $invoice->netTotal();
                $each = floor($net / $data['count'] * 100) / 100;
                $first = Carbon::parse($data['first_due']);
                foreach (range(1, $data['count']) as $seq) {
                    $invoice->installments()->create([
                        'seq' => $seq,
                        'due_date' => $first->copy()->addMonthsNoOverflow($seq - 1)->toDateString(),
                        'amount' => $seq === (int) $data['count'] ? round($net - $each * ($data['count'] - 1), 2) : $each,
                    ]);
                }
            }
            $invoice->unsetRelation('installments');
            $invoice->refreshTotals(); // กำหนดชำระของใบแจ้งหนี้ = งวดถัดไปที่ยังไม่ครบ
        });

        return back()->with('success', (int) $data['count'] > 1 ? "แบ่งชำระ {$data['count']} งวดแล้ว" : 'ยกเลิกการแบ่งงวดแล้ว');
    }

    /* ---------------- ปิดยอดรายวัน ---------------- */

    private function dayPayments(Carbon $date)
    {
        return Payment::valid()->with(['invoice.student.classroom', 'receiver'])->whereDate('paid_at', $date->toDateString())->orderBy('receipt_no')->get();
    }

    public function closing(Request $request)
    {
        $date = Carbon::parse($request->query('date', today()->toDateString()));
        $payments = $this->dayPayments($date);

        return view('finance.closing', [
            'date' => $date,
            'payments' => $payments,
            'byMethod' => collect(Payment::METHODS)->map(fn ($label, $k) => (float) $payments->where('method', $k)->sum('amount')),
            'byReceiver' => $payments->groupBy('received_by')->map(fn ($rows) => ['name' => $rows->first()->receiver?->name ?? '-', 'count' => $rows->count(), 'amount' => (float) $rows->sum('amount')])->values(),
            'closing' => CashClosing::with('closer')->whereDate('date', $date->toDateString())->first(),
            'recent' => CashClosing::with('closer')->orderByDesc('date')->limit(14)->get(),
        ]);
    }

    public function close(Request $request)
    {
        $data = $request->validate(['date' => ['required', 'date', 'before_or_equal:today'], 'note' => ['nullable', 'string', 'max:255']]);
        $date = Carbon::parse($data['date']);
        abort_if(CashClosing::isClosed($date->toDateString()), 422, 'วันนี้ปิดยอดไปแล้ว');
        $payments = $this->dayPayments($date);

        CashClosing::create([
            'date' => $date->toDateString(),
            'cash' => $payments->where('method', 'cash')->sum('amount'),
            'transfer' => $payments->where('method', 'transfer')->sum('amount'),
            'promptpay' => $payments->where('method', 'promptpay')->sum('amount'),
            'receipts' => $payments->count(),
            'note' => $data['note'] ?? null,
            'closed_by' => $request->user()->id,
        ]);

        Audit::log('finance.closing', null, 'ปิดยอดรับเงินวันที่ '.thai_date($date).' '.number_format((float) $payments->sum('amount'), 2).' บาท');

        return back()->with('success', 'ปิดยอดวันที่ '.thai_date($date).' แล้ว');
    }

    /** เปิดยอดใหม่ (เฉพาะผู้ดูแลระบบ) เพื่อแก้ไขใบเสร็จของวันนั้น */
    public function reopen(Request $request, CashClosing $closing)
    {
        abort_unless($request->user()->isAdmin(), 403, 'เฉพาะผู้ดูแลระบบที่ยกเลิกการปิดยอดได้');
        Audit::log('finance.closing', null, 'ยกเลิกการปิดยอดวันที่ '.thai_date($closing->date));
        $closing->delete();

        return back()->with('success', 'ยกเลิกการปิดยอดแล้ว');
    }

    /* ---------------- รายงาน ---------------- */

    public function reports(Request $request)
    {
        $tab = in_array($request->query('tab'), ['receipts', 'category', 'outstanding'], true) ? $request->query('tab') : 'receipts';
        $from = Carbon::parse($request->query('from', today()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', today()->toDateString()))->endOfDay();

        [$head, $rows, $summary] = match ($tab) {
            'receipts' => $this->receiptRegister($from, $to),
            'category' => $this->revenueByCategory($from, $to),
            'outstanding' => $this->outstandingByClassroom(),
        };

        if ($request->boolean('export')) {
            return response()->streamDownload(function () use ($head, $rows) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, $head);
                foreach ($rows as $row) {
                    fputcsv($out, $row);
                }
                fclose($out);
            }, "รายงานการเงิน-{$tab}-".now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('finance.reports', compact('tab', 'from', 'to', 'head', 'rows', 'summary'));
    }

    /** ทะเบียนใบเสร็จ: ทุกใบตามลำดับเลขที่ รวมใบที่ยกเลิก (ไม่นับยอด) */
    private function receiptRegister(Carbon $from, Carbon $to): array
    {
        $payments = Payment::with(['invoice.student.classroom', 'receiver'])->whereBetween('paid_at', [$from, $to])->orderBy('receipt_no')->get();
        $rows = $payments->map(fn (Payment $p) => [
            $p->receipt_no, thai_datetime($p->paid_at), $p->invoice->student->student_code, $p->invoice->student->fullName(),
            $p->invoice->student->classroom?->name() ?? '-', $p->invoice->title, $p->methodLabel(), number_format($p->amount, 2, '.', ''),
            $p->isVoided() ? 'ยกเลิก: '.$p->void_reason : '', $p->receiver?->name ?? '-',
        ])->all();
        $valid = $payments->reject->isVoided();

        return [
            ['เลขที่ใบเสร็จ', 'วันที่', 'รหัส', 'ชื่อ-สกุล', 'ห้อง', 'รายการ', 'วิธีชำระ', 'จำนวนเงิน', 'สถานะ', 'ผู้รับเงิน'],
            $rows,
            ['ใบเสร็จ' => $valid->count().' ใบ', 'ยกเลิก' => ($payments->count() - $valid->count()).' ใบ', 'รวมรับ' => baht($valid->sum('amount')).' บาท'],
        ];
    }

    /**
     * รายรับตามหมวด: กระจายยอดรับของแต่ละใบเสร็จลงรายการของใบแจ้งหนี้ตามสัดส่วน
     * (ใบแจ้งหนี้ที่ออกเองไม่ได้ผูกรายการมาตรฐาน อยู่ในหมวด "ไม่ระบุหมวด")
     */
    private function revenueByCategory(Carbon $from, Carbon $to): array
    {
        $totals = [];
        foreach (Payment::valid()->with('invoice.items.feeItem')->whereBetween('paid_at', [$from, $to])->get() as $p) {
            $sum = (float) $p->invoice->items->sum('amount');
            foreach ($p->invoice->items as $item) {
                $key = $item->feeItem?->category ?? 'ไม่ระบุหมวด';
                $totals[$key] = ($totals[$key] ?? 0) + ($sum > 0 ? $p->amount * $item->amount / $sum : 0);
            }
        }
        arsort($totals);
        $grand = array_sum($totals);

        return [
            ['หมวด', 'รายรับ', 'สัดส่วน (%)'],
            collect($totals)->map(fn ($v, $k) => [$k, number_format($v, 2, '.', ''), $grand > 0 ? number_format($v / $grand * 100, 1) : '0'])->values()->all(),
            ['รวมรับ' => baht($grand).' บาท'],
        ];
    }

    /** ลูกหนี้ค้างชำระแยกห้อง พร้อมอายุหนี้นับจากวันครบกำหนด */
    private function outstandingByClassroom(): array
    {
        $today = today();
        $rooms = [];
        foreach (Invoice::with('student.classroom')->whereIn('status', ['unpaid', 'partial'])->get() as $inv) {
            $room = $inv->student->classroom;
            $key = $room ? sprintf('%03d-%03d', $room->level_order, $room->room) : 'zzz';
            $rooms[$key] ??= ['name' => $room?->name() ?? 'ไม่มีห้อง', 'students' => [], 'b0' => 0, 'b1' => 0, 'b2' => 0, 'b3' => 0];
            $days = $inv->due_date && $inv->due_date->lt($today) ? (int) $inv->due_date->diffInDays($today) : 0;
            $bucket = match (true) {
                $days === 0 => 'b0', $days <= 30 => 'b1', $days <= 60 => 'b2', default => 'b3'
            };
            $rooms[$key][$bucket] += $inv->balance();
            $rooms[$key]['students'][$inv->student_id] = true;
        }
        ksort($rooms);
        $rows = collect($rooms)->map(fn ($r) => [
            $r['name'], count($r['students']), number_format($r['b0'], 2, '.', ''), number_format($r['b1'], 2, '.', ''),
            number_format($r['b2'], 2, '.', ''), number_format($r['b3'], 2, '.', ''), number_format($r['b0'] + $r['b1'] + $r['b2'] + $r['b3'], 2, '.', ''),
        ])->values()->all();

        return [
            ['ห้อง', 'นักเรียนที่ค้าง', 'ยังไม่ครบกำหนด', 'เกิน 1–30 วัน', 'เกิน 31–60 วัน', 'เกิน 60 วัน', 'รวมค้าง'],
            $rows,
            ['ค้างชำระทั้งหมด' => baht(collect($rooms)->sum(fn ($r) => $r['b0'] + $r['b1'] + $r['b2'] + $r['b3'])).' บาท'],
        ];
    }
}
