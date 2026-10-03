<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_visits', function (Blueprint $table) {
            $table->json('form')->nullable();              // คำตอบตามแบบบันทึกการเยี่ยมบ้าน 4 หน้า (App\Support\HomeVisitForm)
            $table->string('photo_inside')->nullable();    // รูปที่ 2 ภาพถ่ายภายในบ้าน (ดิสก์ส่วนตัว)
        });
    }

    public function down(): void
    {
        Schema::table('home_visits', fn (Blueprint $t) => $t->dropColumn(['form', 'photo_inside']));
    }
};
