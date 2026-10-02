<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * สอบคัดเลือกนักเรียนเข้าเรียน: รอบสอบ (ปี + ชั้น) · ห้องสอบ/เลขประจำตัวสอบ · ชุดข้อสอบรายวิชาของรอบ · ผลคัดเลือก
 * ใช้ระบบตรวจกระดาษคำตอบเดิม โดยแผ่นคำตอบผูกกับใบสมัครแทนนักเรียน
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_rounds', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('year');                 // ปีการศึกษาที่รับเข้า (พ.ศ.)
            $t->string('level', 20);                          // ม.1 / ม.4 / ป.1
            $t->date('exam_date')->nullable();
            $t->json('rooms')->nullable();                    // [{name, seats}]
            $t->unsignedInteger('exam_no_start')->nullable(); // เลขประจำตัวสอบคนแรก เช่น 10001
            $t->string('order_by', 10)->default('app_no');    // app_no = ตามลำดับสมัคร · name = ตามชื่อ
            $t->unsignedInteger('quota')->nullable();         // จำนวนรับ
            $t->unsignedInteger('reserve')->default(0);       // จำนวนตัวสำรอง
            $t->decimal('min_score', 7, 2)->nullable();       // คะแนนรวมขั้นต่ำ (ถ่วงน้ำหนักแล้ว)
            $t->string('announce_note', 1000)->nullable();    // ข้อความท้ายประกาศ เช่น วันมอบตัว
            $t->timestamp('published_at')->nullable();
            $t->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['year', 'level']);
        });

        Schema::table('exams', function (Blueprint $t) {
            $t->foreignId('admission_round_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $t->string('subject_name', 100)->nullable()->after('subject_id'); // ชื่อวิชาสอบคัดเลือก (ไม่ผูกรายวิชาในหลักสูตร)
            $t->decimal('weight', 6, 2)->default(1)->after('points');          // น้ำหนักเมื่อรวมคะแนนหลายวิชา
        });
        Schema::table('exams', function (Blueprint $t) {
            // ชุดข้อสอบคัดเลือกไม่มีภาคเรียน/รายวิชา
            $t->unsignedBigInteger('term_id')->nullable()->change();
            $t->unsignedBigInteger('subject_id')->nullable()->change();
        });

        Schema::table('exam_responses', function (Blueprint $t) {
            $t->foreignId('application_id')->nullable()->after('student_id')->constrained('applications')->nullOnDelete();
        });

        Schema::table('applications', function (Blueprint $t) {
            $t->string('exam_no', 10)->nullable()->after('exam_seat')->index(); // เลขประจำตัวสอบ 5 หลัก (ระบายบนกระดาษคำตอบ)
            $t->decimal('exam_total', 8, 2)->nullable()->after('exam_no');       // คะแนนรวมถ่วงน้ำหนัก ณ วันประกาศ
            $t->unsignedInteger('exam_rank')->nullable()->after('exam_total');
            $t->unsignedInteger('reserve_no')->nullable()->after('exam_rank');   // ลำดับสำรอง
        });
    }

    public function down(): void
    {
        Schema::table('applications', fn (Blueprint $t) => $t->dropColumn(['exam_no', 'exam_total', 'exam_rank', 'reserve_no']));
        Schema::table('exam_responses', function (Blueprint $t) {
            $t->dropConstrainedForeignId('application_id');
        });
        Schema::table('exams', function (Blueprint $t) {
            $t->dropConstrainedForeignId('admission_round_id');
            $t->dropColumn(['subject_name', 'weight']);
        });
        Schema::dropIfExists('admission_rounds');
    }
};
