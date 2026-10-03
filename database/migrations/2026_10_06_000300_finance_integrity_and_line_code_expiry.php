<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('line_link_expires_at')->nullable();
        });

        // เลขที่เอกสารล่าสุดของแต่ละชุด (ใบแจ้งหนี้/ใบเสร็จ/ใบสมัคร) ออกเลขถัดไปโดยล็อกแถวนี้
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->string('key', 20);
            $table->string('period', 10);
            $table->unsignedInteger('last_no')->default(0);
            $table->primary(['key', 'period']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
        });

        Schema::table('payment_slips', function (Blueprint $table) {
            $table->string('image_hash', 64)->nullable()->index(); // กันส่งสลิปใบเดิมซ้ำ
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('discount_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn('discount_note'));
        Schema::table('payment_slips', function (Blueprint $t) {
            $t->dropIndex(['image_hash']);
            $t->dropColumn('image_hash');
        });
        Schema::table('payments', function (Blueprint $t) {
            $t->dropConstrainedForeignId('voided_by');
            $t->dropColumn(['voided_at', 'void_reason']);
        });
        Schema::dropIfExists('document_sequences');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('line_link_expires_at'));
    }
};
