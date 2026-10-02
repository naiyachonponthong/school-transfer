<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ปพ.1 ฉบับจริงพิมพ์บนแบบพิมพ์ควบคุมของกระทรวง: บันทึกชุดที่/เลขที่ของแบบพิมพ์ที่ใช้ */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_issues', function (Blueprint $t) {
            $t->string('form_series', 20)->nullable();
            $t->string('form_number', 20)->nullable();
            $t->unique(['type', 'form_series', 'form_number']);
        });
    }

    public function down(): void
    {
        Schema::table('document_issues', function (Blueprint $t) {
            $t->dropUnique(['type', 'form_series', 'form_number']);
            $t->dropColumn(['form_series', 'form_number']);
        });
    }
};
