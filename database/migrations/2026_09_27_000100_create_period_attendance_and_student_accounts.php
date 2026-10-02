<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // เช็คชื่อรายคาบ (รายวิชา) — แยกจากเช็คชื่อหน้าเสาธง/โฮมรูมรายวัน
        Schema::create('period_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedTinyInteger('period');
            $table->string('status', 10); // present, late, absent, leave, sick
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['course_id', 'student_id', 'date', 'period'], 'period_att_unique');
            $table->index(['course_id', 'date', 'period']);
            $table->index(['student_id', 'date']);
        });

        // บัญชีนักเรียน: ผูกนักเรียนกับผู้ใช้ role=student
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->dropColumn('user_id');
        });
        Schema::dropIfExists('period_attendances');
    }
};
