<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1) ลบนักเรียน/บัญชีผู้ใช้แล้วกระเป๋าเงิน สมุดรายการ และยอดขายของร้านต้องไม่หายตาม (ชั้นฐานข้อมูล เสริมจากการตรวจใน controller)
 * 2) ใบจ่ายเงินให้ร้านค้า: เงินค่าสินค้าตัดจากกระเป๋ามาอยู่ที่โรงเรียน โรงเรียนจึงต้องจ่ายยอดขายคืนให้ร้านเป็นงวด
 * 3) ปิดยอดรายวันรวมเงินสดของกระเป๋าเงิน (เติมเงินสด ถอนคืน จ่ายร้านค้า)
 */
return new class extends Migration
{
    private const WALLET_CHILDREN = ['wallet_sales', 'wallet_topups', 'wallet_transactions'];

    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->foreign('student_id')->references('id')->on('students')->restrictOnDelete();
        });
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
        foreach (self::WALLET_CHILDREN as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['wallet_id']);
                $table->foreign('wallet_id')->references('id')->on('wallets')->restrictOnDelete();
            });
        }

        Schema::table('shops', function (Blueprint $table) {
            $table->decimal('fee_percent', 5, 2)->default(0);  // ส่วนแบ่ง/ค่าบริการที่โรงเรียนหักจากยอดขาย (ร้อยละ)
            $table->string('payout_account')->nullable();      // ช่องทางรับเงินของร้าน (ธนาคาร เลขบัญชี ชื่อบัญชี)
        });

        Schema::create('shop_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 20)->unique();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->date('from_date');                         // วันของรายการขายแรกและสุดท้ายในใบนี้
            $table->date('to_date');
            $table->unsignedInteger('sales_count');
            $table->decimal('gross', 12, 2);                   // ยอดขายรวม
            $table->decimal('fee_percent', 5, 2)->default(0);  // อัตรา ณ วันที่จ่าย
            $table->decimal('fee', 12, 2)->default(0);
            $table->decimal('net', 12, 2);                     // ยอดที่จ่ายให้ร้าน
            $table->string('method', 10);                      // cash, transfer
            $table->string('note')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'paid_at']);
        });

        // รายการขายที่จ่ายเงินให้ร้านแล้วชี้ไปที่ใบจ่ายเงินนั้น (ว่าง = ยังไม่ได้จ่าย)
        Schema::table('wallet_sales', function (Blueprint $table) {
            $table->foreignId('settlement_id')->nullable()->constrained('shop_settlements')->nullOnDelete();
        });

        // วิธีคืนเงินของรายการถอนคืน (เงินสดนับในใบนำส่งของวันนั้น)
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->string('method', 10)->nullable();
        });

        Schema::table('cash_closings', function (Blueprint $table) {
            $table->decimal('wallet_cash_in', 12, 2)->default(0);   // เติมเงินสดเข้ากระเป๋า
            $table->decimal('wallet_cash_out', 12, 2)->default(0);  // ถอนคืนเป็นเงินสด
            $table->decimal('shop_cash_out', 12, 2)->default(0);    // จ่ายร้านค้าเป็นเงินสด
        });
    }

    public function down(): void
    {
        Schema::table('cash_closings', fn (Blueprint $table) => $table->dropColumn(['wallet_cash_in', 'wallet_cash_out', 'shop_cash_out']));
        Schema::table('wallet_transactions', fn (Blueprint $table) => $table->dropColumn('method'));
        Schema::table('wallet_sales', fn (Blueprint $table) => $table->dropConstrainedForeignId('settlement_id'));
        Schema::dropIfExists('shop_settlements');
        Schema::table('shops', fn (Blueprint $table) => $table->dropColumn(['fee_percent', 'payout_account']));

        foreach (self::WALLET_CHILDREN as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['wallet_id']);
                $table->foreign('wallet_id')->references('id')->on('wallets')->cascadeOnDelete();
            });
        }
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
        });
    }
};
