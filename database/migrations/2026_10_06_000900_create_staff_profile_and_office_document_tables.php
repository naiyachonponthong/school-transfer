<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ---------- ทะเบียนบุคลากร ---------- */
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('citizen_id', 13)->nullable();
            $table->date('birthdate')->nullable();
            $table->string('rank', 60)->nullable();          // วิทยฐานะ
            $table->date('hired_on')->nullable();
            $table->string('education')->nullable();
            $table->string('major')->nullable();
            $table->string('license_no', 40)->nullable();    // ใบอนุญาตประกอบวิชาชีพ
            $table->date('license_expires_on')->nullable();
            $table->date('license_reminded_on')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('organizer')->nullable();
            $table->date('date');
            $table->decimal('hours', 5, 1)->default(0);
            $table->string('file')->nullable();              // เกียรติบัตร (ดิสก์ส่วนตัว)
            $table->timestamps();
            $table->index(['user_id', 'date']);
        });

        /* ---------- สารบรรณ ---------- */
        Schema::create('office_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);                      // in รับ · out ส่ง · order คำสั่ง · memo บันทึกข้อความ
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('seq');
            $table->string('ref_no', 60)->nullable();        // เลขที่หนังสือของต้นทาง
            $table->date('doc_date');
            $table->string('subject');
            $table->string('party')->nullable();             // จาก (หนังสือรับ) / ถึง (หนังสือส่ง)
            $table->string('urgency', 10)->default('normal');
            $table->text('note')->nullable();
            $table->string('file')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['type', 'year', 'seq']);
        });

        Schema::create('office_document_user', function (Blueprint $table) {
            $table->foreignId('office_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->primary(['office_document_id', 'user_id']);
        });

        // สิทธิ์ใหม่ให้ตำแหน่ง "ธุรการ" ที่มีอยู่แล้ว (ติดตั้งใหม่ได้จากค่าตั้งต้น)
        foreach (DB::table('roles')->where('key', 'clerk')->get() as $role) {
            $permissions = array_values(array_unique([...(json_decode($role->permissions, true) ?: []), 'office.manage']));
            DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions)]);
        }
    }

    public function down(): void
    {
        foreach (['office_document_user', 'office_documents', 'staff_trainings', 'staff_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
