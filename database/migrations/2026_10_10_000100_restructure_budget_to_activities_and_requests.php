<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ปรับงบประมาณให้ตรงกับระบบงานนโยบายและแผนของโรงเรียน:
 *   โครงการ → กิจกรรม → งบของกิจกรรมแยกประเภทเงิน
 *   คำขอใช้งบ (ซื้อ/จ้าง เบิกเงิน ยืมเงิน) → ตรวจเอกสาร → รองผู้อำนวยการ → ผู้อำนวยการ → เจ้าหน้าที่ตัดงบ (ตัดได้หลายประเภทเงินต่อใบ)
 * ตารางใบขอซื้อชุดแรก (งบผูกกับหมวดรายจ่าย) ยังไม่เคยใช้งานจริง จึงถูกแทนที่ทั้งชุด
 */
return new class extends Migration
{
    private const OLD_TABLES = ['purchase_approvals', 'purchase_request_items', 'purchase_requests', 'project_budgets'];

    /** key ของตำแหน่ง => [สิทธิ์ที่เพิ่ม, สิทธิ์ของชุดแรกที่เลิกใช้] */
    private const GRANTS = [
        'finance' => [['budget.manage', 'budget.review', 'budget.cut', 'budget.cut_special'], []],
        'clerk' => [['budget.review'], ['procurement.manage']],
        'executive' => [['budget.manage', 'budget.approve_vice', 'budget.approve'], []],
    ];

    public function up(): void
    {
        foreach (self::OLD_TABLES as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->string('track', 10)->default('general'); // general = กลุ่มทั่วไป, special = ห้องเรียนพิเศษ (กำหนดว่าเจ้าหน้าที่ตัดงบกลุ่มไหนตัด)
        });

        Schema::create('project_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('detail')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_source_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();
            $table->unique(['project_activity_id', 'budget_source_id']);
        });

        Schema::create('budget_requests', function (Blueprint $table) {
            $table->id();
            $table->string('req_no', 20)->unique();
            $table->foreignId('project_activity_id')->constrained()->restrictOnDelete();
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 10);                      // buy_hire = ขอซื้อ/จ้าง, disburse = ขอเบิกเงิน, loan = ขอยืมเงิน
            $table->string('title');
            $table->text('reason')->nullable();
            $table->string('method', 12)->nullable();        // วิธีจัดหา (เฉพาะขอซื้อ/จ้าง)
            $table->string('vendor')->nullable();
            $table->date('needed_on')->nullable();
            $table->decimal('total', 14, 2);
            $table->string('status', 10)->default('pending'); // pending, approved (ตัดงบแล้ว), rejected, cancelled
            $table->json('steps');                           // ลำดับขั้น ณ วันที่ยื่น ขั้นสุดท้ายคือ cut เสมอ
            $table->unsignedTinyInteger('step')->default(0);
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'step']);
        });

        Schema::create('budget_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_request_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->string('item_type', 10)->default('supply'); // supply, asset, service, other
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_price', 14, 2);
            $table->decimal('amount', 14, 2);
        });

        Schema::create('budget_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('step');
            $table->string('step_key', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('decision', 10);                  // approved, rejected
            $table->string('note')->nullable();
            $table->string('signature')->nullable();         // สำเนาลายเซ็นของผู้พิจารณา ณ ตอนที่เซ็น (เปลี่ยนลายเซ็นภายหลังเอกสารเดิมไม่เปลี่ยน)
            $table->timestamp('created_at')->nullable();
        });

        // เงินที่ตัดจริงของคำขอ แยกประเภทเงิน (ใบเดียวตัดได้หลายประเภทเงิน)
        Schema::create('budget_request_cuts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_source_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->unique(['budget_request_id', 'budget_source_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('signature')->nullable();         // ลายเซ็นที่เซ็นบนหน้าจอ (ไฟล์ในดิสก์ส่วนตัว)
        });

        foreach (self::GRANTS as $key => [$add, $remove]) {
            if ($role = DB::table('roles')->where('key', $key)->first()) {
                $permissions = array_values(array_unique(array_merge(array_diff(json_decode($role->permissions, true) ?: [], $remove), $add)));
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions)]);
            }
        }
        DB::table('settings')->where('key', 'purchase_steps')->delete();
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('signature'));
        foreach (['budget_request_cuts', 'budget_request_approvals', 'budget_request_items', 'budget_requests', 'activity_budgets', 'project_activities'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('track'));
        // ตารางชุดแรกไม่ถูกสร้างคืน (ถูกแทนที่ด้วยโครงสร้างนี้)
    }
};
