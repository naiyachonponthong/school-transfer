<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- แชทครู ↔ ผู้ปกครอง ----
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete(); // เรื่องของนักเรียนคนไหน
            $table->string('topic')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('conversation_user', function (Blueprint $table) {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->primary(['conversation_id', 'user_id']);
        });
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body')->nullable();
            $table->string('attachment')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });

        // ---- การบ้าน ----
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->nullable()->constrained()->nullOnDelete(); // ส่งคะแนนเข้าช่องคะแนนไหน
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('attachment')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->decimal('max_score', 6, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->text('text')->nullable();
            $table->string('file')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('channel', 10)->default('online'); // online, paper
            $table->decimal('score', 6, 2)->nullable();
            $table->string('feedback')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['assignment_id', 'student_id']);
        });

        // ---- แบบประเมิน (สร้างเองได้) ----
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('respondent', 10)->default('teacher'); // teacher, parent, both
            $table->json('scale');      // [{label, value}]
            $table->json('subscales');  // [{key, name, in_total, higher_is_better, bands:[{max,label,color}]}]
            $table->json('total_bands')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('survey_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort');
            $table->text('text');
            $table->string('subscale', 30)->nullable();
            $table->boolean('reverse')->default(false);
        });
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('respondent_role', 10);
            $table->json('answers');
            $table->json('scores');
            $table->timestamps();
            $table->unique(['survey_id', 'student_id', 'term_id', 'respondent_role'], 'survey_resp_unique');
        });

        // ---- แฟ้มสะสมผลงาน ----
        Schema::create('student_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20); // award, work, activity, volunteer
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('level', 20)->nullable(); // school, district, province, region, national, international
            $table->date('date')->nullable();
            $table->decimal('hours', 5, 1)->nullable(); // ชั่วโมงจิตอาสา/กิจกรรม
            $table->string('image')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('verified')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['student_works', 'survey_responses', 'survey_items', 'surveys', 'submissions', 'assignments', 'messages', 'conversation_user', 'conversations'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
