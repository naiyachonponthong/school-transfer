<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1) ประวัติชั้นเรียนรายปี — students.classroom_id ยังเป็น "ห้องปัจจุบัน" เหมือนเดิม
 *    ส่วนข้อมูลย้อนหลัง (สมุดพก/สมุดคะแนนของปีเก่า) อ่านจาก enrollments
 * 2) ลบนักเรียน/ใบแจ้งหนี้แล้วหลักฐานการเงินต้องไม่หายตาม (ชั้นฐานข้อมูล เสริมจากการตรวจใน controller)
 */
return new class extends Migration
{
    private const STATUS = ['active' => 'studying', 'graduated' => 'graduated', 'moved' => 'moved', 'dropped' => 'dropped'];

    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('number')->nullable(); // เลขที่ในปีนั้น
            $table->string('status', 20)->default('studying'); // studying, promoted, retained, graduated, moved, dropped
            $table->timestamps();
            $table->unique(['student_id', 'year']);
            $table->index(['classroom_id', 'number']);
        });

        $this->backfill();

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->foreign('student_id')->references('id')->on('students')->restrictOnDelete();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
        });
        Schema::table('payment_slips', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();
        });
    }

    private function backfill(): void
    {
        $now = now();
        $years = DB::table('classrooms')->pluck('year', 'id');

        // ปีปัจจุบันของแต่ละคน = ห้องที่อยู่ตอนนี้
        $rows = [];
        foreach (DB::table('students')->whereNotNull('classroom_id')->get(['id', 'classroom_id', 'number', 'status']) as $s) {
            $rows[] = [
                'student_id' => $s->id, 'classroom_id' => $s->classroom_id, 'year' => $years[$s->classroom_id],
                'number' => $s->number, 'status' => self::STATUS[$s->status] ?? 'studying',
                'created_at' => $now, 'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('enrollments')->insertOrIgnore($chunk);
        }

        // ปีก่อนหน้า: เดาจากห้องที่เคยถูกเช็คชื่อ แล้วตามด้วยห้องของรายวิชาที่มีคะแนน (เลขที่ของปีเก่าไม่มีเก็บไว้)
        $past = DB::table('attendances')->whereNotNull('classroom_id')
            ->select('student_id', 'classroom_id', DB::raw('count(*) as n'))
            ->groupBy('student_id', 'classroom_id')->orderByDesc('n')->get()
            ->concat(
                DB::table('scores')
                    ->join('assessments', 'assessments.id', '=', 'scores.assessment_id')
                    ->join('courses', 'courses.id', '=', 'assessments.course_id')
                    ->select('scores.student_id', 'courses.classroom_id')->distinct()->get()
            );
        $rows = [];
        foreach ($past as $p) {
            $rows[] = [
                'student_id' => $p->student_id, 'classroom_id' => $p->classroom_id, 'year' => $years[$p->classroom_id],
                'number' => null, 'status' => 'promoted', 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        // unique(student_id, year): แถวแรกของแต่ละปีชนะ (ห้องที่ถูกเช็คชื่อมากที่สุดมาก่อน)
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('enrollments')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        Schema::table('payment_slips', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
        });
        Schema::dropIfExists('enrollments');
    }
};
