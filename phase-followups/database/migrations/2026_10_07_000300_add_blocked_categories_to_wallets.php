<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ผู้ปกครองกำหนดหมวดสินค้าที่ไม่ให้บุตรหลานซื้อด้วยกระเป๋าเงิน (เช่น น้ำอัดลม ขนม) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->json('blocked_categories')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('wallets', fn (Blueprint $table) => $table->dropColumn('blocked_categories'));
    }
};
