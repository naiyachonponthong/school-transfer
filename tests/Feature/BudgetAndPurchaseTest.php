<?php

namespace Tests\Feature;

use App\Models\BudgetSource;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\CodeSeries;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** งบประมาณและโครงการ: แหล่งเงิน งบของโครงการ ใบขอซื้อ/ขอจ้าง การกันงบ และการอนุมัติตามลำดับขั้น */
class BudgetAndPurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    private function withRole(string $username, string $role): User
    {
        $user = User::where('username', $username)->first();
        $user->roles()->sync([Role::where('key', $role)->first()->id]);

        return $user->fresh();
    }

    /** แหล่งเงิน 100,000 → โครงการของครู t5 → งบค่าวัสดุ 10,000 */
    private function setUpProject(): array
    {
        $fy = CodeSeries::fiscalYear(now());
        $owner = User::where('username', 't5')->first();
        $this->actingAs($this->admin())->post('/budget/sources', ['fiscal_year' => $fy, 'name' => 'เงินอุดหนุนรายหัว', 'amount' => 100000])->assertSessionHas('success');
        $source = BudgetSource::sole();
        $this->actingAs($this->admin())->post('/projects', ['name' => 'ห้องปฏิบัติการวิทยาศาสตร์', 'fiscal_year' => $fy, 'owner_id' => $owner->id])->assertRedirect();
        $project = Project::sole();
        $this->actingAs($this->admin())->post("/projects/{$project->id}/lines", ['budget_source_id' => $source->id, 'category' => 'supplies', 'amount' => 10000])->assertSessionHas('success');

        return [$source, $project, ProjectBudget::sole(), $owner];
    }

    private function request(User $by, ProjectBudget $line, float $price, array $extra = [])
    {
        return $this->actingAs($by)->post('/purchases', $extra + [
            'project_budget_id' => $line->id, 'kind' => 'buy', 'title' => 'ขอซื้อวัสดุ', 'method' => 'specific',
            'items' => [['description' => 'บีกเกอร์ 250 มล.', 'item_type' => 'supply', 'quantity' => 2, 'unit' => 'ใบ', 'unit_price' => $price]],
        ]);
    }

    public function test_budget_cannot_be_over_allocated_and_permissions_hold(): void
    {
        [$source, $project, $line, $owner] = $this->setUpProject();
        $teacher = User::where('username', 'teacher')->first();
        $this->assertMatchesRegularExpression('/^P\d{2}-001$/', $project->code);

        // ครูทั่วไปเข้าหน้างบประมาณหรือตั้งโครงการไม่ได้ และเห็นเฉพาะโครงการที่ตัวเองรับผิดชอบ
        $this->actingAs($teacher)->get('/budget')->assertForbidden();
        $this->actingAs($teacher)->post('/projects', ['name' => 'x'])->assertForbidden();
        $this->actingAs($teacher)->get('/projects')->assertOk()->assertDontSee('ห้องปฏิบัติการวิทยาศาสตร์');
        $this->actingAs($teacher)->get("/projects/{$project->id}")->assertForbidden();
        $this->actingAs($owner)->get('/projects')->assertOk()->assertSee('ห้องปฏิบัติการวิทยาศาสตร์');
        $this->actingAs($owner)->get("/projects/{$project->id}")->assertOk()->assertSee('ค่าวัสดุ')->assertDontSee('เพิ่ม/แก้งบ');

        // จัดสรรเกินวงเงินของแหล่งเงินไม่ได้ · แก้ยอดบรรทัดเดิมได้
        $this->actingAs($this->admin())->post("/projects/{$project->id}/lines", ['budget_source_id' => $source->id, 'category' => 'equipment', 'amount' => 95000])->assertSessionHas('warning');
        $this->actingAs($this->admin())->post("/projects/{$project->id}/lines", ['budget_source_id' => $source->id, 'category' => 'equipment', 'amount' => 90000])->assertSessionHas('success');
        $this->actingAs($this->admin())->post("/projects/{$project->id}/lines", ['budget_source_id' => $source->id, 'category' => 'supplies', 'amount' => 12000])->assertSessionHas('warning');
        $this->assertSame('10000.00', $line->fresh()->amount);
        $this->actingAs($this->admin())->put("/budget/sources/{$source->id}", ['fiscal_year' => $source->fiscal_year, 'name' => $source->name, 'amount' => 50000])->assertSessionHas('warning');
        $this->actingAs($this->admin())->delete("/budget/sources/{$source->id}")->assertSessionHas('warning');

        $this->actingAs($this->admin())->get('/budget')->assertOk()->assertSee('เงินอุดหนุนรายหัว')->assertSee('100,000.00');
    }

    public function test_request_reserves_budget_and_moves_through_every_approval_step(): void
    {
        [, $project, $line, $owner] = $this->setUpProject();
        $teacher = User::where('username', 'teacher')->first();
        $clerk = $this->withRole('t3', 'clerk');
        $finance = $this->withRole('t4', 'finance');

        $this->actingAs($teacher)->get('/purchases/create')->assertOk()->assertSee('ห้องปฏิบัติการวิทยาศาสตร์')->assertSee('ผู้รับผิดชอบโครงการ → เจ้าหน้าที่พัสดุ → เจ้าหน้าที่การเงิน → ผู้อำนวยการ');
        // เกินงบคงเหลือยื่นไม่ได้
        $this->request($teacher, $line, 6000)->assertSessionHas('warning');
        $this->assertSame(0, PurchaseRequest::count());

        $this->request($teacher, $line, 3000)->assertRedirect()->assertSessionHas('success');
        $purchase = PurchaseRequest::sole();
        $this->assertSame(['6000.00', 'pending', 0, ['owner', 'procurement', 'finance', 'director']], [$purchase->total, $purchase->status, $purchase->step, $purchase->steps]);
        $this->assertStringStartsWith('PR'.CodeSeries::fiscalYear(now()), $purchase->req_no);
        // เงินถูกกันไว้ตั้งแต่ยื่น ใบถัดไปจึงขอได้ไม่เกินที่เหลือ
        $this->assertSame(4000.0, $line->fresh()->available());
        $this->request($teacher, $line, 2500)->assertSessionHas('warning');

        // คนที่ไม่ใช่ผู้พิจารณาของขั้นนั้นตัดสินไม่ได้ และคนนอกเปิดดูไม่ได้
        $this->actingAs($clerk)->post("/purchases/{$purchase->id}/decide", ['decision' => 'approved'])->assertForbidden();
        $this->actingAs(User::where('username', 't6')->first())->get("/purchases/{$purchase->id}")->assertForbidden();
        $this->actingAs($owner)->get('/purchases')->assertOk()->assertSee('รอคุณพิจารณา');
        $this->actingAs($owner)->get("/purchases/{$purchase->id}")->assertOk()->assertSee('พิจารณาในขั้นผู้รับผิดชอบโครงการ')->assertSee('บีกเกอร์ 250 มล.')->assertSee('หกพันบาทถ้วน');

        $this->actingAs($owner)->post("/purchases/{$purchase->id}/decide", ['decision' => 'approved'])->assertSessionHas('success');
        $this->actingAs($owner)->post("/purchases/{$purchase->id}/decide", ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($clerk)->post("/purchases/{$purchase->id}/decide", ['decision' => 'approved', 'note' => 'ตรวจราคาแล้ว'])->assertSessionHas('success');
        $this->actingAs($finance)->post("/purchases/{$purchase->id}/decide", ['decision' => 'approved'])->assertSessionHas('success');
        $this->assertSame(['pending', 3], [$purchase->fresh()->status, $purchase->fresh()->step]);
        $this->assertSame(0.0, $line->fresh()->committed());

        // ขั้นสุดท้าย: ผูกพันงบ
        $this->actingAs($this->admin())->post("/purchases/{$purchase->id}/decide", ['decision' => 'approved'])->assertSessionHas('success');
        $purchase->refresh();
        $this->assertSame(['approved', 4], [$purchase->status, $purchase->approvals()->count()]);
        $this->assertSame([6000.0, 0.0, 4000.0], [$line->fresh()->committed(), $line->fresh()->pending(), $line->fresh()->available()]);
        $this->actingAs($teacher)->get("/purchases/{$purchase->id}")->assertOk()->assertSee('ผูกพันงบแล้ว')->assertDontSee('ยกเลิกใบนี้');
        $this->actingAs($this->admin())->get("/projects/{$project->id}")->assertOk()->assertSee($purchase->req_no);

        // ลดงบต่ำกว่ายอดผูกพันไม่ได้ และบรรทัดที่มีใบแล้วลบไม่ได้
        $this->actingAs($this->admin())->post("/projects/{$project->id}/lines", ['budget_source_id' => $line->budget_source_id, 'category' => 'supplies', 'amount' => 5000])->assertSessionHas('warning');
        $this->actingAs($this->admin())->delete("/project-lines/{$line->id}")->assertSessionHas('warning');
    }

    public function test_rejecting_or_cancelling_releases_the_reserved_budget(): void
    {
        [, $project, $line, $owner] = $this->setUpProject();
        $teacher = User::where('username', 'teacher')->first();

        $this->request($teacher, $line, 2000);
        $first = PurchaseRequest::latest('id')->first();
        $this->actingAs($owner)->post("/purchases/{$first->id}/decide", ['decision' => 'rejected'])->assertSessionHasErrors('note');
        $this->actingAs($owner)->post("/purchases/{$first->id}/decide", ['decision' => 'rejected', 'note' => 'ยังมีของเดิมใช้ได้'])->assertSessionHas('success');
        $this->assertSame('rejected', $first->fresh()->status);
        $this->assertSame(10000.0, $line->fresh()->available());

        $this->request($teacher, $line, 1000);
        $second = PurchaseRequest::latest('id')->first();
        $this->actingAs(User::where('username', 't6')->first())->post("/purchases/{$second->id}/cancel")->assertForbidden();
        $this->actingAs($teacher)->post("/purchases/{$second->id}/cancel")->assertSessionHas('success');
        $this->assertSame(10000.0, $line->fresh()->available());
        $this->actingAs($owner)->post("/purchases/{$second->id}/decide", ['decision' => 'approved'])->assertForbidden();

        // ปิดโครงการ: ต้องไม่มีใบรออนุมัติ และปิดแล้วขอเพิ่มไม่ได้
        $this->request($teacher, $line, 500);
        $third = PurchaseRequest::latest('id')->first();
        $this->actingAs($this->admin())->post("/projects/{$project->id}/close", ['summary' => 'เสร็จตามแผน'])->assertSessionHas('warning');
        $this->actingAs($teacher)->post("/purchases/{$third->id}/cancel");
        $this->actingAs($this->admin())->post("/projects/{$project->id}/close", [])->assertSessionHasErrors('summary');
        $this->actingAs($this->admin())->post("/projects/{$project->id}/close", ['summary' => 'เสร็จตามแผน'])->assertSessionHas('success');
        $this->request($teacher, $line, 100)->assertSessionHas('warning');
        $this->actingAs($this->admin())->post("/projects/{$project->id}/close")->assertSessionHas('success');
        $this->assertSame('active', $project->fresh()->status);
    }

    public function test_approval_steps_are_configurable_and_owner_skips_own_step(): void
    {
        [, , $line, $owner] = $this->setUpProject();

        // ผู้รับผิดชอบโครงการขอเอง: ไม่มีขั้นของตัวเอง
        $this->request($owner, $line, 100);
        $this->assertSame(['procurement', 'finance', 'director'], PurchaseRequest::latest('id')->first()->steps);

        $this->actingAs($this->admin())->post('/budget/steps', ['steps' => []])->assertSessionHasErrors('steps');
        $this->actingAs($this->admin())->post('/budget/steps', ['steps' => ['director', 'owner']])->assertSessionHas('success');
        $this->assertSame('owner,director', Settings::get('purchase_steps'));

        // ใบเดิมใช้ลำดับขั้น ณ วันที่ยื่น ใบใหม่ใช้ลำดับใหม่
        $this->assertCount(3, PurchaseRequest::first()->steps);
        $teacher = User::where('username', 'teacher')->first();
        $this->request($teacher, $line, 100);
        $new = PurchaseRequest::latest('id')->first();
        $this->assertSame(['owner', 'director'], $new->steps);
        $this->actingAs($owner)->post("/purchases/{$new->id}/decide", ['decision' => 'approved']);
        $this->actingAs($this->admin())->post("/purchases/{$new->id}/decide", ['decision' => 'approved']);
        $this->assertSame('approved', $new->fresh()->status);

        $this->actingAs($this->admin())->get('/menu')->assertOk()->assertSee('งบประมาณ')->assertSee('ขอซื้อ/ขอจ้าง');
        $this->actingAs($teacher)->get('/menu')->assertOk()->assertSee('ขอซื้อ/ขอจ้าง')->assertSee('โครงการ');
    }
}
