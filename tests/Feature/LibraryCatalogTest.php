<?php

namespace Tests\Feature;

use App\Http\Controllers\LibraryController;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookLoan;
use App\Models\Student;
use App\Models\User;
use App\Support\Barcode;
use App\Support\Dewey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** ห้องสมุดตามหลักสากล: ระเบียน DDC · ตัวเล่ม/บาร์โค้ด/เลขทะเบียน · ป้ายสัน/บาร์โค้ดปกนอก-ปกใน · ยืม-คืนด้วยบาร์โค้ดตัวเล่ม */
class LibraryCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function teacher(): User
    {
        return User::where('username', 'teacher')->first();
    }

    private function form(array $over = []): array
    {
        return array_merge([
            'title' => 'ไดโนเสาร์ครองโลก', 'author' => 'นายศรีสุข รักเรียน', 'isbn' => '978-616-08-2154-9', 'collection' => 'general', 'class_number' => '567.9',
            'author_mark' => 'ศ532ด', 'volume' => '1', 'publisher' => 'นานมีบุ๊คส์', 'pub_place' => 'กรุงเทพฯ', 'pub_year' => '2566', 'pages' => 120, 'size_cm' => 24,
            'illustrated' => 1, 'subjects' => "ไดโนเสาร์ ;สัตว์ดึกดำบรรพ์\nไดโนเสาร์", 'copies_count' => 3, 'price' => 250, 'location' => 'ชั้น 500', 'source' => 'จัดซื้อ',
        ], $over);
    }

    /** ถอดรหัสแท่งกลับเป็นข้อความ (ตรวจว่าบาร์โค้ดที่วาดถูกต้องตามมาตรฐาน Code 128 และ checksum ผ่าน) */
    private function decode(string $bits): string
    {
        $patterns = (new \ReflectionClassConstant(Barcode::class, 'PATTERNS'))->getValue();
        $widths = array_map('strlen', preg_split('/(?<=1)(?=0)|(?<=0)(?=1)/', $bits));
        $values = [];
        for ($i = 0; $i + 6 <= count($widths); $i += 6) {
            $chunk = implode('', array_slice($widths, $i, 6));
            if ($chunk === '233111') {
                break; // stop (+ แท่งปิดท้าย)
            }
            $values[] = array_search($chunk, $patterns, true);
        }
        $check = array_pop($values);
        $sum = $values[0];
        foreach (array_slice($values, 1) as $p => $v) {
            $sum += $v * ($p + 1);
        }
        $this->assertSame($sum % 103, $check, 'checksum');
        $set = $values[0] === 105 ? 'C' : 'B';
        $out = '';
        foreach (array_slice($values, 1) as $v) {
            if ($v === 99) {
                $set = 'C';
            } elseif ($v === 100) {
                $set = 'B';
            } else {
                $out .= $set === 'C' ? str_pad((string) $v, 2, '0', STR_PAD_LEFT) : chr($v + 32);
            }
        }

        return $out;
    }

    public function test_code128_encodes_and_round_trips(): void
    {
        // ตรงกับ JsBarcode (CODE128B) ทุก module
        $this->assertSame('11010010000100010110001001110110010011101100100111011001001110110010011101100100111011001001110110010011100110111011001001100011101011',
            Barcode::modules('B00000001', false));
        foreach (['B00000001', '00012345', 'LIB-2569/0001', '1234567', 'A1B2', '9786160821543', 'x'] as $s) {
            $this->assertSame($s, $this->decode(Barcode::modules($s)), $s);
        }
        // ชุด C ทำให้เลขยาวสั้นลง
        $this->assertLessThan(strlen(Barcode::modules('B00000001', false)), strlen(Barcode::modules('B00000001')));
        $this->assertStringContainsString('<svg', Barcode::svg('B00000001'));
    }

    public function test_dewey_helpers_and_isbn(): void
    {
        $this->assertSame('500 วิทยาศาสตร์', Dewey::label('567.9'));
        $this->assertSame('560', Dewey::division('567.9'));
        $this->assertNull(Dewey::label('56'));
        $this->assertSame('ศ', Dewey::initial(Dewey::authorEntry('นายศรีสุข รักเรียน')));
        $this->assertSame('ร', Dewey::initial('เรื่องเล่า'));
        $this->assertSame('Murphy', Dewey::authorEntry('Murphy, Raymond'));
        $this->assertSame('Murphy', Dewey::authorEntry('Raymond Murphy'));
        $this->assertSame('Grammar in Use', Dewey::titleEntry('The Grammar in Use'));
        $this->assertTrue(LibraryController::isbnValid('9786160821549'));
        $this->assertFalse(LibraryController::isbnValid('9786160821544'));
        $this->assertTrue(LibraryController::isbnValid('080442957X'));
    }

    public function test_catalog_a_book_with_copies_barcodes_and_accession_numbers(): void
    {
        Storage::fake('public');
        $t = $this->teacher();
        $this->actingAs($t)->get('/library/create')->assertOk()->assertSee('ระบบทศนิยมของดิวอี้')->assertSee('B00000022'); // ต่อจาก 21 เล่มของ seeder

        // เลขหมู่ผิดรูปแบบ / ISBN ผิด / ไม่มีเลขหมู่ → ไม่รับ
        $this->actingAs($t)->post('/library', $this->form(['class_number' => '56']))->assertSessionHasErrors('class_number');
        $this->actingAs($t)->post('/library', $this->form(['isbn' => '9786160821548']))->assertSessionHasErrors('isbn');
        $this->actingAs($t)->post('/library', $this->form(['class_number' => '']))->assertSessionHasErrors('class_number');

        $this->actingAs($t)->post('/library', $this->form(['cover' => UploadedFile::fake()->image('cover.jpg', 300, 400)]))->assertRedirect();
        $book = Book::where('title', 'ไดโนเสาร์ครองโลก')->first();
        $this->assertSame(['9786160821549', '500 วิทยาศาสตร์', 'ไดโนเสาร์; สัตว์ดึกดำบรรพ์', 3], [$book->isbn, $book->category, $book->subjects, $book->copies]);
        $this->assertSame(['567.9', 'ศ532ด', 'ล.1'], $book->callNumber());
        $this->assertSame('กรุงเทพฯ : นานมีบุ๊คส์, 2566', $book->imprint());
        $this->assertSame('120 หน้า : ภาพประกอบ ; 24 ซม.', $book->physical());
        $items = $book->items;
        $this->assertSame(['B00000022', 'B00000023', 'B00000024'], $items->pluck('barcode')->all());
        $this->assertSame(['00022', '00023', '00024'], $items->pluck('accession_no')->all());
        $this->assertSame([1, 2, 3], $items->pluck('copy_no')->all());
        $this->assertSame(['567.9', 'ศ532ด', 'ล.1', 'ฉ.2'], $items[1]->callNumber());
        Storage::disk('public')->assertExists($book->cover);

        $this->actingAs($t)->get("/library/{$book->id}")->assertOk()->assertSee('B00000023')->assertSee('500 วิทยาศาสตร์')->assertSee('กรุงเทพฯ : นานมีบุ๊คส์, 2566')->assertSee('พิมพ์ป้ายติดเล่ม');
        $this->actingAs($t)->get('/library?q=B00000023')->assertOk()->assertSee('ไดโนเสาร์ครองโลก');
        $this->actingAs($t)->get('/library?q=9786160821549')->assertSee('ไดโนเสาร์ครองโลก');
        $this->actingAs($t)->get('/library?class=500')->assertSee('ไดโนเสาร์ครองโลก')->assertDontSee('เจ้าชายน้อย');

        // นวนิยาย: ใช้ "น" แทนเลขหมู่ ไม่ต้องมีเลขหมู่ · บาร์โค้ดเดิมที่ติดมาใส่เองได้
        $this->actingAs($t)->post('/library', $this->form(['title' => 'บ้านเล็กในป่าใหญ่', 'collection' => 'fiction', 'class_number' => '', 'author_mark' => 'ว111บ', 'volume' => '',
            'isbn' => '', 'copies_count' => 1, 'barcode' => 'OLD-777']))->assertRedirect();
        $novel = Book::where('title', 'บ้านเล็กในป่าใหญ่')->first();
        $this->assertSame(['น', 'ว111บ'], $novel->callNumber());
        $this->assertSame('นวนิยาย', $novel->category);
        $this->assertSame('OLD-777', $novel->items->first()->barcode);

        // เพิ่มเล่ม → ต่อเลข · แก้เล่ม → ต้องพิมพ์ป้ายใหม่
        $this->actingAs($t)->post("/library/{$book->id}/copies", ['copies_count' => 2, 'location' => 'ชั้น 500'])->assertRedirect();
        $this->assertSame([4, 5], $book->items()->get()->pluck('copy_no')->slice(3)->values()->all());
        $this->assertSame(5, $book->fresh()->copies);
        $c = $items[0];
        $c->update(['label_printed_at' => now()]);
        $this->actingAs($t)->put("/library-copies/{$c->id}", ['barcode' => 'B00000099', 'copy_no' => 1, 'condition' => 'good'])->assertRedirect();
        $this->assertNull($c->fresh()->label_printed_at);
        $this->actingAs($t)->put("/library-copies/{$c->id}", ['barcode' => 'B00000023', 'copy_no' => 1, 'condition' => 'good'])->assertSessionHasErrors('barcode');
        $this->actingAs($t)->put("/library-copies/{$c->id}", ['barcode' => 'B00000099', 'copy_no' => 1, 'condition' => 'withdrawn'])->assertRedirect();
        $this->assertSame(4, $book->fresh()->copies);
    }

    public function test_labels_spine_outer_inner_and_queue(): void
    {
        $t = $this->teacher();
        // seeder: 8 เรื่องแรกพิมพ์ป้ายแล้ว · 2 เรื่องหลังยังไม่พิมพ์
        $pending = BookCopy::whereNull('label_printed_at')->count();
        $this->assertSame(2, $pending);
        $res = $this->actingAs($t)->get('/library-labels')->assertOk()->assertSee('ยังไม่ได้พิมพ์ (2)')->assertSee('100 เรื่องไดโนเสาร์');
        $res->assertSee('class="spine', false)->assertSee('class="lbl-outer', false)->assertSee('class="lbl-inner', false)->assertSee('<svg class="barcode"', false);

        $dict = Book::where('collection', 'reference')->first();
        $this->actingAs($t)->get("/library-labels?source=book&book={$dict->id}&type=spine")->assertOk()
            ->assertSee('<div>อ</div>', false)->assertSee('<div>495.913</div>', false)->assertSee('<div>ร141พ</div>', false)->assertDontSee('class="lbl-outer', false);
        $this->actingAs($t)->get('/library-labels?source=range&from=00001&to=00003&type=inner')->assertOk()->assertSee('00002')->assertSee('เจ้าชายน้อย');
        // เว้นช่องที่ใช้ไปแล้ว
        $this->actingAs($t)->get('/library-labels?type=outer&skip=5')->assertOk()->assertSee('lbl-outer blank', false);

        $ids = BookCopy::whereNull('label_printed_at')->pluck('id')->all();
        $this->actingAs($t)->post('/library-labels/printed', ['ids' => $ids])->assertRedirect();
        $this->assertSame(0, BookCopy::whereNull('label_printed_at')->count());
        $this->actingAs($t)->get('/library-labels')->assertSee('ทุกเล่มพิมพ์ป้ายแล้ว');
    }

    public function test_borrow_and_return_by_copy_barcode(): void
    {
        $t = $this->teacher();
        [$a, $b] = Student::active()->whereDoesntHave('bookLoans', fn ($q) => $q->whereNull('returned_on'))->take(2)->get()->all();
        $book = Book::where('title', 'คณิตคิดสนุก ม.ต้น')->first();
        $copy = $book->items->last();

        $this->actingAs($t)->post('/library-loans/borrow', ['student' => $a->student_code, 'book' => $copy->barcode])->assertSessionHasNoErrors();
        $loan = BookLoan::latest('id')->first();
        $this->assertSame([$copy->id, $book->id], [$loan->book_copy_id, $loan->book_id]);
        // เล่มเดียวกันยืมซ้ำไม่ได้ · ยืมด้วยเลขทะเบียน/ISBN ก็ได้ (เลือกเล่มที่ว่าง)
        $this->actingAs($t)->post('/library-loans/borrow', ['student' => $b->student_code, 'book' => $copy->barcode])->assertSessionHasErrors('book');
        $this->actingAs($t)->post('/library-loans/borrow', ['student' => $b->student_code, 'book' => $book->isbn])->assertSessionHasNoErrors();
        $this->assertNotSame($copy->id, BookLoan::latest('id')->first()->book_copy_id);

        // หนังสืออ้างอิงไม่ให้ยืมออก · เล่มส่งซ่อมยืมไม่ได้
        $dict = Book::where('collection', 'reference')->first();
        $this->actingAs($t)->post('/library-loans/borrow', ['student' => $b->student_code, 'book' => $dict->items->first()->barcode])->assertSessionHasErrors('book');
        $other = Book::where('title', 'ความลับของจักรวาล')->first()->items->first();
        $other->update(['condition' => 'repair']);
        $this->actingAs($t)->post('/library-loans/borrow', ['student' => $b->student_code, 'book' => $other->barcode])->assertSessionHasErrors('book');

        $this->actingAs($t)->get('/library-loans')->assertOk()->assertSee($copy->barcode);
        $this->actingAs($t)->post('/library-loans/return', ['book' => $copy->barcode])->assertSessionHasNoErrors();
        $this->assertNotNull($loan->fresh()->returned_on);
        $this->actingAs($t)->post('/library-loans/return', ['book' => $copy->barcode])->assertSessionHasErrors('return');
    }

    public function test_numbering_patterns_for_barcode_and_accession(): void
    {
        $t = $this->teacher();
        $this->actingAs($t)->get('/library-numbering/library-barcode')->assertOk()->assertSee('รูปแบบบาร์โค้ดหนังสือ')->assertSee('B00000022');
        $this->actingAs($t)->put('/library-numbering/library-barcode', ['pattern' => 'ห{SEQ6}'])->assertSessionHasErrors('pattern');
        $this->actingAs($t)->put('/library-numbering/library-barcode', ['pattern' => 'LIB{SEQ6}'])->assertRedirect(route('library.numbering', 'library-barcode'));
        $this->actingAs($t)->put('/library-numbering/library-accession', ['pattern' => '{SEQ4}/{YEAR}'])->assertRedirect();
        $this->actingAs($t)->post('/library', $this->form(['copies_count' => 2]))->assertRedirect();
        $items = Book::where('title', 'ไดโนเสาร์ครองโลก')->first()->items;
        $this->assertSame(['LIB000001', 'LIB000002'], $items->pluck('barcode')->all());
        $this->assertSame(['0001/'.(today()->year + 543), '0002/'.(today()->year + 543)], $items->pluck('accession_no')->all());
        $this->actingAs(User::where('role', 'parent')->first())->get('/library-numbering/library-barcode')->assertForbidden();
    }
}
