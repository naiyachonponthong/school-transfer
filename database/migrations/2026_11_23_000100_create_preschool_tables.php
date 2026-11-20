<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ปฐมวัย (อ.1-อ.3): ผลประเมินพัฒนาการ 12 มาตรฐานรายภาคเรียน และบันทึกประจำวัน */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preschool_assessments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('term_id')->constrained()->cascadeOnDelete();
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->json('levels')->nullable();      // {"1":3,"2":2,...} มาตรฐานที่ 1-12 ระดับ 3/2/1
            $t->text('comment')->nullable();     // ความเห็นครูประจำชั้น
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['term_id', 'student_id']);
        });

        Schema::create('preschool_daily_logs', function (Blueprint $t) {
            $t->id();
            $t->date('date');
            $t->foreignId('student_id')->constrained()->cascadeOnDelete();
            $t->boolean('milk')->default(false);
            $t->boolean('meal')->default(false);
            $t->boolean('brush')->default(false);
            $t->boolean('nap')->default(false);
            $t->string('note')->nullable();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['date', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preschool_daily_logs');
        Schema::dropIfExists('preschool_assessments');
    }
};
