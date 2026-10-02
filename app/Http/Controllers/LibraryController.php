<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookLoan;
use App\Models\Student;
use App\Services\Notifier;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** ห้องสมุด: ทะเบียนหนังสือ + ยืม-คืนด้วยการสแกน */
class LibraryController extends Controller
{
    public function index(Request $request)
    {
        $books = Book::withCount('activeLoans')
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('title', 'like', "%{$t}%")->orWhere('code', 'like', "%{$t}%")->orWhere('author', 'like', "%{$t}%")))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->orderBy('title')->paginate(40)->withQueryString();

        return view('library.index', [
            'books' => $books,
            'categories' => Book::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'stats' => [
                'titles' => Book::count(),
                'copies' => (int) Book::sum('copies'),
                'out' => BookLoan::whereNull('returned_on')->count(),
                'overdue' => BookLoan::whereNull('returned_on')->where('due_on', '<', today()->toDateString())->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        Book::create($this->validated($request));

        return back()->with('success', 'เพิ่มหนังสือแล้ว');
    }

    public function update(Request $request, Book $book)
    {
        $book->update($this->validated($request, $book));

        return back()->with('success', 'บันทึกแล้ว');
    }

    public function destroy(Book $book)
    {
        abort_if($book->activeLoans()->exists(), 422, 'หนังสือเล่มนี้ยังถูกยืมอยู่');
        $book->delete();

        return back()->with('success', 'ลบหนังสือแล้ว');
    }

    /** หน้ายืม-คืน */
    public function circulation(Request $request)
    {
        $loans = BookLoan::with(['book', 'student.classroom'])->whereNull('returned_on')
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->whereHas('student', fn ($s) => $s->search($t))->orWhereHas('book', fn ($b) => $b->where('title', 'like', "%{$t}%")->orWhere('code', $t))))
            ->orderBy('due_on')->paginate(40)->withQueryString();

        return view('library.circulation', [
            'loans' => $loans,
            'recentReturns' => BookLoan::with(['book', 'student'])->whereNotNull('returned_on')->latest('updated_at')->limit(8)->get(),
            'loanDays' => (int) Settings::get('library_loan_days', 7),
        ]);
    }

    public function borrow(Request $request)
    {
        $data = $request->validate([
            'student' => ['required', 'string'],
            'book' => ['required', 'string'],
            'days' => ['nullable', 'integer', 'min:1', 'max:60'],
        ], [], ['student' => 'นักเรียน', 'book' => 'หนังสือ']);

        $sCode = trim(explode(' ', trim($data['student']))[0]);
        $student = Student::active()->where(fn ($q) => $q->where('student_code', $sCode)->orWhere('qr_token', $sCode))->first();
        $book = Book::where('code', trim($data['book']))->first();

        if (! $student || ! $book) {
            return back()->withInput()->withErrors(['student' => ! $student ? "ไม่พบนักเรียน {$sCode}" : 'ไม่พบหนังสือรหัส '.$data['book']]);
        }
        if ($book->available() < 1) {
            return back()->withInput()->withErrors(['book' => "\"{$book->title}\" ถูกยืมหมดแล้ว"]);
        }
        if ($student->bookLoans()->whereNull('returned_on')->where('due_on', '<', today()->toDateString())->exists()) {
            return back()->withInput()->withErrors(['student' => "{$student->fullName()} มีหนังสือเกินกำหนดคืน ต้องคืนก่อนยืมใหม่"]);
        }

        $days = (int) ($data['days'] ?? Settings::get('library_loan_days', 7));
        $loan = BookLoan::create([
            'book_id' => $book->id, 'student_id' => $student->id,
            'borrowed_on' => today(), 'due_on' => today()->addWeekdays($days), 'recorded_by' => $request->user()->id,
        ]);

        return back()->with('success', "{$student->fullName()} ยืม \"{$book->title}\" กำหนดคืน ".thai_date($loan->due_on))->withInput(['student' => $data['student']]);
    }

    public function return(BookLoan $loan)
    {
        $loan->update(['returned_on' => today()]);

        return back()->with('success', "รับคืน \"{$loan->book->title}\" แล้ว".($loan->due_on->lt(today()) ? ' (เกินกำหนด '.$loan->due_on->diffInDays(today()).' วัน)' : ''));
    }

    /** คืนด้วยการสแกนรหัสหนังสือ */
    public function returnByCode(Request $request)
    {
        $code = trim((string) $request->input('book'));
        $loan = BookLoan::whereNull('returned_on')->whereHas('book', fn ($q) => $q->where('code', $code))->oldest('borrowed_on')->first();
        if (! $loan) {
            return back()->withErrors(['return' => "ไม่พบรายการยืมของหนังสือรหัส {$code}"]);
        }

        return $this->return($loan);
    }

    /** แจ้งเตือนผู้ปกครองของคนที่เกินกำหนดคืน */
    public function remindOverdue()
    {
        $loans = BookLoan::with(['book', 'student'])->whereNull('returned_on')->where('due_on', '<', today()->toDateString())->get();
        foreach ($loans->groupBy('student_id') as $group) {
            $s = $group->first()->student;
            Notifier::parents($s, '📚 น้อง'.($s->nickname ?: $s->first_name).' มีหนังสือห้องสมุดเกินกำหนดคืน: '.$group->pluck('book.title')->implode(', '));
        }

        return back()->with('success', 'ส่งแจ้งเตือนผู้ปกครอง '.$loans->groupBy('student_id')->count().' คนแล้ว (เฉพาะคนที่เชื่อม LINE)');
    }

    private function validated(Request $request, ?Book $book = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('books')->ignore($book?->id)],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:60'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'copies' => ['required', 'integer', 'min:1', 'max:999'],
            'location' => ['nullable', 'string', 'max:60'],
        ], [], ['code' => 'รหัส/บาร์โค้ด', 'title' => 'ชื่อหนังสือ']);
    }
}
