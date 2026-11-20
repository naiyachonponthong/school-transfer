<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ข่าวแบบจัดรูปแบบ (HTML) + สรุปสั้น แท็ก และป้ายข่าวด่วน ข่าวเดิมยังเป็นข้อความธรรมดา (is_html = false) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_posts', function (Blueprint $t) {
            $t->boolean('is_html')->default(false)->after('body');
            $t->string('excerpt', 300)->nullable()->after('title');
            $t->json('tags')->nullable()->after('excerpt');
            $t->boolean('urgent')->default(false)->after('pinned');
        });
    }

    public function down(): void
    {
        Schema::table('web_posts', function (Blueprint $t) {
            $t->dropColumn(['is_html', 'excerpt', 'tags', 'urgent']);
        });
    }
};
