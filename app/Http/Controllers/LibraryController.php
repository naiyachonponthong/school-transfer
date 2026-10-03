<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookLoan;
use App\Models\Student;
use App\Services\Notifier;
use App\Support\AccessionNumber;
use App\Support\Audit;
use App\Support\BookBarcode;
use App\Support\Dewey;
use App\Support\Settings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * ห้องสมุดตามหลักสากล: ระเบียนบรรณานุกรม (ISBN · เลขหมู่ DDC · เลขผู้แต่ง · พิมพลักษณ์ · หัวเรื่อง)
 * + ตัวเล่ม (บาร์โค้ด · เลขทะเบียน · ฉบับที่ · สภาพ) + ยืม-คืนด้วยบาร์โค้ดตัวเล่ม
 */
class LibraryController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        // นวนิยาย/เรื่องสั้นใช้สัญลักษณ์แทนเลขหมู่ ไม่นับในหมวด DDC · ย/อ/บร มีเลขหมู่ นับตามเลขหมู่
        $symbolOnly = collect(Dewey::COLLECTIONS)->filter(fn ($c) => $c[2] === 'class')->keys()->all();
        $books = Book::withCount(['activeLoans', 'items as circulating_count' => fn ($c) => $c->whereNotIn('condition', BookCopy::OUT_OF_SERVICE)])
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('title', 'like', "%{$q}%")->orWhere('author', 'like', "%{$q}%")
                ->orWhere('code', $q)->orWhere('isbn', preg_replace('/[^0-9Xx]/', '', $q) ?: $q)->orWhere('class_number', 'like', "{$q}%")
                ->orWhere('subjects', 'like', "%{$q}%")->orWhere('series', 'like', "%{$q}%")
                ->orWhereHas('items', fn ($i) => $i->where('barcode', $q)->orWhere('accession_no', $q))))
            ->when($request->query('class'), fn ($query, $c) => $query->whereNotIn('collection', $symbolOnly)->where('class_number', 'like', substr($c, 0, 1).'%'))
            ->when($request->query('collection'), fn ($query, $c) => $query->where('collection', $c))
            ->orderBy($request->query('sort') === 'call' ? 'class_number' : 'title')->orderBy('title')
            ->paginate(40)->withQueryString();

        return view('library.index', [
            'books' => $books,
            'stats' => [
                'titles' => Book::count(),
                'copies' => BookCopy::where('condition', '!=', 'withdrawn')->count(),
                'out' => BookLoan::whereNull('returned_on')->count(),
                'overdue' => BookLoan::whereNull('returned_on')->where('due_on', '<', today()->toDateString())->count(),
                'labels' => BookCopy::where('condition', '!=', 'withdrawn')->whereNull('label_printed_at')->count(),
                'noAccession' => BookCopy::whereNull('accession_no')->count(),
            ],
            'byClass' => Book::whereNotIn('collection', $symbolOnly)->whereNotNull('class_number')->get(['class_number'])
                ->countBy(fn ($b) => Dewey::mainClass($b->class_number)),
            'byCollection' => Book::where('collection', '!=', 'general')->selectRaw('collection, count(*) c')->groupBy('collection')->pluck('c', 'collection'),
        ]);
    }

    public function create()
    {
        return view('library.form', [
            'book' => new Book(['collection' => 'general', 'language' => 'ไทย']),
            'locations' => self::locations(),
            'nextBarcode' => BookBarcode::next()[0], 'nextAccession' => AccessionNumber::next()[0],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $items = $request->validate([
            'copies_count' => ['required', 'integer', 'min:1', 'max:200'],
            'barcode' => ['nullable', 'string', 'max:40', 'regex:/^[\x21-\x7E]+$/', Rule::unique('book_copies', 'barcode')],
            'location' => ['nullable', 'string', 'max:60'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'acquired_on' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:60'],
        ], ['barcode.regex' => 'บาร์โค้ดใช้ได้เฉพาะตัวอักษรอังกฤษ ตัวเลข และสัญลักษณ์ (ไม่มีช่องว่าง/ภาษาไทย)'], ['copies_count' => 'จำนวนเล่ม', 'barcode' => 'บาร์โค้ด']);
        if (filled($items['barcode'] ?? null) && $items['copies_count'] > 1) {
            throw ValidationException::withMessages(['barcode' => 'เพิ่มหลายเล่มพร้อมกันให้เว้นบาร์โค้ดว่าง ระบบจะออกเรียงกันให้']);
        }
        $data['cover'] = $request->hasFile('cover') ? $request->file('cover')->store('books', 'public') : null;

        $book = DB::transaction(function () use ($data, $items) {
            $book = Book::create($data + ['copies' => 0, 'location' => $items['location'] ?? null]);
            $this->addItems($book, (int) $items['copies_count'], $items);

            return $book;
        });
        Audit::log('library.create', $book, "ลงทะเบียนหนังสือ {$book->title} {$items['copies_count']} เล่ม");

        return redirect()->route($request->boolean('another') ? 'library.create' : 'library.show', $request->boolean('another') ? [] : $book)
            ->with('success', "ลงทะเบียน \"{$book->title}\" {$items['copies_count']} เล่มแล้ว — พิมพ์ป้ายสัน/บาร์โค้ดได้ที่ \"พิมพ์ป้าย\"");
    }

    /**
     * เพิ่มตัวเล่ม: ออกบาร์โค้ด + เลขทะเบียนเรียงกัน (สองคนบันทึกพร้อมกันไม่ได้เลขซ้ำ)
     *
     * @return list<BookCopy>
     */
    private function addItems(Book $book, int $count, array $attrs): array
    {
        $date = isset($attrs['acquired_on']) ? Carbon::parse($attrs['acquired_on']) : today();
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($book, $count, $attrs, $date) {
                    $barcodes = filled($attrs['barcode'] ?? null) ? [$attrs['barcode']] : BookBarcode::next(null, $date, $count);
                    $accessions = AccessionNumber::next(null, $date, $count);
                    $copyNo = (int) $book->items()->max('copy_no');
                    $out = [];
                    foreach (range(0, $count - 1) as $i) {
                        $out[] = $book->items()->create([
                            'barcode' => $barcodes[$i], 'accession_no' => $accessions[$i], 'copy_no' => $copyNo + $i + 1,
                            'location' => $attrs['location'] ?? $book->location, 'price' => $attrs['price'] ?? null,
                            'acquired_on' => $date, 'source' => $attrs['source'] ?? null, 'condition' => 'good',
                        ]);
                    }

                    return $out;
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 3) {
                    throw $e;
                }
            }
        }
    }

    public function show(Book $book)
    {
        $book->load(['items.activeLoan.student.classroom']);

        return view('library.show', [
            'book' => $book,
            'history' => $book->loans()->with(['student.classroom', 'copy'])->latest('borrowed_on')->limit(15)->get(),
            'locations' => self::locations(),
        ]);
    }

    public function edit(Book $book)
    {
        return view('library.form', ['book' => $book, 'locations' => self::locations()]);
    }

    public function update(Request $request, Book $book)
    {
        $data = $this->validated($request, $book);
        unset($data['cover']);
        if ($request->hasFile('cover')) {
            $book->cover && Storage::disk('public')->delete($book->cover);
            $data['cover'] = $request->file('cover')->store('books', 'public');
        }
        $book->fill($data);
        if ($diff = Audit::diff($book)) {
            Audit::log('library.update', $book, "แก้ระเบียนหนังสือ {$book->title}", $diff);
        }
        $book->save();

        return redirect()->route('library.show', $book)->with('success', 'บันทึกแล้ว — ถ้าเลขเรียกเปลี่ยน ให้พิมพ์ป้ายสันใหม่');
    }

    public function destroy(Book $book)
    {
        abort_if($book->loans()->exists(), 422, 'หนังสือนี้มีประวัติการยืมแล้ว ลบไม่ได้ — เปลี่ยนสภาพตัวเล่มเป็น "จำหน่ายออก" แทน');
        Audit::log('library.delete', $book, "ลบระเบียนหนังสือ {$book->title} (บันทึกผิด)");
        $book->cover && Storage::disk('public')->delete($book->cover);
        $book->delete();

        return redirect()->route('library.index')->with('success', 'ลบหนังสือแล้ว');
    }

    public function storeCopies(Request $request, Book $book)
    {
        $data = $request->validate([
            'copies_count' => ['required', 'integer', 'min:1', 'max:200'],
            'location' => ['nullable', 'string', 'max:60'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'acquired_on' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:60'],
        ], [], ['copies_count' => 'จำนวนเล่ม']);
        $items = $this->addItems($book, (int) $data['copies_count'], $data);
        Audit::log('library.copies', $book, "เพิ่มตัวเล่ม {$book->title} {$data['copies_count']} เล่ม");

        return back()->with('success', 'เพิ่ม '.count($items).' เล่มแล้ว ('.collect($items)->pluck('barcode')->implode(', ').')');
    }

    public function updateCopy(Request $request, BookCopy $copy)
    {
        $data = $request->validate([
            'barcode' => ['required', 'string', 'max:40', 'regex:/^[\x21-\x7E]+$/', Rule::unique('book_copies', 'barcode')->ignore($copy->id)],
            'accession_no' => ['nullable', 'string', 'max:40', Rule::unique('book_copies', 'accession_no')->ignore($copy->id)],
            'copy_no' => ['required', 'integer', 'min:1', 'max:999'],
            'location' => ['nullable', 'string', 'max:60'],
            'condition' => ['required', Rule::in(array_keys(BookCopy::CONDITIONS))],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'acquired_on' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['barcode.regex' => 'บาร์โค้ดใช้ได้เฉพาะตัวอักษรอังกฤษ ตัวเลข และสัญลักษณ์'], ['barcode' => 'บาร์โค้ด', 'accession_no' => 'เลขทะเบียน']);
        if (in_array($data['condition'], BookCopy::OUT_OF_SERVICE, true) && $copy->activeLoan()->exists() && $data['condition'] !== 'lost') {
            throw ValidationException::withMessages(['condition' => 'เล่มนี้ถูกยืมอยู่ — รับคืนก่อน (หรือเลือก "สูญหาย")']);
        }
        $copy->fill($data);
        // เปลี่ยนบาร์โค้ด/ฉบับที่ → ต้องพิมพ์ป้ายใหม่
        if ($copy->isDirty(['barcode', 'accession_no', 'copy_no'])) {
            $copy->label_printed_at = null;
        }
        if ($diff = Audit::diff($copy)) {
            Audit::log('library.copy', $copy, "แก้ตัวเล่ม {$copy->barcode} {$copy->book->title}", $diff);
        }
        $copy->save();

        return back()->with('success', "บันทึกเล่ม {$copy->barcode} แล้ว");
    }

    public function destroyCopy(BookCopy $copy)
    {
        if ($copy->loans()->exists()) {
            return back()->withErrors(['copy' => "เล่ม {$copy->barcode} มีประวัติการยืมแล้ว ลบไม่ได้ — เปลี่ยนสภาพเป็น \"จำหน่ายออก\" แทน"]);
        }
        Audit::log('library.copy', $copy, "ลบตัวเล่ม {$copy->barcode} {$copy->book->title} (บันทึกผิด)");
        $copy->delete();

        return back()->with('success', 'ลบตัวเล่มแล้ว');
    }

    /** ออกเลขทะเบียนให้ตัวเล่มที่ยังไม่มี (ข้อมูลเดิมก่อนปรับระบบ) เรียงตามวันที่ลงระบบ */
    public function assignAccession()
    {
        $copies = BookCopy::whereNull('accession_no')->orderBy('id')->get();
        if ($copies->isNotEmpty()) {
            $numbers = AccessionNumber::next(null, today(), $copies->count());
            DB::transaction(fn () => $copies->each(fn ($c, $i) => $c->update(['accession_no' => $numbers[$i], 'label_printed_at' => null])));
            Audit::log('library.accession', null, 'ออกเลขทะเบียน '.$copies->count().' เล่ม ('.$numbers[0].'–'.end($numbers).')');
        }

        return back()->with('success', $copies->isEmpty() ? 'ทุกเล่มมีเลขทะเบียนแล้ว' : 'ออกเลขทะเบียน '.$copies->count().' เล่มแล้ว — พิมพ์ป้ายบาร์โค้ดปกในใหม่');
    }

    private function validated(Request $request, ?Book $book = null): array
    {
        $request->merge([
            'isbn' => preg_replace('/[^0-9Xx]/', '', (string) $request->input('isbn')) ?: null,
            'class_number' => trim((string) $request->input('class_number')) ?: null,
        ]);
        $collections = array_keys(Dewey::COLLECTIONS);
        $replacesClass = collect(Dewey::COLLECTIONS)->filter(fn ($c) => $c[2] === 'class')->keys()->all();
        $data = $request->validate([
            'isbn' => ['nullable', 'string', function ($attr, $value, $fail) {
                self::isbnValid($value) || $fail('ISBN ไม่ถูกต้อง (ตรวจเลขหลักสุดท้ายไม่ผ่าน) — ตรวจกับหลังปก/หน้าลิขสิทธิ์');
            }],
            'collection' => ['required', Rule::in($collections)],
            'class_number' => [Rule::requiredIf(! in_array($request->input('collection'), $replacesClass, true)), 'nullable', 'regex:/^\d{3}(\.\d+)?$/'],
            'author_mark' => ['nullable', 'string', 'max:20'],
            'volume' => ['nullable', 'string', 'max:20'],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'contributors' => ['nullable', 'string', 'max:255'],
            'edition' => ['nullable', 'string', 'max:40'],
            'pub_place' => ['nullable', 'string', 'max:100'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'pub_year' => ['nullable', 'regex:/^\d{4}$/'],
            'pages' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'size_cm' => ['nullable', 'integer', 'min:5', 'max:80'],
            'series' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:30'],
            'subjects' => ['nullable', 'string', 'max:500'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'note' => ['nullable', 'string', 'max:500'],
            'cover' => ['nullable', 'image', 'max:8192'],
        ], [
            'class_number.required' => 'ใส่เลขหมู่ DDC (หรือเลือกประเภทนวนิยาย/เรื่องสั้นที่ใช้สัญลักษณ์แทนเลขหมู่)',
            'class_number.regex' => 'เลขหมู่ต้องเป็นตัวเลข 3 หลัก มีทศนิยมได้ เช่น 510 · 895.913 · 959.3',
            'pub_year.regex' => 'ปีพิมพ์เป็นตัวเลข 4 หลัก เช่น 2567',
        ], ['title' => 'ชื่อเรื่อง', 'class_number' => 'เลขหมู่']);
        $data['illustrated'] = $request->boolean('illustrated');
        $data['language'] = $data['language'] ?? 'ไทย';
        // หัวเรื่อง: รวมช่องว่าง/ขึ้นบรรทัดเป็น ;
        $data['subjects'] = collect(preg_split('/[;\n]/u', (string) ($data['subjects'] ?? '')))->map(fn ($s) => trim($s))->filter()->unique()->implode('; ') ?: null;

        return $data;
    }

    /** ISBN-10 / ISBN-13 (ตรวจเลขหลักสุดท้าย) */
    public static function isbnValid(string $isbn): bool
    {
        $isbn = strtoupper($isbn);
        if (preg_match('/^\d{13}$/', $isbn)) {
            $sum = 0;
            foreach (str_split(substr($isbn, 0, 12)) as $i => $d) {
                $sum += (int) $d * ($i % 2 ? 3 : 1);
            }

            return (10 - $sum % 10) % 10 === (int) $isbn[12];
        }
        if (preg_match('/^\d{9}[\dX]$/', $isbn)) {
            $sum = 0;
            foreach (str_split($isbn) as $i => $d) {
                $sum += ($d === 'X' ? 10 : (int) $d) * (10 - $i);
            }

            return $sum % 11 === 0;
        }

        return false;
    }

    public static function locations()
    {
        return BookCopy::whereNotNull('location')->distinct()->orderBy('location')->pluck('location');
    }

    /* ================================================================ ยืม-คืน */

    public function circulation(Request $request)
    {
        $loans = BookLoan::with(['book', 'copy', 'student.classroom'])->whereNull('returned_on')
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->whereHas('student', fn ($s) => $s->search($t))
                ->orWhereHas('book', fn ($b) => $b->where('title', 'like', "%{$t}%"))->orWhereHas('copy', fn ($c) => $c->where('barcode', $t))))
            ->orderBy('due_on')->paginate(40)->withQueryString();

        return view('library.circulation', [
            'loans' => $loans,
            'recentReturns' => BookLoan::with(['book', 'student'])->whereNotNull('returned_on')->latest('updated_at')->limit(8)->get(),
            'loanDays' => (int) Settings::get('library_loan_days', 7),
        ]);
    }

    /**
     * หาตัวเล่มจากที่สแกน/พิมพ์: บาร์โค้ด → เลขทะเบียน → รหัสระเบียน/ISBN (เลือกเล่มที่ว่างให้)
     */
    private static function findCopy(string $code, bool $forBorrow): ?BookCopy
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        $copy = BookCopy::with('book')->where('barcode', $code)->first() ?? BookCopy::with('book')->where('accession_no', $code)->first();
        if ($copy) {
            return $copy;
        }
        $book = Book::where('code', $code)->orWhere('isbn', preg_replace('/[^0-9Xx]/', '', $code) ?: '-')->first();
        if (! $book) {
            return null;
        }
        $items = $book->items()->with('book')->get();

        return $forBorrow
            ? $items->first(fn ($c) => $c->isCirculating() && ! $c->activeLoan()->exists())
            : $items->first(fn ($c) => $c->activeLoan()->exists());
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
        if (! $student) {
            return back()->withInput()->withErrors(['student' => "ไม่พบนักเรียน {$sCode}"]);
        }
        $copy = self::findCopy($data['book'], true);
        if (! $copy) {
            return back()->withInput()->withErrors(['book' => 'ไม่พบหนังสือ/เล่มที่ว่าง สำหรับรหัส '.$data['book']]);
        }
        $book = $copy->book;
        if (! $book->loanable()) {
            return back()->withInput()->withErrors(['book' => "\"{$book->title}\" เป็น{$book->collectionInfo()[0]} — ใช้ในห้องสมุดเท่านั้น"]);
        }
        if (! $copy->isCirculating()) {
            return back()->withInput()->withErrors(['book' => "เล่ม {$copy->barcode} สภาพ \"{$copy->conditionLabel()}\" ให้ยืมไม่ได้"]);
        }
        if ($active = $copy->activeLoan()->with('student')->first()) {
            return back()->withInput()->withErrors(['book' => "เล่ม {$copy->barcode} ถูกยืมอยู่โดย {$active->student?->fullName()} — รับคืนก่อน"]);
        }
        if ($student->bookLoans()->whereNull('returned_on')->where('due_on', '<', today()->toDateString())->exists()) {
            return back()->withInput()->withErrors(['student' => "{$student->fullName()} มีหนังสือเกินกำหนดคืน ต้องคืนก่อนยืมใหม่"]);
        }

        $days = (int) ($data['days'] ?? Settings::get('library_loan_days', 7));
        $loan = BookLoan::create([
            'book_id' => $book->id, 'book_copy_id' => $copy->id, 'student_id' => $student->id,
            'borrowed_on' => today(), 'due_on' => today()->addWeekdays($days), 'recorded_by' => $request->user()->id,
        ]);

        return back()->with('success', "{$student->fullName()} ยืม \"{$book->title}\" ({$copy->barcode}) กำหนดคืน ".thai_date($loan->due_on))->withInput(['student' => $data['student']]);
    }

    public function return(BookLoan $loan)
    {
        $loan->update(['returned_on' => today()]);

        return back()->with('success', "รับคืน \"{$loan->book->title}\"".($loan->copy ? " ({$loan->copy->barcode})" : '').' แล้ว'
            .($loan->due_on->lt(today()) ? ' (เกินกำหนด '.$loan->due_on->diffInDays(today()).' วัน)' : ''));
    }

    /** คืนด้วยการสแกนบาร์โค้ดตัวเล่ม */
    public function returnByCode(Request $request)
    {
        $code = trim((string) $request->input('book'));
        $copy = self::findCopy($code, false);
        $loan = $copy?->activeLoan()->first()
            ?? BookLoan::whereNull('returned_on')->whereNull('book_copy_id')->whereHas('book', fn ($q) => $q->where('code', $code))->oldest('borrowed_on')->first();
        if (! $loan) {
            return back()->withErrors(['return' => "ไม่พบรายการยืมของหนังสือรหัส {$code}"]);
        }

        return $this->return($loan);
    }

    /** แจ้งเตือนผู้ปกครองของคนที่เกินกำหนดคืน */
    public function remindOverdue()
    {
        return back()->with('success', 'ส่งแจ้งเตือนผู้ปกครอง '.BookLoan::notifyOverdue().' คนแล้ว (เฉพาะคนที่เชื่อม LINE)');
    }
}
