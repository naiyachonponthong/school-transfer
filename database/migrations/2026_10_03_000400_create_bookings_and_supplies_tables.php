<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** จองห้อง/รถ/อุปกรณ์ · วัสดุสิ้นเปลือง (คลัง + ใบเบิก) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookable_resources', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('type', 20)->default('room');           // room, vehicle, equipment
            $t->unsignedSmallInteger('capacity')->nullable();
            $t->string('description')->nullable();
            $t->boolean('requires_approval')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('bookings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('resource_id')->constrained('bookable_resources')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title');                                // วัตถุประสงค์
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->unsignedSmallInteger('attendees')->nullable();
            $t->string('destination')->nullable();              // รถ: ปลายทาง
            $t->string('note', 500)->nullable();
            $t->string('status', 20)->default('approved')->index(); // pending, approved, rejected, cancelled
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('review_note')->nullable();
            $t->timestamps();
            $t->index(['resource_id', 'starts_at']);
        });

        Schema::create('supplies', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('unit', 30)->default('ชิ้น');
            $t->string('category', 50)->nullable();
            $t->integer('stock')->default(0);
            $t->unsignedInteger('min_stock')->default(0);       // ต่ำกว่านี้ = ใกล้หมด
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('supply_requisitions', function (Blueprint $t) {
            $t->id();
            $t->string('req_no', 20)->unique();
            $t->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('department', 100)->nullable();         // กลุ่มสาระ / งาน
            $t->string('purpose')->nullable();
            $t->string('status', 20)->default('pending')->index(); // pending, issued, rejected, cancelled
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('review_note')->nullable();
            $t->timestamps();
        });

        Schema::create('supply_requisition_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('requisition_id')->constrained('supply_requisitions')->cascadeOnDelete();
            $t->foreignId('supply_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('issued')->nullable();
        });

        // บัญชีวัสดุ (stock card): ทุกการรับเข้า/จ่ายออก/ปรับยอด พร้อมยอดคงเหลือหลังรายการ
        Schema::create('supply_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supply_id')->constrained()->cascadeOnDelete();
            $t->string('type', 10);                             // in, out, adjust
            $t->integer('quantity');                            // + รับเข้า / − จ่ายออก
            $t->integer('balance');
            $t->foreignId('requisition_id')->nullable()->constrained('supply_requisitions')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('note')->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_transactions');
        Schema::dropIfExists('supply_requisition_items');
        Schema::dropIfExists('supply_requisitions');
        Schema::dropIfExists('supplies');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('bookable_resources');
    }
};
