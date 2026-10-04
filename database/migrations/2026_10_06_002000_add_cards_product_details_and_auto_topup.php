<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // บัตรแตะ (RFID/NFC): หมายเลขบัตรที่เครื่องอ่านส่งมา ใช้แทนการสแกน QR ได้ทุกจุด
        Schema::table('students', function (Blueprint $table) {
            $table->string('card_uid', 40)->nullable()->unique();
        });

        // รายละเอียดสินค้า
        Schema::table('shop_products', function (Blueprint $table) {
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->string('barcode', 40)->nullable();
            $table->string('unit', 20)->nullable();
            $table->decimal('cost', 10, 2)->nullable();   // ต้นทุนต่อหน่วย (ไว้ดูกำไรขั้นต้น)
            $table->integer('stock')->nullable();         // ว่าง = ไม่นับสต็อก
            $table->index(['shop_id', 'barcode']);
        });

        // เติมเงินอัตโนมัติผ่านธนาคาร/ผู้ให้บริการรับชำระ: อ้างอิงที่ใส่ใน QR และเลขรายการของธนาคาร (กันเข้าซ้ำ)
        Schema::table('wallet_topups', function (Blueprint $table) {
            $table->string('reference', 20)->nullable()->unique();
            $table->string('gateway_txn', 80)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_topups', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropUnique(['gateway_txn']);
            $table->dropColumn(['reference', 'gateway_txn']);
        });
        Schema::table('shop_products', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'barcode']);
            $table->dropColumn(['image', 'description', 'barcode', 'unit', 'cost', 'stock']);
        });
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['card_uid']);
            $table->dropColumn('card_uid');
        });
    }
};
