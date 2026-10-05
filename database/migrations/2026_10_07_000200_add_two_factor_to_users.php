<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ยืนยันตัวตน 2 ขั้นด้วยแอปสร้างรหัส (TOTP) สำหรับบัญชีผู้ดูแลระบบและการเงิน */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();          // เข้ารหัส
            $table->text('two_factor_recovery_codes')->nullable();  // เข้ารหัส (เก็บเฉพาะค่าแฮชของรหัสสำรอง)
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->unsignedBigInteger('two_factor_last_step')->nullable(); // ช่วงเวลาของรหัสล่าสุดที่ใช้ไปแล้ว (กันใช้รหัสเดิมซ้ำ)
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_step']));
    }
};
