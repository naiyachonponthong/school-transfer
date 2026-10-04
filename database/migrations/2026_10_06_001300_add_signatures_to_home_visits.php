<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_visits', function (Blueprint $table) {
            // ลายเซ็นที่เซ็นบนหน้าจอ (ไฟล์ PNG ในดิสก์ส่วนตัว)
            $table->string('sign_guardian')->nullable();
            $table->string('sign_visitor')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('home_visits', fn (Blueprint $t) => $t->dropColumn(['sign_guardian', 'sign_visitor']));
    }
};
