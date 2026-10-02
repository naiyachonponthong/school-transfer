<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * คำตอบของคำถามที่โรงเรียนเพิ่มเองในฟอร์มรับสมัคร
 * เก็บเป็นภาพถ่าย (snapshot) พร้อมข้อความคำถาม ณ ตอนที่ส่ง — แก้ฟอร์มทีหลังใบสมัครเก่ายังอ่านถูก
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->json('answers')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('answers');
        });
    }
};
