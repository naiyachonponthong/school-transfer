<?php

use App\Support\Grade;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // เกณฑ์ตัดเกรดที่ใช้ตอนอนุมัติผล (รูปแบบเดียวกับค่าตั้ง grade_scale) — แก้เกณฑ์ทีหลังไม่กระทบผลที่อนุมัติแล้ว
            $table->string('grade_scale', 60)->nullable();
        });

        // รายวิชาที่ล็อกไปแล้ว: ถือว่าใช้เกณฑ์ปัจจุบัน
        DB::table('courses')->where('locked', true)->update(['grade_scale' => Grade::scaleString()]);
    }

    public function down(): void
    {
        Schema::table('courses', fn (Blueprint $t) => $t->dropColumn('grade_scale'));
    }
};
