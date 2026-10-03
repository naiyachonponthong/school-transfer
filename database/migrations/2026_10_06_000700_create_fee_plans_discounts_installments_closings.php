<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // รายการค่าธรรมเนียมมาตรฐาน (ใช้จัดหมวดในรายงานรายรับ)
        Schema::create('fee_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 60)->default('ทั่วไป');
            $table->decimal('default_amount', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // แผนเรียกเก็บของแต่ละระดับชั้นในภาคเรียน → ออกใบแจ้งหนี้ทั้งระดับชั้นจากแผน
        Schema::create('fee_plans', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedSmallInteger('year');
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('level', 20);
            $table->date('due_date')->nullable();
            $table->timestamps();
        });
        Schema::create('fee_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_item_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('fee_plan_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('fee_item_id')->nullable()->constrained()->nullOnDelete();
        });

        // ส่วนลดประจำตัวนักเรียน (ทุน พี่น้อง บุตรบุคลากร) หักอัตโนมัติเมื่อออกใบแจ้งหนี้จากแผน
        Schema::create('student_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 10); // percent · amount
            $table->decimal('value', 12, 2);
            $table->foreignId('fee_item_id')->nullable()->constrained()->nullOnDelete(); // null = ลดจากยอดรวม
            $table->unsignedSmallInteger('year')->nullable(); // null = ทุกปี
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('invoice_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('seq');
            $table->date('due_date');
            $table->decimal('amount', 12, 2);
            $table->unique(['invoice_id', 'seq']);
        });

        // ปิดยอดรับเงินประจำวัน: เก็บยอด ณ ตอนปิด หลังปิดแล้วยกเลิกใบเสร็จของวันนั้นไม่ได้
        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('cash', 12, 2)->default(0);
            $table->decimal('transfer', 12, 2)->default(0);
            $table->decimal('promptpay', 12, 2)->default(0);
            $table->unsignedInteger('receipts')->default(0);
            $table->string('note')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_closings');
        Schema::dropIfExists('invoice_installments');
        Schema::dropIfExists('student_discounts');
        Schema::table('invoice_items', fn (Blueprint $t) => $t->dropConstrainedForeignId('fee_item_id'));
        Schema::table('invoices', fn (Blueprint $t) => $t->dropConstrainedForeignId('fee_plan_id'));
        Schema::dropIfExists('fee_plan_items');
        Schema::dropIfExists('fee_plans');
        Schema::dropIfExists('fee_items');
    }
};
