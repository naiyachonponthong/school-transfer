<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ทะเบียนครุภัณฑ์ · ตรวจสอบพัสดุประจำปี · แจ้งซ่อม */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $t) {
            $t->id();
            $t->string('code', 50)->unique();                 // เลขครุภัณฑ์ เช่น 7440-001-0001
            $t->string('name');
            $t->string('category', 50)->nullable();
            $t->string('brand')->nullable();                   // ยี่ห้อ / รุ่น
            $t->string('serial_no', 100)->nullable();
            $t->date('acquired_on')->nullable();
            $t->decimal('price', 12, 2)->default(0);
            $t->string('budget_source')->nullable();           // แหล่งงบ / วิธีได้มา
            $t->string('location', 100)->nullable()->index();  // ห้อง / อาคาร
            $t->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedTinyInteger('useful_life')->nullable(); // อายุการใช้งาน (ปี) สำหรับค่าเสื่อม
            $t->string('status', 20)->default('normal')->index();
            $t->string('photo')->nullable();
            $t->text('note')->nullable();
            $t->string('qr_token', 32)->unique();
            $t->date('disposed_on')->nullable();
            $t->timestamps();
        });

        Schema::create('asset_checks', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('year');                  // ปีงบประมาณ/ปี พ.ศ. ที่ตรวจ
            $t->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $t->string('result', 10);                          // found, damaged, missing
            $t->string('note')->nullable();
            $t->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['year', 'asset_id']);
        });

        Schema::create('repair_requests', function (Blueprint $t) {
            $t->id();
            $t->string('ticket_no', 20)->unique();
            $t->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $t->string('location', 100)->nullable();
            $t->string('title');
            $t->text('detail')->nullable();
            $t->string('photo')->nullable();
            $t->string('priority', 10)->default('normal');
            $t->string('status', 20)->default('pending')->index();
            $t->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $t->decimal('cost', 10, 2)->nullable();
            $t->text('result_note')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
        });

        // ไทม์ไลน์ของงานซ่อม (ใครเปลี่ยนสถานะอะไร เมื่อไร)
        Schema::create('repair_updates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('repair_request_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 20);
            $t->string('note', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_updates');
        Schema::dropIfExists('repair_requests');
        Schema::dropIfExists('asset_checks');
        Schema::dropIfExists('assets');
    }
};
