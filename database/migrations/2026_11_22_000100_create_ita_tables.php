<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ITA / OIT: การเปิดเผยข้อมูลสาธารณะบนเว็บไซต์ แยกตามปีงบประมาณ */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ita_years', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('year')->unique(); // ปีงบประมาณ พ.ศ.
            $t->string('note')->nullable();
            $t->boolean('is_published')->default(false);
            $t->timestamps();
        });

        Schema::create('ita_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ita_year_id')->constrained()->cascadeOnDelete();
            $t->string('code', 10);           // O1, O2, ...
            $t->string('group')->nullable();  // ตัวชี้วัด / ตัวชี้วัดย่อย
            $t->string('title');
            $t->text('description')->nullable();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->boolean('done')->default(false);
            $t->timestamps();
            $t->unique(['ita_year_id', 'code']);
        });

        Schema::create('ita_evidences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ita_item_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('kind', 10); // file, link, post
            $t->string('path')->nullable();
            $t->string('url', 1000)->nullable();
            $t->foreignId('web_post_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedSmallInteger('sort')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ita_evidences');
        Schema::dropIfExists('ita_items');
        Schema::dropIfExists('ita_years');
    }
};
