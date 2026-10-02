<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // เช็คชื่อหน้าเสาธง/โฮมรูม วันละ 1 ครั้งต่อคน
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('status', 10); // present, late, absent, leave, sick
            $table->string('note')->nullable();
            $table->time('checked_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['student_id', 'date']);
            $table->index(['classroom_id', 'date']);
            $table->index(['date', 'status']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 10); // sick, personal
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason')->nullable();
            $table->string('attachment')->nullable();
            $table->string('status', 10)->default('pending')->index(); // pending, approved, rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->timestamps();
        });

        Schema::create('behavior_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->smallInteger('points'); // + ความดี / - หักคะแนน
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('behavior_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('behavior_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->smallInteger('points');
            $table->text('note')->nullable();
            $table->date('date');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['student_id', 'date']);
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('audience', 20)->default('all'); // all, parents, staff, classroom
            $table->foreignId('classroom_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('pinned')->default(false);
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->primary(['announcement_id', 'user_id']);
        });

        // ลงเวลาปฏิบัติงานของครู/บุคลากร
        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->string('status', 10)->default('present'); // present, late, leave, duty
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        foreach (['staff_attendances', 'announcement_reads', 'announcements', 'behavior_records', 'behavior_rules', 'leave_requests', 'attendances'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
