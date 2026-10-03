<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->date('visited_on');
            $table->foreignId('visitor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guardian_met')->nullable();
            $table->string('housing', 20)->nullable();
            $table->string('family_status', 20)->nullable();
            $table->json('risks')->nullable();          // ด้านที่พบความเสี่ยง
            $table->text('note')->nullable();
            $table->string('photo')->nullable();        // ดิสก์ส่วนตัว
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'term_id']);
        });

        Schema::create('care_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);             // learning, behavior, health, economic, safety, family
            $table->string('level', 10)->default('risk'); // risk เสี่ยง · problem มีปัญหา
            $table->string('title');
            $table->text('detail')->nullable();
            $table->string('status', 15)->default('open')->index(); // open, monitoring, referred, closed
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('care_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_case_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->text('action');
            $table->text('result')->nullable();
            $table->date('follow_up_on')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // หนังสือขออนุญาตผู้ปกครอง (ทัศนศึกษา กิจกรรมนอกสถานที่ ฯลฯ)
        Schema::create('consent_forms', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->json('classroom_ids');
            $table->date('due_date')->nullable();
            $table->boolean('is_open')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('consent_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consent_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // ผู้ปกครองที่ตอบ (หรือครูที่บันทึกแทน)
            $table->boolean('agreed');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['consent_form_id', 'student_id']);
        });

        // สิทธิ์ใหม่ "ดูแลช่วยเหลือนักเรียนทุกห้อง" ให้ตำแหน่งผู้บริหารที่มีอยู่แล้ว (ติดตั้งใหม่ได้จากค่าตั้งต้นอยู่แล้ว)
        foreach (DB::table('roles')->where('key', 'executive')->get() as $role) {
            $permissions = json_decode($role->permissions, true) ?: [];
            if (! in_array('care.manage', $permissions, true)) {
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode([...$permissions, 'care.manage'])]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_responses');
        Schema::dropIfExists('consent_forms');
        Schema::dropIfExists('care_actions');
        Schema::dropIfExists('care_cases');
        Schema::dropIfExists('home_visits');
    }
};
