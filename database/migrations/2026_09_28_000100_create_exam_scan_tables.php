<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ตรวจข้อสอบปรนัยด้วยมือถือ (ScanGrade) — ชุดข้อสอบผูกกับรายวิชาที่เปิดสอน (หลายห้องได้)
 * กระดาษคำตอบใช้แบบเดียวกับ ScanGrade เดิม (sheet-layout v1) จึงใช้กระดาษที่พิมพ์ไว้แล้วได้
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $t) {
            $t->id();
            $t->foreignId('term_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $t->string('title');                        // เช่น สอบกลางภาค
            $t->unsignedTinyInteger('n_items');         // 1–100 ข้อ 4 ตัวเลือก
            $t->date('exam_date')->nullable();
            $t->json('answer_key')->nullable();         // ['2','3','24',...] '24' = ถูกได้หลายตัวเลือก
            $t->json('cancelled')->nullable();          // [เลขข้อที่ยกเลิก]
            $t->string('cancel_mode', 8)->default('give'); // give = ทุกคนได้คะแนน · drop = ตัดออกจากคะแนนเต็ม
            $t->decimal('points', 6, 2)->default(1);    // คะแนนต่อข้อ
            $t->unsignedInteger('key_version')->default(0);
            $t->string('assessment_name')->nullable();  // ช่องคะแนนในสมุดคะแนนที่จะส่งคะแนนเข้า
            $t->boolean('published')->default(false);   // นักเรียน/ผู้ปกครองเห็นคะแนน
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('course_exam', function (Blueprint $t) {
            $t->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->primary(['exam_id', 'course_id']);
        });

        Schema::create('exam_responses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $t->string('request_id', 80)->unique();     // ส่งซ้ำจากมือถือ (ออฟไลน์) ไม่เกิดแถวซ้ำ
            $t->string('answers', 100);                 // '1'..'4' · '0' ว่าง · '9' ตอบซ้อน
            $t->decimal('score', 7, 2)->nullable();
            $t->decimal('max_score', 7, 2)->nullable();
            $t->string('status', 10)->default('ok');    // ok · review · void
            $t->json('flags')->nullable();
            $t->decimal('confidence', 4, 3)->nullable();
            $t->string('code_read', 8)->nullable();
            $t->string('seat_read', 8)->nullable();
            $t->string('image')->nullable();            // ภาพดัดตรง (ดิสก์ส่วนตัว ไม่เปิดสาธารณะ)
            $t->string('source', 10)->default('camera'); // camera · photo
            $t->unsignedInteger('key_version')->default(0);
            $t->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('scanned_at')->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->json('edits')->nullable();              // ประวัติการแก้ (ใคร แก้อะไร เมื่อไร)
            $t->timestamps();
            $t->index(['exam_id', 'status']);
            $t->index(['exam_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_responses');
        Schema::dropIfExists('course_exam');
        Schema::dropIfExists('exams');
    }
};
