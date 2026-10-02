<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ห้องสมุดตามหลักสากล: ระเบียนบรรณานุกรม (ISBN, เลขหมู่ DDC, เลขผู้แต่ง, พิมพลักษณ์, ลักษณะรูปเล่ม, หัวเรื่อง)
 * + ตัวเล่ม (บาร์โค้ด เลขทะเบียน ฉบับที่ สภาพ) แยกจากระเบียน · การยืมผูกกับตัวเล่ม
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $t) {
            $t->string('isbn', 20)->nullable()->after('code')->index();
            $t->string('collection', 20)->default('general')->after('isbn'); // ประเภททรัพยากร (น / รส / ย / อ …)
            $t->string('class_number', 20)->nullable()->after('collection');  // เลขหมู่ DDC เช่น 895.913
            $t->string('author_mark', 20)->nullable()->after('class_number'); // เลขผู้แต่ง+อักษรชื่อเรื่อง เช่น ศ532ร
            $t->string('volume', 20)->nullable()->after('author_mark');       // ล.1
            $t->string('contributors')->nullable()->after('author');         // ผู้แต่งร่วม / ผู้แปล / ผู้วาดภาพ
            $t->string('edition', 40)->nullable()->after('contributors');      // ครั้งที่พิมพ์
            $t->string('pub_place', 100)->nullable()->after('edition');
            $t->string('pub_year', 10)->nullable()->after('publisher');
            $t->unsignedSmallInteger('pages')->nullable()->after('pub_year');
            $t->boolean('illustrated')->default(false)->after('pages');
            $t->unsignedTinyInteger('size_cm')->nullable()->after('illustrated');
            $t->string('series')->nullable()->after('size_cm');
            $t->string('language', 30)->default('ไทย')->after('series');
            $t->string('subjects', 500)->nullable()->after('language');       // หัวเรื่อง คั่นด้วย ;
            $t->text('summary')->nullable()->after('subjects');
            $t->string('note', 500)->nullable()->after('summary');
            $t->string('cover')->nullable()->after('note');
        });

        Schema::create('book_copies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('book_id')->constrained()->cascadeOnDelete();
            $t->string('barcode', 40)->unique();              // บาร์โค้ดติดปก ใช้ยืม-คืน
            $t->string('accession_no', 40)->nullable()->unique(); // เลขทะเบียนหนังสือ
            $t->unsignedSmallInteger('copy_no')->default(1);    // ฉบับที่ (ฉ.)
            $t->string('location', 60)->nullable();             // ชั้นจัดเก็บ
            $t->string('condition', 15)->default('good');       // good · fair · damaged · repair · lost · withdrawn
            $t->decimal('price', 10, 2)->nullable();
            $t->date('acquired_on')->nullable();
            $t->string('source', 60)->nullable();               // ซื้อ / บริจาค / งบ …
            $t->string('note')->nullable();
            $t->timestamp('label_printed_at')->nullable();
            $t->timestamps();
        });

        Schema::table('book_loans', function (Blueprint $t) {
            $t->foreignId('book_copy_id')->nullable()->after('book_id')->constrained()->nullOnDelete();
        });

        // ย้ายข้อมูลเดิม: หนังสือ 1 รายการ × จำนวนเล่ม → ตัวเล่ม (เล่มแรกใช้รหัสเดิมเป็นบาร์โค้ด ยืม-คืนด้วยรหัสเดิมได้)
        $now = now();
        foreach (DB::table('books')->orderBy('id')->get() as $b) {
            $ids = [];
            for ($n = 1; $n <= max(1, (int) $b->copies); $n++) {
                $ids[] = DB::table('book_copies')->insertGetId([
                    'book_id' => $b->id, 'barcode' => $n === 1 ? $b->code : $b->code.'-'.$n, 'copy_no' => $n,
                    'location' => $b->location, 'condition' => 'good', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $active = DB::table('book_loans')->where('book_id', $b->id)->whereNull('returned_on')->orderBy('id')->pluck('id');
            foreach ($active as $i => $loanId) {
                DB::table('book_loans')->where('id', $loanId)->update(['book_copy_id' => $ids[$i % count($ids)]]);
            }
            DB::table('book_loans')->where('book_id', $b->id)->whereNull('book_copy_id')->update(['book_copy_id' => $ids[0]]);
            if (in_array($b->category, ['นวนิยาย'], true)) {
                DB::table('books')->where('id', $b->id)->update(['collection' => 'fiction']);
            }
        }
    }

    public function down(): void
    {
        Schema::table('book_loans', fn (Blueprint $t) => $t->dropConstrainedForeignId('book_copy_id'));
        Schema::dropIfExists('book_copies');
        Schema::table('books', fn (Blueprint $t) => $t->dropColumn(['isbn', 'collection', 'class_number', 'author_mark', 'volume', 'contributors', 'edition',
            'pub_place', 'pub_year', 'pages', 'illustrated', 'size_cm', 'series', 'language', 'subjects', 'summary', 'note', 'cover']));
    }
};
