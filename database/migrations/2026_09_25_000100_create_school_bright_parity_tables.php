<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---- LINE ----
        Schema::table('users', function (Blueprint $table) {
            $table->string('line_user_id', 64)->nullable()->index();
            $table->string('line_link_code', 10)->nullable()->index();
            $table->timestamp('line_linked_at')->nullable();
        });

        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20)->default('line');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('text');
            $table->string('status', 20); // sent, skipped, failed
            $table->string('error')->nullable();
            $table->timestamps();
        });

        // ---- สแกน QR หน้าประตู ----
        Schema::table('students', function (Blueprint $table) {
            $table->string('qr_token', 32)->nullable()->unique();
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->time('checkout_at')->nullable();
            $table->string('source', 10)->default('manual'); // manual, gate, leave
        });

        // ---- GPS ----
        Schema::table('staff_attendances', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedInteger('distance_m')->nullable();
        });

        // ---- สลิปโอนเงิน ----
        Schema::create('payment_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('image');
            $table->dateTime('transferred_at')->nullable();
            $table->string('note')->nullable();
            $table->string('status', 10)->default('pending'); // pending, approved, rejected
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // ---- ห้องพยาบาล ----
        Schema::create('health_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->dateTime('visited_at');
            $table->string('symptom');
            $table->decimal('temperature', 4, 1)->nullable();
            $table->string('treatment')->nullable();
            $table->string('medicine')->nullable();
            $table->string('action', 20)->default('rest'); // rest, returned, sent_home, hospital
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('health_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('measured_on');
            $table->decimal('weight', 5, 1)->nullable(); // กก.
            $table->decimal('height', 5, 1)->nullable(); // ซม.
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ---- ห้องสมุด ----
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique(); // บาร์โค้ด/เลขทะเบียน
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('category', 60)->nullable();
            $table->string('publisher')->nullable();
            $table->unsignedSmallInteger('copies')->default(1);
            $table->string('location', 60)->nullable();
            $table->timestamps();
        });
        Schema::create('book_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('borrowed_on');
            $table->date('due_on');
            $table->date('returned_on')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['returned_on', 'due_on']);
        });

        // ---- รับสมัครนักเรียน ----
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('app_no', 20)->unique();
            $table->unsignedSmallInteger('year');
            $table->string('level', 20);
            $table->string('prefix', 20)->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('nickname', 50)->nullable();
            $table->string('gender', 1)->nullable();
            $table->date('birthdate')->nullable();
            $table->string('citizen_id', 13)->nullable();
            $table->string('previous_school')->nullable();
            $table->decimal('gpa', 3, 2)->nullable();
            $table->string('parent_name');
            $table->string('parent_phone', 20);
            $table->string('relation', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('document')->nullable();
            $table->text('note')->nullable();
            $table->string('status', 20)->default('submitted'); // submitted, reviewing, accepted, rejected, enrolled
            $table->string('staff_note')->nullable();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // ---- ปฏิทินโรงเรียน ----
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('type', 20)->default('activity'); // holiday, exam, activity, meeting
            $table->string('audience', 20)->default('all'); // all, staff
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['start_date', 'end_date']);
        });

        // ---- ลางานของครู/บุคลากร ----
        Schema::create('staff_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // sick, personal, vacation, duty, maternity
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason')->nullable();
            $table->string('attachment')->nullable();
            $table->string('status', 10)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['staff_leaves', 'events', 'applications', 'book_loans', 'books', 'health_measurements', 'health_visits', 'payment_slips', 'message_logs'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('staff_attendances', fn (Blueprint $t) => $t->dropColumn(['lat', 'lng', 'distance_m']));
        Schema::table('attendances', fn (Blueprint $t) => $t->dropColumn(['checkout_at', 'source']));
        Schema::table('students', function (Blueprint $t) {
            $t->dropUnique(['qr_token']);
            $t->dropColumn('qr_token');
        });
        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex(['line_user_id']);
            $t->dropIndex(['line_link_code']);
            $t->dropColumn(['line_user_id', 'line_link_code', 'line_linked_at']);
        });
    }
};
