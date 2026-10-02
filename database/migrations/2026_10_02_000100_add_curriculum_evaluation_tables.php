<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ข้อมูลพื้นฐานสำหรับเอกสาร ปพ. ตามหลักสูตรแกนกลางฯ
 * - ผลการเรียนพิเศษ (ร, มส, มผ) และผลการแก้ตัว แยกจากคะแนน
 * - คุณลักษณะอันพึงประสงค์ 8 ข้อ + การอ่าน คิดวิเคราะห์ และเขียน (รายภาคเรียน)
 * - เวลาเรียนของรายวิชา + ประเภทกิจกรรมพัฒนาผู้เรียน
 * - ข้อมูลหัวกระดาษ ปพ.1 ของนักเรียน
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $t) {
            $t->unsignedSmallInteger('hours')->nullable();      // เวลาเรียน (ชั่วโมง) — ประถมใช้แทนหน่วยกิต
            $t->string('activity_kind', 20)->nullable();        // guidance, scout, club, social
        });

        Schema::table('students', function (Blueprint $t) {
            $t->string('nationality', 50)->nullable();
            $t->string('ethnicity', 50)->nullable();            // เชื้อชาติ
            $t->string('religion', 50)->nullable();
            $t->string('father_name')->nullable();
            $t->string('mother_name')->nullable();
            $t->date('admitted_on')->nullable();                // วันเข้าเรียน
            $t->string('previous_school')->nullable();
            $t->string('previous_school_province', 100)->nullable();
            $t->string('previous_level', 20)->nullable();       // ชั้นสุดท้ายจากโรงเรียนเดิม
            $t->date('left_on')->nullable();                    // วันจบ/ออก
            $t->string('leave_reason')->nullable();             // สาเหตุที่ออก
        });

        // ผลการเรียนที่ไม่ได้มาจากคะแนน: ร / มส (วิชาทั่วไป) หรือ มผ (กิจกรรม) + ผลการแก้ตัว
        Schema::create('course_results', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->string('special', 4)->nullable();
            $t->string('remedial_grade', 4)->nullable();
            $t->date('remedied_on')->nullable();
            $t->string('note')->nullable();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['course_id', 'student_id']);
        });

        Schema::create('student_evaluations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('term_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->json('traits')->nullable();                     // {"1":3,...,"8":2} ระดับ 0–3
            $t->unsignedTinyInteger('rtw')->nullable();         // อ่าน คิดวิเคราะห์ และเขียน 0–3
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['term_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_evaluations');
        Schema::dropIfExists('course_results');
        Schema::table('students', function (Blueprint $t) {
            $t->dropColumn(['nationality', 'ethnicity', 'religion', 'father_name', 'mother_name', 'admitted_on',
                'previous_school', 'previous_school_province', 'previous_level', 'left_on', 'leave_reason']);
        });
        Schema::table('subjects', function (Blueprint $t) {
            $t->dropColumn(['hours', 'activity_kind']);
        });
    }
};
