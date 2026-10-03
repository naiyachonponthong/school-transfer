<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // สอนแทน: คาบในตารางสอนของวันใดวันหนึ่งที่ให้ครูอีกคนเข้าสอน
        Schema::create('substitutions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('timetable_slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('period');
            $table->foreignId('absent_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('substitute_id')->constrained('users')->cascadeOnDelete();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['date', 'timetable_slot_id']);
            $table->index(['substitute_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('substitutions');
    }
};
