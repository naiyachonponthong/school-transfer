<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - behavior_records.auto_key: รายการที่ระบบหักคะแนนเองจากการมาสาย/ขาด (เช่น late:5:2 = สายครบรอบที่ 2 ของภาคเรียน id 5)
 *   คำนวณใหม่ได้โดยไม่หักซ้ำ และคืนคะแนนได้เมื่อแก้สถานะการมาเรียน
 * - document_issues ใช้เก็บ ปพ.2 ด้วย (type = pp2) ไม่ต้องเพิ่มตาราง
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('behavior_records', function (Blueprint $t) {
            $t->string('auto_key', 40)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('behavior_records', function (Blueprint $t) {
            $t->dropIndex(['auto_key']);
            $t->dropColumn('auto_key');
        });
    }
};
