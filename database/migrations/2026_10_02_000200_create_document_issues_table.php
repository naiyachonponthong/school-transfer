<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ทะเบียนคุมการออกเอกสาร (เริ่มจาก ปพ.7 ใบรับรองผลการศึกษา)
 * เก็บข้อมูลที่พิมพ์ไว้ (snapshot) เพื่อพิมพ์ซ้ำได้ตรงกับฉบับที่ออกไปจริง
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_issues', function (Blueprint $t) {
            $t->id();
            $t->string('type', 10);                          // pp7
            $t->unsignedSmallInteger('year');                // ปี พ.ศ. ของเลขที่
            $t->unsignedInteger('number');                   // เลขที่ในปีนั้น
            $t->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $t->string('student_name');
            $t->string('purpose');
            $t->date('issued_on');
            $t->json('snapshot');
            $t->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['type', 'year', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_issues');
    }
};
