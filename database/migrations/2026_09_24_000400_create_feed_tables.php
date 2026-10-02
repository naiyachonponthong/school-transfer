<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ฟีดข่าวโรงเรียน: โพสต์ของครู ประกาศเกียรติคุณ และรายการที่ระบบสร้างให้อัตโนมัติ
        Schema::create('feed_posts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('post'); // post, achievement, announcement, welcome
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('headline')->nullable();       // "ได้รับรางวัล", "ผ่านการคัดเลือก"
            $table->string('title')->nullable();          // หัวข้อบนการ์ดรางวัล
            $table->text('body')->nullable();
            $table->string('image')->nullable();
            $table->string('icon', 40)->nullable();
            $table->string('audience', 20)->default('all'); // all, parents, staff, classroom
            $table->foreignId('classroom_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('announcement_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('feed_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 10);
            $table->timestamps();
            $table->unique(['feed_post_id', 'user_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_reactions');
        Schema::dropIfExists('feed_posts');
    }
};
