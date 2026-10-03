<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // รายชื่อเฉพาะของรายวิชา (วิชาเลือก/ชุมนุม รับนักเรียนข้ามห้องได้) — ไม่มีแถว = เรียนทั้งห้องเหมือนเดิม
        Schema::create('course_students', function (Blueprint $table) {
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->primary(['course_id', 'student_id']);
            $table->index('student_id');
        });

        // ขั้นตอนส่งผลการเรียน: ครูส่ง → ฝ่ายวิชาการอนุมัติ (ล็อก) หรือตีกลับ
        Schema::table('courses', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('return_note')->nullable();
        });

        Schema::table('terms', function (Blueprint $table) {
            $table->date('results_announce_on')->nullable(); // ก่อนวันนี้ผู้ปกครอง/นักเรียนยังไม่เห็นผลการเรียนของภาคนี้
        });
    }

    public function down(): void
    {
        Schema::table('terms', fn (Blueprint $t) => $t->dropColumn('results_announce_on'));
        Schema::table('courses', function (Blueprint $t) {
            $t->dropConstrainedForeignId('submitted_by');
            $t->dropConstrainedForeignId('approved_by');
            $t->dropColumn(['submitted_at', 'approved_at', 'return_note']);
        });
        Schema::dropIfExists('course_students');
    }
};
