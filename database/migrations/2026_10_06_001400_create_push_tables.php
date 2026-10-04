<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // อุปกรณ์ที่ผู้ใช้เปิดรับแจ้งเตือน (Web Push)
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        // ข้อความที่รออุปกรณ์มารับ (ส่งสัญญาณปลุกแบบไม่มีเนื้อหา แล้ว service worker มาดึงข้อความเอง)
        Schema::create('push_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('text', 1000);
            $table->string('url', 500)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'delivered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_messages');
        Schema::dropIfExists('push_subscriptions');
    }
};
