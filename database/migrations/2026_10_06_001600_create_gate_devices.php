<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // เครื่องสแกนใบหน้า/บัตรที่ประตู (มีได้หลายเครื่อง) ส่งเหตุการณ์เข้ามาที่ /gate/hook/{token}
        Schema::create('gate_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location')->nullable();
            $table->string('mode', 5)->default('auto'); // auto = เข้า/ออกตามเวลา · in · out
            $table->string('token', 64)->unique();      // อยู่ในที่อยู่ที่เครื่องส่งข้อมูลมา (เครื่องส่วนใหญ่ใส่ header เองไม่ได้)
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('gate_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gate_device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 60)->nullable();   // รหัสที่เครื่องส่งมา (รหัสนักเรียน)
            $table->string('result', 12);             // present, late, out, repeat, unknown
            $table->timestamp('occurred_at');
            $table->json('payload')->nullable();      // ข้อมูลดิบ (เก็บเฉพาะรายการที่ระบุตัวไม่ได้ ไว้ตรวจสอบ)
            $table->timestamps();
            $table->index(['gate_device_id', 'occurred_at']);
            $table->index(['result', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_events');
        Schema::dropIfExists('gate_devices');
    }
};
