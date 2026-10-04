<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // หน่วยงานของโรงเรียน (ผู้บริหาร → ฝ่าย → กลุ่มสาระ/งาน) ซ้อนกันได้หลายชั้น
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->string('kind', 12)->default('unit'); // executive, division, group, unit
            $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('head_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // ครูหนึ่งคนสังกัดได้หลายหน่วย (เช่น กลุ่มสาระ + งานของฝ่าย) มีหน่วยหลักหนึ่งหน่วย
        Schema::create('department_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['department_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('staff_code', 20)->nullable()->unique(); // รหัสบุคลากร (ใช้เป็นรหัสบุคคลในเครื่องสแกนได้)
            $table->boolean('hide_phone')->default(false);          // ไม่แสดงเบอร์โทรในทะเบียนติดต่อ
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['staff_code']);
            $table->dropColumn(['staff_code', 'hide_phone']);
        });
        Schema::dropIfExists('department_user');
        Schema::dropIfExists('departments');
    }
};
