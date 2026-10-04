<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ร้านค้าในโรงเรียน (โรงอาหาร สหกรณ์ ฯลฯ) และคนขายของแต่ละร้าน
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('shop_user', function (Blueprint $table) {
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['shop_id', 'user_id']);
        });
        Schema::create('shop_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 60)->nullable();
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // กระเป๋าเงินของนักเรียน (หนึ่งคนหนึ่งใบ)
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('balance', 12, 2)->default(0);
            $table->decimal('daily_limit', 10, 2)->nullable(); // ว่าง = ไม่จำกัด
            $table->boolean('is_frozen')->default(false);      // ระงับการใช้จ่าย (บัตรหาย)
            $table->date('low_notified_on')->nullable();       // แจ้งยอดใกล้หมดวันละครั้ง
            $table->timestamps();
        });

        // การขายหนึ่งครั้ง
        Schema::create('wallet_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->decimal('total', 10, 2);
            $table->json('items');                       // [{name, price, qty}]
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_key', 64)->unique();  // กันตัดเงินซ้ำเมื่อกดซ้ำ/เน็ตสะดุด
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'created_at']);
        });

        // คำขอเติมเงิน: เงินสดที่ห้องการเงิน (อนุมัติทันที) หรือโอนแล้วแนบสลิป (รอตรวจ)
        Schema::create('wallet_topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('method', 10);                // cash, transfer
            $table->string('status', 10)->default('pending'); // pending, approved, rejected
            $table->string('slip')->nullable();
            $table->string('slip_hash', 64)->nullable()->index();
            $table->string('note')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        // สมุดรายการของกระเป๋า: เพิ่มอย่างเดียว ไม่แก้ ไม่ลบ (ผิดให้ลงรายการกลับ)
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);                  // topup, purchase, void, adjust, withdraw
            $table->decimal('amount', 12, 2);            // บวก = เข้า, ลบ = ออก
            $table->decimal('balance_after', 12, 2);
            $table->foreignId('sale_id')->nullable()->constrained('wallet_sales')->nullOnDelete();
            $table->foreignId('topup_id')->nullable()->constrained('wallet_topups')->nullOnDelete();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['wallet_id', 'id']);
        });

        // ตำแหน่ง "ผู้ขาย/ร้านค้า" สำหรับบัญชีของคนขาย
        if (! DB::table('roles')->where('key', 'cashier')->exists()) {
            DB::table('roles')->insert(['key' => 'cashier', 'name' => 'ผู้ขาย/ร้านค้า', 'permissions' => json_encode(['pos.use']),
                'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        // ตำแหน่งการเงินเดิมได้สิทธิ์จัดการกระเป๋าเงินด้วย
        $finance = DB::table('roles')->where('key', 'finance')->first();
        if ($finance) {
            $permissions = array_values(array_unique(array_merge(json_decode($finance->permissions, true) ?: [], ['wallet.manage', 'pos.use'])));
            DB::table('roles')->where('id', $finance->id)->update(['permissions' => json_encode($permissions)]);
        }
    }

    public function down(): void
    {
        foreach (['wallet_transactions', 'wallet_topups', 'wallet_sales', 'wallets', 'shop_products', 'shop_user', 'shops'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::table('roles')->where('key', 'cashier')->delete();
    }
};
