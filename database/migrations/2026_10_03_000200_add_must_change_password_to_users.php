<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** บัญชีที่ได้รหัสผ่านจากคนอื่น (สร้างให้/รีเซ็ตให้) ต้องตั้งรหัสใหม่เองก่อนใช้งาน */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('must_change_password')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('must_change_password');
        });
    }
};
