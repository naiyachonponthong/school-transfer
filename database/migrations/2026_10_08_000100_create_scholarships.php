<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ทะเบียนทุนการศึกษา: ทุน → ผู้ถูกเสนอชื่อ → พิจารณา → มอบ (ลดค่าธรรมเนียมผ่านส่วนลดประจำตัวเดิม หรือจ่ายเป็นเงิน/สิ่งของ)
 */
return new class extends Migration
{
    private const ROLES = ['finance', 'executive'];

    public function up(): void
    {
        Schema::create('scholarships', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 20);                 // need, merit, conduct, talent, other
            $table->string('donor')->nullable();            // ผู้ให้ทุน
            $table->unsignedSmallInteger('year');           // ปีการศึกษา (พ.ศ.)
            $table->string('mode', 10);                     // discount = ลดค่าธรรมเนียม, cash = จ่ายเป็นเงิน/สิ่งของ
            $table->string('value_type', 10)->default('amount'); // amount = บาท, percent = ร้อยละ (เฉพาะทุนลดค่าธรรมเนียม)
            $table->decimal('value', 12, 2);                // มูลค่าต่อทุน
            $table->foreignId('fee_item_id')->nullable()->constrained()->nullOnDelete(); // ลดเฉพาะรายการนี้ (ว่าง = จากยอดรวม)
            $table->unsignedInteger('slots')->nullable();   // จำนวนทุน (ว่าง = ไม่จำกัด)
            $table->decimal('budget', 12, 2)->nullable();   // งบรวม (ว่าง = ไม่จำกัด)
            $table->text('conditions')->nullable();
            $table->date('opens_on')->nullable();
            $table->date('closes_on')->nullable();
            $table->boolean('is_open')->default(true);      // เปิดรับการเสนอชื่อ
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('year');
        });

        Schema::create('scholarship_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scholarship_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->string('status', 12)->default('nominated'); // nominated, approved, reserve, rejected, revoked
            $table->text('reason')->nullable();             // เหตุผล/คุณสมบัติที่ครูเสนอ
            $table->foreignId('nominated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->decimal('amount', 12, 2)->nullable();   // มูลค่าเป็นบาทที่อนุมัติ (ทุนลดเป็นร้อยละ = ว่าง)
            $table->string('doc_no', 20)->nullable()->unique(); // เลขที่ใบสำคัญรับเงิน (ทุนที่จ่ายเป็นเงิน)
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('received_by')->nullable();      // ชื่อผู้รับเงิน/สิ่งของ
            $table->timestamps();
            $table->unique(['scholarship_id', 'student_id']);
        });

        // ส่วนลดประจำตัวที่ระบบสร้างให้จากทุน (เพิกถอนทุน = ปิดส่วนลด)
        Schema::table('student_discounts', function (Blueprint $table) {
            $table->foreignId('scholarship_award_id')->nullable()->constrained()->nullOnDelete();
        });

        foreach (DB::table('roles')->whereIn('key', self::ROLES)->get() as $role) {
            $permissions = array_values(array_unique(array_merge(json_decode($role->permissions, true) ?: [], ['scholarships.manage'])));
            DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions)]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('roles')->whereIn('key', self::ROLES)->get() as $role) {
            $permissions = array_values(array_diff(json_decode($role->permissions, true) ?: [], ['scholarships.manage']));
            DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions)]);
        }
        Schema::table('student_discounts', fn (Blueprint $table) => $table->dropConstrainedForeignId('scholarship_award_id'));
        Schema::dropIfExists('scholarship_awards');
        Schema::dropIfExists('scholarships');
    }
};
