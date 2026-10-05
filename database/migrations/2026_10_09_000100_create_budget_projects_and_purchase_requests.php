<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * งบประมาณและโครงการ (เฟส 1): แหล่งเงินของปีงบประมาณ → โครงการ → งบของโครงการแยกแหล่งเงินและหมวดรายจ่าย
 * → ใบขอซื้อ/ขอจ้างที่ตัดงบของโครงการ และการอนุมัติตามลำดับขั้น
 */
return new class extends Migration
{
    private const GRANTS = [
        'finance' => ['budget.manage'],
        'clerk' => ['procurement.manage'],
        'executive' => ['budget.manage', 'budget.approve'],
    ];

    public function up(): void
    {
        Schema::create('budget_sources', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year');     // ปีงบประมาณ พ.ศ. (ต.ค.–ก.ย.)
            $table->string('name');                          // เช่น เงินอุดหนุนรายหัว
            $table->decimal('amount', 14, 2)->default(0);    // วงเงินที่ได้รับ
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index('fiscal_year');
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('fiscal_year');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete(); // ผู้รับผิดชอบโครงการ
            $table->text('objective')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 10)->default('active'); // active, closed
            $table->text('summary')->nullable();             // สรุปผลเมื่อปิดโครงการ
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('fiscal_year');
        });

        Schema::create('project_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_source_id')->constrained()->restrictOnDelete();
            $table->string('category', 20);                  // compensation, service, supplies, equipment, other
            $table->decimal('amount', 14, 2);
            $table->timestamps();
            $table->unique(['project_id', 'budget_source_id', 'category']);
        });

        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('req_no', 20)->unique();
            $table->foreignId('project_budget_id')->constrained()->restrictOnDelete(); // บรรทัดงบที่ใช้เงิน
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 10);                      // buy = ขอซื้อ, hire = ขอจ้าง
            $table->string('title');
            $table->text('reason')->nullable();
            $table->string('method', 12)->default('specific'); // วิธีจัดหา: specific, selection, ebidding, other
            $table->string('vendor')->nullable();            // ผู้ขาย/ผู้รับจ้างที่เสนอ
            $table->date('needed_on')->nullable();
            $table->decimal('total', 14, 2);
            $table->string('status', 10)->default('pending'); // pending, approved, rejected, cancelled
            $table->json('steps');                           // ลำดับขั้นอนุมัติ ณ วันที่ยื่น (เปลี่ยนการตั้งค่าภายหลังไม่กระทบใบที่ยื่นแล้ว)
            $table->unsignedTinyInteger('step')->default(0); // ขั้นที่รออยู่ (ลำดับใน steps)
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'step']);
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->string('item_type', 10)->default('supply'); // supply = วัสดุ, asset = ครุภัณฑ์, service = จ้าง/บริการ
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_price', 14, 2);
            $table->decimal('amount', 14, 2);
        });

        Schema::create('purchase_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('step');
            $table->string('step_key', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('decision', 10);                  // approved, rejected
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        foreach (self::GRANTS as $key => $add) {
            if ($role = DB::table('roles')->where('key', $key)->first()) {
                $permissions = array_values(array_unique(array_merge(json_decode($role->permissions, true) ?: [], $add)));
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions)]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::GRANTS as $key => $remove) {
            if ($role = DB::table('roles')->where('key', $key)->first()) {
                DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values(array_diff(json_decode($role->permissions, true) ?: [], $remove)))]);
            }
        }
        foreach (['purchase_approvals', 'purchase_request_items', 'purchase_requests', 'project_budgets', 'projects', 'budget_sources'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
