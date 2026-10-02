<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });

        // ภาคเรียน: ปีการศึกษา (พ.ศ.) + ภาค 1/2
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('term');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
            $table->unique(['year', 'term']);
        });

        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('level', 20);          // ป.1, ม.3
            $table->unsignedTinyInteger('room');  // 1, 2, 3
            $table->unsignedSmallInteger('level_order')->default(0);
            $table->foreignId('homeroom_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('co_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['year', 'level', 'room']);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_code', 20)->unique();
            $table->string('citizen_id', 13)->nullable();
            $table->string('prefix', 20)->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('nickname', 50)->nullable();
            $table->string('gender', 1)->nullable(); // M/F
            $table->date('birthdate')->nullable();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('number')->nullable(); // เลขที่
            $table->string('status', 20)->default('active')->index(); // active, graduated, moved, dropped
            $table->string('photo')->nullable();
            $table->string('blood_type', 5)->nullable();
            $table->text('medical_note')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->timestamps();
            $table->index(['classroom_id', 'number']);
        });

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('relation', 30)->nullable(); // บิดา มารดา ผู้ปกครอง
            $table->unique(['user_id', 'student_id']);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->decimal('credit', 3, 1)->default(1.0);
            $table->string('type', 20)->default('basic'); // basic พื้นฐาน, extra เพิ่มเติม, activity กิจกรรม
            $table->string('group')->nullable();          // กลุ่มสาระ
            $table->timestamps();
        });

        // รายวิชาที่เปิดสอนในห้อง/ภาคเรียน
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('locked')->default(false);
            $table->timestamps();
            $table->unique(['term_id', 'classroom_id', 'subject_id']);
        });

        // ช่องคะแนน เช่น เก็บ 1, กลางภาค, ปลายภาค
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('max_score', 6, 2);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 6, 2)->nullable();
            $table->timestamps();
            $table->unique(['assessment_id', 'student_id']);
        });

        Schema::create('timetable_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day');    // 1=จันทร์ ... 5=ศุกร์
            $table->unsignedTinyInteger('period'); // คาบที่
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();   // เช่น "ลูกเสือ" ที่ไม่ใช่รายวิชา
            $table->string('room_name', 50)->nullable();
            $table->unique(['term_id', 'classroom_id', 'day', 'period']);
        });
    }

    public function down(): void
    {
        foreach (['timetable_slots', 'scores', 'assessments', 'courses', 'subjects', 'guardian_student', 'students', 'classrooms', 'terms', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
