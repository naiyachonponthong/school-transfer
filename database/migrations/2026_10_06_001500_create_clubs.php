<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // รายวิชาของชุมนุม: ห้องเดียว+วิชาเดียวมีได้หลายชุมนุม จึงเพิ่ม variant ไว้แยก (รายวิชาปกติ = '')
        Schema::table('courses', function (Blueprint $table) {
            $table->string('variant', 40)->default('');
            $table->string('title')->nullable(); // ชื่อที่แสดงแทนชื่อวิชา เช่น ชื่อชุมนุม
            $table->unique(['term_id', 'classroom_id', 'subject_id', 'variant'], 'courses_term_room_subject_variant_unique');
        });
        Schema::table('courses', fn (Blueprint $table) => $table->dropUnique(['term_id', 'classroom_id', 'subject_id']));

        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete(); // ครูที่ปรึกษา
            $table->unsignedSmallInteger('capacity')->nullable(); // null = ไม่จำกัด
            $table->json('levels')->nullable();                   // ระดับชั้นที่รับ (null = ทุกระดับ)
            $table->string('location')->nullable();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete(); // รายวิชาสำหรับเช็คชื่อ/ประเมินผล
            $table->timestamps();
            $table->unique(['term_id', 'name']);
        });

        // นักเรียนหนึ่งคนอยู่ได้หนึ่งชุมนุมต่อภาคเรียน (บังคับที่ฐานข้อมูล กันกดพร้อมกันสองเครื่อง)
        Schema::create('club_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['term_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_members');
        Schema::dropIfExists('clubs');
        Schema::table('courses', function (Blueprint $table) {
            $table->unique(['term_id', 'classroom_id', 'subject_id']);
        });
        Schema::table('courses', function (Blueprint $table) {
            $table->dropUnique('courses_term_room_subject_variant_unique');
            $table->dropColumn(['variant', 'title']);
        });
    }
};
