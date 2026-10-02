<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * รับสมัครแบบหลายขั้นตอน: บันทึกร่างแล้วกลับมากรอกต่อได้ (status = draft)
 * + ค่าสมัคร (แนบสลิป → เจ้าหน้าที่ยืนยัน → ใบเสร็จ) + ห้องสอบ/เลขที่นั่งสอบ (พิมพ์ในส่วนที่ 2 ของใบสมัคร)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // ร่างยังไม่มีชื่อ/ผู้ปกครองจนกว่าจะกรอกขั้นนั้น
            $table->string('first_name')->nullable()->change();
            $table->string('last_name')->nullable()->change();
            $table->string('parent_name')->nullable()->change();
            $table->json('steps_done')->nullable()->after('answers');
            $table->timestamp('submitted_at')->nullable()->after('steps_done');
            $table->decimal('fee_amount', 8, 2)->nullable()->after('submitted_at');
            $table->string('fee_status', 10)->default('none')->after('fee_amount'); // none · unpaid · pending (ส่งสลิปแล้ว) · paid
            $table->string('fee_slip')->nullable()->after('fee_status');
            $table->string('fee_note')->nullable()->after('fee_slip');
            $table->string('fee_receipt_no', 30)->nullable()->unique()->after('fee_note');
            $table->timestamp('fee_paid_at')->nullable()->after('fee_receipt_no');
            $table->foreignId('fee_verified_by')->nullable()->after('fee_paid_at')->constrained('users')->nullOnDelete();
            $table->string('exam_room', 60)->nullable()->after('fee_verified_by');
            $table->string('exam_seat', 20)->nullable()->after('exam_room');
            $table->index(['year', 'citizen_id']);
        });
        // ใบสมัครเดิมทั้งหมดส่งแล้ว (ระบบเดิมไม่มีร่าง)
        DB::table('applications')->whereNull('submitted_at')->update(['submitted_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex(['year', 'citizen_id']);
            $table->dropConstrainedForeignId('fee_verified_by');
            $table->dropColumn(['steps_done', 'submitted_at', 'fee_amount', 'fee_status', 'fee_slip', 'fee_note', 'fee_receipt_no', 'fee_paid_at', 'exam_room', 'exam_seat']);
        });
    }
};
