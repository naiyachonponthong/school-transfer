<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ครูและบุคลากรสแกนที่เครื่องเดียวกับนักเรียนได้ (รหัสในเครื่อง = ชื่อผู้ใช้) ลงเป็นเวลาทำงาน
        Schema::table('gate_events', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('student_id')->constrained()->nullOnDelete();
        });
        Schema::table('staff_attendances', function (Blueprint $table) {
            $table->string('source', 10)->nullable()->after('note'); // gate = สแกนที่ประตู, ว่าง = ลงเวลาเอง
        });
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('face_consent_at')->nullable(); // ผู้ดูแลบันทึกว่าบุคลากรยินยอมให้ใช้ใบหน้าแล้ว
        });
    }

    public function down(): void
    {
        Schema::table('gate_events', fn (Blueprint $table) => $table->dropConstrainedForeignId('user_id'));
        Schema::table('staff_attendances', fn (Blueprint $table) => $table->dropColumn('source'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('face_consent_at'));
    }
};
