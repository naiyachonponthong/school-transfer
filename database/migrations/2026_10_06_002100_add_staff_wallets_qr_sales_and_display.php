<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // กระเป๋าเงินของครู/บุคลากร: กระเป๋าหนึ่งใบเป็นของนักเรียนหรือของบุคลากรอย่างใดอย่างหนึ่ง
        Schema::table('wallets', function (Blueprint $table) {
            $table->unsignedBigInteger('student_id')->nullable()->change();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnDelete();
        });

        // การขายที่รับเงินด้วย QR พร้อมเพย์ (ไม่ผ่านกระเป๋า) จึงไม่มีกระเป๋าผูกอยู่
        Schema::table('wallet_sales', function (Blueprint $table) {
            $table->unsignedBigInteger('wallet_id')->nullable()->change();
            $table->string('payment', 10)->default('wallet'); // wallet, qr
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->string('promptpay_id', 20)->nullable();        // พร้อมเพย์ของร้าน (ว่าง = ใช้ของโรงเรียน)
            $table->boolean('show_balance')->default(true);       // แสดงยอดคงเหลือบนหน้าจอลูกค้า
        });
    }

    public function down(): void
    {
        Schema::table('shops', fn (Blueprint $table) => $table->dropColumn(['promptpay_id', 'show_balance']));
        Schema::table('wallet_sales', fn (Blueprint $table) => $table->dropColumn('payment'));
        Schema::table('wallets', fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
    }
};
