<?php

namespace Tests\Feature;

use App\Models\BudgetRequest;
use App\Models\BudgetSource;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Role;
use App\Models\User;
use App\Support\CodeSeries;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** งบประมาณ: ประเภทเงิน โครงการ กิจกรรม คำขอใช้งบ การกันงบ การพิจารณาตามลำดับขั้น การตัดงบหลายประเภทเงิน และลายเซ็น */
class BudgetRequestTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /** PNG 1×1 จุด ใช้แทนลายเซ็นที่วาดบนหน้าจอ */
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    private function user(string $username, ?string $permissionRole = null, bool $signed = true): User
    {
        $user = User::where('username', $username)->first();
        if ($permissionRole) {
            $user->roles()->sync([Role::firstOrCreate(['key' => 'test-'.$permissionRole], ['name' => 'ทดสอบ '.$permissionRole, 'permissions' => [$permissionRole]])->id]);
        }
        if ($signed) {
            $this->actingAs($user->fresh())->post('/profile/signature', ['signature' => self::PNG])->assertSessionHas('success');
        }

        return $user->fresh();
    }

    /** อุดหนุน 100,000 + รายได้ 20,000 → โครงการของครู t5 → กิจกรรมงบ อุดหนุน 8,000 + รายได้ 2,000 */
    private function setUpActivity(string $track = 'general'): array
    {
        $fy = CodeSeries::fiscalYear(now());
        $admin = $this->admin();
        $this->actingAs($admin)->post('/budget/sources/standard', ['fiscal_year' => $fy])->assertSessionHas('success');
        $subsidy = BudgetSource::where('name', 'เงินอุดหนุน')->first();
        $income = BudgetSource::where('name', 'เงินรายได้สถานศึกษา')->first();
        $this->actingAs($admin)->put("/budget/sources/{$subsidy->id}", ['fiscal_year' => $fy, 'name' => $subsidy->name, 'amount' => 100000])->assertSessionHas('success');
        $this->actingAs($admin)->put("/budget/sources/{$income->id}", ['fiscal_year' => $fy, 'name' => $income->name, 'amount' => 20000])->assertSessionHas('success');
        $owner = User::where('username', 't5')->first();
        $this->actingAs($admin)->post('/projects', ['name' => 'ห้องปฏิบัติการวิทยาศาสตร์', 'fiscal_year' => $fy, 'track' => $track, 'owner_id' => $owner->id])->assertRedirect();
        $project = Project::latest('id')->first();
        $this->actingAs($admin)->post("/projects/{$project->id}/activities", ['name' => 'จัดซื้อวัสดุทดลอง', 'budgets' => [$subsidy->id => 8000, $income->id => 2000]])->assertSessionHas('success');

        return [$project, ProjectActivity::latest('id')->first(), $subsidy, $income, $owner];
    }

    private function submit(User $by, ProjectActivity $activity, float $price, array $extra = [])
    {
        return $this->actingAs($by)->post('/budget-requests', $extra + [
            'project_activity_id' => $activity->id, 'type' => 'buy_hire', 'title' => 'ขอซื้อวัสดุ', 'method' => 'specific',
            'items' => [['description' => 'บีกเกอร์ 250 มล.', 'item_type' => 'supply', 'quantity' => 2, 'unit' => 'ใบ', 'unit_price' => $price]],
        ]);
    }

    public function test_sources_projects_and_activity_budgets_respect_limits_and_permissions(): void
    {
        [$project, $activity, $subsidy, $income, $owner] = $this->setUpActivity();
        $teacher = User::where('username', 'teacher')->first();
        $this->assertSame(4, BudgetSource::count());
        $this->assertSame(10000.0, $activity->total());

        // ครูทั่วไปเข้าหน้างบประมาณหรือตั้งโครงการไม่ได้ และเห็นเฉพาะโครงการที่ตัวเองรับผิดชอบ
        $this->actingAs($teacher)->get('/budget')->assertForbidden();
        $this->actingAs($teacher)->post('/projects', ['name' => 'x'])->assertForbidden();
        $this->actingAs($teacher)->get('/projects')->assertOk()->assertDontSee('ห้องปฏิบัติการวิทยาศาสตร์');
        $this->actingAs($teacher)->get("/projects/{$project->id}")->assertForbidden();
        $this->actingAs($owner)->get("/projects/{$project->id}")->assertOk()->assertSee('จัดซื้อวัสดุทดลอง')->assertSee('เงินอุดหนุน 8,000.00')->assertDontSee('เพิ่มกิจกรรม');

        // จัดสรรเกินวงเงินของประเภทเงินไม่ได้ (รายได้มี 20,000 ใช้ไป 2,000)
        $this->actingAs($this->admin())->post("/projects/{$project->id}/activities", ['name' => 'ซ่อมตู้ดูดควัน', 'budgets' => [$income->id => 19000]])->assertSessionHas('warning');
        $this->assertSame(1, $project->activities()->count());
        $this->actingAs($this->admin())->post("/projects/{$project->id}/activities", ['name' => 'ซ่อมตู้ดูดควัน', 'budgets' => [$income->id => 18000]])->assertSessionHas('success');
        $this->actingAs($this->admin())->put("/budget/sources/{$income->id}", ['fiscal_year' => $income->fiscal_year, 'name' => $income->name, 'amount' => 5000])->assertSessionHas('warning');
        $this->actingAs($this->admin())->delete("/budget/sources/{$income->id}")->assertSessionHas('warning');

        // แก้งบกิจกรรม: 0 = เลิกใช้เงินประเภทนั้น
        $this->actingAs($this->admin())->put("/projects/{$project->id}/activities/{$activity->id}", ['name' => 'จัดซื้อวัสดุทดลอง', 'budgets' => [$subsidy->id => 9000, $income->id => 0]])->assertSessionHas('success');
        $this->assertSame([9000.0, 1], [$activity->fresh()->total(), $activity->budgets()->count()]);

        $this->actingAs($this->admin())->get('/budget')->assertOk()->assertSee('เงินอุดหนุน')->assertSee('100,000.00')->assertSee('เจ้าหน้าที่ตัดงบ');
        $this->actingAs($this->admin())->get('/projects')->assertOk()->assertSee('ห้องปฏิบัติการวิทยาศาสตร์');
    }

    public function test_request_reserves_budget_goes_through_each_step_and_is_cut_across_sources(): void
    {
        [$project, $activity, $subsidy, $income] = $this->setUpActivity();
        $teacher = User::where('username', 'teacher')->first();
        $checker = $this->user('t3', 'budget.review');
        $vice = $this->user('t4', 'budget.approve_vice');
        $director = $this->user('t6', 'budget.approve');
        $cutter = $this->user('t7', 'budget.cut');

        $this->actingAs($teacher)->get('/budget-requests/create')->assertOk()->assertSee('จัดซื้อวัสดุทดลอง')->assertSee('ผู้ตรวจสอบเอกสาร → รองผู้อำนวยการ → ผู้อำนวยการ → เจ้าหน้าที่ตัดงบ');
        // เกินงบคงเหลือของกิจกรรมยื่นไม่ได้
        $this->submit($teacher, $activity, 6000)->assertSessionHas('warning');
        $this->assertSame(0, BudgetRequest::count());

        $this->submit($teacher, $activity, 4500)->assertRedirect()->assertSessionHas('success');
        $r = BudgetRequest::sole();
        $this->assertSame(['9000.00', 'pending', 0, ['review', 'vice', 'director', 'cut']], [$r->total, $r->status, $r->step, $r->steps]);
        $this->assertStringStartsWith('BR'.CodeSeries::fiscalYear(now()), $r->req_no);
        // เงินถูกกันไว้ตั้งแต่ยื่น
        $this->assertSame(1000.0, $activity->fresh()->available());
        $this->submit($teacher, $activity, 600)->assertSessionHas('warning');

        // คนที่ไม่ใช่ผู้พิจารณาของขั้นนั้นตัดสินไม่ได้ และคนนอกเปิดดูไม่ได้
        $this->actingAs($vice)->post("/budget-requests/{$r->id}/decide", ['decision' => 'approved'])->assertForbidden();
        $this->actingAs(User::where('username', 't8')->first())->get("/budget-requests/{$r->id}")->assertForbidden();
        $this->actingAs($checker)->get('/budget-requests')->assertOk()->assertSee('รอคุณพิจารณา');
        $this->actingAs($checker)->get("/budget-requests/{$r->id}")->assertOk()->assertSee('พิจารณาในขั้นผู้ตรวจสอบเอกสาร')->assertSee('บีกเกอร์ 250 มล.')->assertSee('เก้าพันบาทถ้วน');

        $this->actingAs($checker)->post("/budget-requests/{$r->id}/decide", ['decision' => 'approved', 'note' => 'เอกสารครบ'])->assertSessionHas('success');
        $this->actingAs($checker)->post("/budget-requests/{$r->id}/decide", ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($vice)->post("/budget-requests/{$r->id}/decide", ['decision' => 'approved'])->assertSessionHas('success');
        $this->actingAs($director)->post("/budget-requests/{$r->id}/decide", ['decision' => 'approved'])->assertSessionHas('success');
        $this->assertSame(['pending', 3, 'เจ้าหน้าที่ตัดงบ'], [$r->fresh()->status, $r->fresh()->step, $r->fresh()->currentStepLabel()]);
        $this->assertSame(0.0, $activity->fresh()->spent());

        // ตัดงบ: รวมต้องเท่ายอดคำขอ และไม่เกินงบคงเหลือของแต่ละประเภทเงิน
        $this->actingAs($cutter)->get("/budget-requests/{$r->id}")->assertOk()->assertSee('ตัดงบและลงนาม')->assertSee('เหลือ 8,000.00');
        $this->actingAs($cutter)->post("/budget-requests/{$r->id}/decide", ['decision' => 'approved', 'cuts' => [$subsidy->id => 8000]])->assertSessionHas('warning');
        $this->actingAs($cutter)->post("/budget-requests/{$r->id}/decide", ['decision' => 'approved', 'cuts' => [$subsidy->id => 6000, $income->id => 3000]])->assertSessionHas('warning');
        $this->assertSame('pending', $r->fresh()->status);
        $this->actingAs($cutter)->post("/budget-requests/{$r->id}/decide", ['decision' => 'approved', 'cuts' => [$subsidy->id => 7500, $income->id => 1500]])->assertSessionHas('success');

        $r->refresh();
        $this->assertSame(['approved', 4, 2], [$r->status, $r->approvals()->count(), $r->cuts()->count()]);
        $this->assertSame([9000.0, 0.0, 1000.0], [$activity->fresh()->spent(), $activity->fresh()->pending(), $activity->fresh()->available()]);
        $left = $activity->fresh()->bySource();
        $this->assertSame([500.0, 500.0], [$left[$subsidy->id]['left'], $left[$income->id]['left']]);

        // ทุกขั้นมีสำเนาลายเซ็น และผู้ที่เปิดใบได้เท่านั้นที่เห็น
        $approval = $r->approvals()->first();
        $this->assertNotNull($approval->signature);
        Storage::disk('local')->assertExists($approval->signature);
        $this->actingAs($teacher)->get("/files/budget-approval-sign/{$approval->id}")->assertOk();
        $this->actingAs(User::where('username', 't8')->first())->get("/files/budget-approval-sign/{$approval->id}")->assertForbidden();
        $this->actingAs($teacher)->get("/budget-requests/{$r->id}")->assertOk()->assertSee('ตัดงบแล้ว')->assertSee('ตัดงบจาก')->assertDontSee('ยกเลิกใบนี้');
        $this->actingAs($this->admin())->get("/projects/{$project->id}")->assertOk()->assertSee($r->req_no);

        // ลดงบต่ำกว่าที่ตัดแล้วไม่ได้ และกิจกรรมที่มีคำขอแล้วลบไม่ได้
        $this->actingAs($this->admin())->put("/projects/{$project->id}/activities/{$activity->id}", ['name' => $activity->name, 'budgets' => [$subsidy->id => 7000, $income->id => 2000]])->assertSessionHas('warning');
        $this->actingAs($this->admin())->delete("/project-activities/{$activity->id}")->assertSessionHas('warning');
    }

    public function test_approving_needs_a_saved_signature_and_special_track_needs_its_own_cutter(): void
    {
        Settings::set(['budget_request_steps' => '']);
        [, $activity, $subsidy] = $this->setUpActivity('special');
        $teacher = User::where('username', 'teacher')->first();
        $general = $this->user('t7', 'budget.cut');
        $special = $this->user('t6', 'budget.cut_special', signed: false);

        // ปิดขั้นพิจารณาทั้งหมด: เหลือขั้นตัดงบขั้นเดียว
        $this->submit($teacher, $activity, 500);
        $r = BudgetRequest::sole();
        $this->assertSame(['cut'], $r->steps);

        // โครงการห้องเรียนพิเศษ: เจ้าหน้าที่ตัดงบกลุ่มทั่วไปตัดไม่ได้
        $this->actingAs($general)->post("/budget-requests/{$r->id}/decide", ['decision' => 'approved', 'cuts' => [$subsidy->id => 1000]])->assertForbidden();

        // ยังไม่มีลายเซ็น: อนุมัติไม่ได้ แต่ไม่อนุมัติได้
        $this->actingAs($special)->get("/budget-requests/{$r->id}")->assertOk()->assertSee('ยังไม่มีลายเซ็นของคุณในระบบ');
        $this->actingAs($special)->post("/budget-requests/{$r->id}/decide", ['decision' => 'approved', 'cuts' => [$subsidy->id => 1000]])->assertSessionHas('warning');
        $this->assertSame('pending', $r->fresh()->status);

        // ลายเซ็นต้องเป็น PNG จริง
        $this->actingAs($special)->post('/profile/signature', ['signature' => 'data:image/png;base64,'.base64_encode('not an image')])->assertSessionHasErrors('signature');
        $this->actingAs($special)->post('/profile/signature', ['signature' => self::PNG])->assertSessionHas('success');
        $this->actingAs($special->fresh())->get('/profile')->assertOk()->assertSee('ลายเซ็นของฉัน')->assertSee('บันทึกแล้ว');
        $this->actingAs($special->fresh())->get("/files/my-signature/{$special->id}")->assertOk();
        $this->actingAs($general)->get("/files/my-signature/{$special->id}")->assertForbidden();

        $this->actingAs($special->fresh())->post("/budget-requests/{$r->id}/decide", ['decision' => 'approved', 'cuts' => [$subsidy->id => 1000]])->assertSessionHas('success');
        $this->assertSame('approved', $r->fresh()->status);

        // เซ็นใหม่หรือลบลายเซ็น: สำเนาในเอกสารเดิมยังอยู่
        $copy = $r->approvals()->first()->signature;
        $this->actingAs($special->fresh())->post('/profile/signature', ['signature' => 'clear'])->assertSessionHas('success');
        $this->assertNull($special->fresh()->signature);
        Storage::disk('local')->assertExists($copy);
    }

    public function test_rejecting_or_cancelling_releases_the_reserve_and_closed_projects_take_no_requests(): void
    {
        [$project, $activity] = $this->setUpActivity();
        $teacher = User::where('username', 'teacher')->first();
        $checker = $this->user('t3', 'budget.review');

        $this->submit($teacher, $activity, 2000, ['type' => 'loan', 'title' => 'ขอยืมเงินไปราชการ']);
        $first = BudgetRequest::latest('id')->first();
        $this->assertSame(['loan', null], [$first->type, $first->method]);
        $this->actingAs($checker)->post("/budget-requests/{$first->id}/decide", ['decision' => 'rejected'])->assertSessionHasErrors('note');
        $this->actingAs($checker)->post("/budget-requests/{$first->id}/decide", ['decision' => 'rejected', 'note' => 'เอกสารไม่ครบ'])->assertSessionHas('success');
        $this->assertSame('rejected', $first->fresh()->status);
        $this->assertSame(10000.0, $activity->fresh()->available());

        $this->submit($teacher, $activity, 1000);
        $second = BudgetRequest::latest('id')->first();
        $this->actingAs(User::where('username', 't8')->first())->post("/budget-requests/{$second->id}/cancel")->assertForbidden();
        $this->actingAs($teacher)->post("/budget-requests/{$second->id}/cancel")->assertSessionHas('success');
        $this->assertSame(10000.0, $activity->fresh()->available());
        $this->actingAs($checker)->post("/budget-requests/{$second->id}/decide", ['decision' => 'approved'])->assertForbidden();

        // ปิดโครงการ: ต้องไม่มีคำขอรอพิจารณา และปิดแล้วขอเพิ่มไม่ได้
        $this->submit($teacher, $activity, 500);
        $third = BudgetRequest::latest('id')->first();
        $this->actingAs($this->admin())->post("/projects/{$project->id}/close", ['summary' => 'เสร็จตามแผน'])->assertSessionHas('warning');
        $this->actingAs($teacher)->post("/budget-requests/{$third->id}/cancel");
        $this->actingAs($this->admin())->post("/projects/{$project->id}/close", [])->assertSessionHasErrors('summary');
        $this->actingAs($this->admin())->post("/projects/{$project->id}/close", ['summary' => 'เสร็จตามแผน'])->assertSessionHas('success');
        $this->submit($teacher, $activity, 100)->assertSessionHas('warning');

        // ขั้นพิจารณาตั้งค่าได้ มีผลกับใบที่ยื่นใหม่ ใบเดิมใช้ลำดับ ณ วันที่ยื่น
        $this->actingAs($this->admin())->post("/projects/{$project->id}/close");
        $this->actingAs($this->admin())->post('/budget/steps', ['steps' => ['director']])->assertSessionHas('success');
        $this->assertSame(['director', 'cut'], BudgetRequest::configuredSteps());
        $this->submit($teacher, $activity, 100);
        $this->assertSame(['director', 'cut'], BudgetRequest::latest('id')->first()->steps);
        $this->assertCount(4, $first->fresh()->steps);

        $this->actingAs($this->admin())->get('/menu')->assertOk()->assertSee('งบประมาณ')->assertSee('ขอใช้งบ');
        $this->actingAs($teacher)->get('/menu')->assertOk()->assertSee('ขอใช้งบ')->assertSee('โครงการ');
    }
}
