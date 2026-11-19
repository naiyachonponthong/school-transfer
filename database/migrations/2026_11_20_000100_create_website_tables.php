<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** เว็บไซต์โรงเรียน (หน้าบ้าน): ข่าว สไลด์ หน้าเนื้อหา ลิงก์หน่วยงาน ข้อความติดต่อ */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_posts', function (Blueprint $t) {
            $t->id();
            $t->string('category', 20)->default('news'); // news ประชาสัมพันธ์, activity กิจกรรม, procurement จัดซื้อจัดจ้าง, admission รับสมัคร
            $t->string('title');
            $t->text('body')->nullable();
            $t->string('cover')->nullable();
            $t->json('images')->nullable();      // รูปกิจกรรมเพิ่มเติม
            $t->json('attachments')->nullable(); // [{path, name}] ไฟล์แนบ เช่น PDF ประกาศจัดซื้อ
            $t->boolean('pinned')->default(false);
            $t->timestamp('published_at')->nullable(); // ว่าง = ฉบับร่าง, อนาคต = ตั้งเวลาเผยแพร่
            $t->unsignedInteger('views')->default(0);
            $t->foreignId('announcement_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['category', 'published_at']);
        });

        Schema::create('web_slides', function (Blueprint $t) {
            $t->id();
            $t->string('image');
            $t->string('title')->nullable();
            $t->string('caption')->nullable();
            $t->string('url')->nullable();
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('web_pages', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('title');
            $t->text('body')->nullable();
            $t->string('cover')->nullable();
            $t->boolean('in_menu')->default(true);
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_published')->default(true);
            $t->timestamps();
        });

        Schema::create('web_links', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 20)->default('agency'); // agency หน่วยงานที่เกี่ยวข้อง, service บริการ
            $t->string('title');
            $t->string('url');
            $t->string('logo')->nullable();
            $t->string('icon', 40)->nullable();
            $t->unsignedInteger('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('web_messages', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('contact', 150); // อีเมลหรือเบอร์โทร
            $t->string('subject', 150)->nullable();
            $t->text('message');
            $t->string('ip', 45)->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });

        // ทำเนียบบุคลากรบนเว็บ: แสดงเฉพาะคนที่เลือกไว้ (PDPA)
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('public_profile')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('public_profile'));
        Schema::dropIfExists('web_messages');
        Schema::dropIfExists('web_links');
        Schema::dropIfExists('web_pages');
        Schema::dropIfExists('web_slides');
        Schema::dropIfExists('web_posts');
    }
};
