<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCheck;
use App\Models\AuditLog;
use App\Models\RepairRequest;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** ครุภัณฑ์ · ตรวจสอบพัสดุประจำปี · แจ้งซ่อม */
class FacilityTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function teacher(): User
    {
        return User::where('username', 'teacher')->first();
    }

    /** ครูงานอาคารสถานที่ (ตั้งไว้ใน seeder) */
    private function facility(): User
    {
        return User::find(User::facilityManagerIds()[0]);
    }

    public function test_permissions(): void
    {
        $this->assertFalse($this->teacher()->canManageFacilities());
        $this->assertTrue($this->facility()->canManageFacilities());
        $this->assertTrue(User::where('username', 'admin')->first()->canManageFacilities());

        $this->actingAs($this->teacher())->get('/inventory')->assertForbidden();
        $this->actingAs($this->teacher())->get('/asset-checks')->assertForbidden();
        $this->actingAs($this->teacher())->get('/repairs/report')->assertForbidden();
        $this->actingAs($this->teacher())->get('/repairs')->assertOk()->assertSee('รายการที่คุณแจ้ง');
        $this->actingAs($this->facility())->get('/inventory')->assertOk()->assertSee('ทะเบียนครุภัณฑ์');
        $this->actingAs(User::where('role', 'parent')->first())->get('/repairs')->assertForbidden();

        // เมนู: ครูทั่วไปเห็นแจ้งซ่อม ไม่เห็นครุภัณฑ์
        $this->actingAs($this->teacher())->get('/menu')->assertSee('แจ้งซ่อม')->assertDontSee('href="'.route('assets.index').'"', false);
        $this->actingAs($this->facility())->get('/menu')->assertSee('href="'.route('assets.index').'"', false);
    }

    public function test_asset_crud_qr_and_delete_guard(): void
    {
        Storage::fake('public');
        $m = $this->facility();
        $this->actingAs($m)->post('/inventory', ['code' => 'T-001', 'name' => 'ลำโพงห้องเรียน', 'category' => 'ครุภัณฑ์ไฟฟ้าและวิทยุ', 'status' => 'normal', 'location' => 'ห้อง ม.2/1',
            'photo' => UploadedFile::fake()->image('a.jpg')])->assertSessionHasNoErrors();
        $asset = Asset::where('code', 'T-001')->first();
        $this->assertSame(0.0, $asset->price);
        $this->assertNotEmpty($asset->qr_token);
        $this->actingAs($m)->post('/inventory', ['code' => 'T-001', 'name' => 'ซ้ำ', 'status' => 'normal'])->assertSessionHasErrors('code');

        // ครูทั่วไปสแกน QR → เห็นหน้าครุภัณฑ์ (ไม่เห็นราคา) + ปุ่มแจ้งซ่อม
        $this->actingAs($this->teacher())->get("/a/{$asset->qr_token}")->assertRedirect(route('assets.show', $asset));
        $this->actingAs($this->teacher())->get("/inventory/{$asset->id}")->assertOk()->assertSee('แจ้งซ่อม')->assertDontSee('มูลค่าสุทธิ');
        $this->actingAs($this->teacher())->put("/inventory/{$asset->id}", ['code' => 'T-001', 'name' => 'x', 'status' => 'normal'])->assertForbidden();

        $this->actingAs($m)->get('/inventory/labels?ids='.$asset->id)->assertOk()->assertSee($asset->qrUrl(), false);

        // มีประวัติซ่อมแล้วลบไม่ได้ ต้องจำหน่าย
        RepairRequest::create(['ticket_no' => 'X-1', 'asset_id' => $asset->id, 'title' => 'เสียงแตก', 'status' => 'done', 'reporter_id' => $this->teacher()->id]);
        $this->actingAs($m)->delete("/inventory/{$asset->id}")->assertSessionHasErrors('asset');
        $this->actingAs($m)->put("/inventory/{$asset->id}", ['code' => 'T-001', 'name' => 'ลำโพงห้องเรียน', 'status' => 'disposed'])->assertRedirect();
        $this->assertSame(today()->toDateString(), $asset->fresh()->disposed_on->toDateString());
        $this->assertTrue(AuditLog::where('action', 'asset.update')->where('subject_id', $asset->id)->exists());
        $this->actingAs($m)->get('/inventory')->assertDontSee('T-001'); // จำหน่ายแล้วไม่อยู่ในรายการใช้งาน
    }

    public function test_straight_line_depreciation_keeps_one_baht(): void
    {
        Carbon::setTestNow('2026-10-01');
        $a = new Asset(['price' => 10000, 'category' => 'ครุภัณฑ์สำนักงาน', 'acquired_on' => '2024-10-01']); // 730 วัน จาก 5 ปี
        $this->assertSame(5, $a->life());
        $this->assertSame(3999.6, $a->accumulatedDepreciation());
        $this->assertSame(6000.4, $a->bookValue());
        $old = new Asset(['price' => 10000, 'useful_life' => 2, 'acquired_on' => '2015-01-01']);
        $this->assertSame(1.0, $old->bookValue());
        $this->assertNull((new Asset(['price' => 5000]))->bookValue());
        Carbon::setTestNow();
    }

    public function test_import_from_excel_paste(): void
    {
        $paste = "เลขครุภัณฑ์\tชื่อครุภัณฑ์\tประเภท\tราคา\tวันที่ได้มา\tสถานที่\n"
            ."IMP-1\tกล้องจุลทรรศน์\tครุภัณฑ์วิทยาศาสตร์\t12,500\t16/5/2567\tห้องวิทย์\n"
            ."IMP-2\tตู้เหล็ก\tครุภัณฑ์สำนักงาน\t4500\t2025-01-10\tห้องพักครู\n"
            ."\tไม่มีเลข\t\t\t\t\n";
        $this->actingAs($this->facility())->post('/inventory/import', ['data' => $paste])->assertRedirect(route('assets.index'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'ใหม่ 2'));
        $a = Asset::where('code', 'IMP-1')->first();
        $this->assertSame([12500.0, '2024-05-16', 'ห้องวิทย์'], [$a->price, $a->acquired_on->toDateString(), $a->location]);

        $this->actingAs($this->facility())->post('/inventory/import', ['data' => "เลขครุภัณฑ์\tชื่อ\tสถานที่\nIMP-1\tกล้องจุลทรรศน์\tห้องแล็บ 2"])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'อัปเดต 1'));
        $this->assertSame('ห้องแล็บ 2', $a->fresh()->location);
        $this->assertSame(12500.0, $a->fresh()->price); // คอลัมน์ที่ไม่ได้ส่งมา ไม่ถูกล้าง

        $this->actingAs($this->facility())->post('/inventory/import', ['data' => "ชื่อ\tราคา\nx\t1"])->assertSessionHasErrors('data');
    }

    public function test_repair_flow_updates_asset_and_notifies(): void
    {
        Storage::fake('public');
        $asset = Asset::where('name', 'like', 'เครื่องฉายภาพ%')->first();
        $this->actingAs($this->teacher())->get("/repairs/create?asset={$asset->id}")->assertOk()->assertSee($asset->code);
        $this->actingAs($this->teacher())->post('/repairs', ['title' => 'ภาพไม่ขึ้น', 'priority' => 'urgent', 'asset_id' => $asset->id,
            'photo' => UploadedFile::fake()->image('p.jpg')])->assertSessionHasNoErrors();
        $repair = RepairRequest::latest('id')->first();
        $this->assertSame('R'.(today()->year + 543).'-0004', $repair->ticket_no); // seeder มี 3 ใบ
        $this->assertSame($asset->location, $repair->location);
        $this->assertNotNull($repair->photo);

        // ไม่ระบุครุภัณฑ์ต้องบอกสถานที่
        $this->actingAs($this->teacher())->post('/repairs', ['title' => 'ก๊อกน้ำรั่ว', 'priority' => 'normal'])->assertSessionHasErrors('location');

        // ครูคนอื่นเปิดดูไม่ได้ / ครูผู้แจ้งอัปเดตไม่ได้
        $other = User::where('role', 'teacher')->get()->first(fn ($u) => ! $u->canManageFacilities() && $u->id !== $this->teacher()->id);
        $this->actingAs($other)->get("/repairs/{$repair->id}")->assertForbidden();
        $this->actingAs($this->teacher())->put("/repairs/{$repair->id}", ['status' => 'done'])->assertForbidden();

        $m = $this->facility();
        $this->actingAs($m)->put("/repairs/{$repair->id}", ['status' => 'in_progress', 'assignee_id' => $m->id, 'note' => 'รับเรื่องแล้ว'])->assertRedirect();
        $this->assertSame('repairing', $asset->fresh()->status);
        $this->actingAs($this->teacher())->post("/repairs/{$repair->id}/cancel")->assertForbidden(); // รับเรื่องแล้วยกเลิกเองไม่ได้

        $this->actingAs($m)->put("/repairs/{$repair->id}", ['status' => 'done', 'assignee_id' => $m->id, 'cost' => 850, 'result_note' => 'เปลี่ยนหลอดภาพ'])->assertRedirect();
        $repair->refresh();
        $this->assertSame('normal', $asset->fresh()->status);
        $this->assertNotNull($repair->finished_at);
        $this->assertSame(['pending', 'in_progress', 'done'], $repair->updates()->reorder('id')->pluck('status')->all());
        $this->assertTrue(AuditLog::where('action', 'repair.cost')->where('subject_id', $repair->id)->exists());

        $this->actingAs($this->teacher())->get("/repairs/{$repair->id}")->assertOk()->assertSee('ซ่อมเสร็จ')->assertSee('รับเรื่องแล้ว');
        $this->actingAs($m)->get('/repairs/report?month='.now()->format('Y-m'))->assertOk()->assertSee($repair->ticket_no)->assertSee('850.00');
        $this->actingAs($m)->get("/inventory/{$asset->id}")->assertSee($repair->ticket_no);
    }

    public function test_reporter_can_cancel_pending_request(): void
    {
        $repair = RepairRequest::where('status', 'pending')->first();
        $reporter = $repair->reporter;
        $this->actingAs($this->teacher())->post("/repairs/{$repair->id}/cancel")->assertForbidden();
        $this->actingAs($reporter)->post("/repairs/{$repair->id}/cancel")->assertRedirect();
        $this->assertSame('cancelled', $repair->fresh()->status);
    }

    public function test_annual_inventory_check_by_scan_and_report(): void
    {
        $m = $this->facility();
        $year = today()->year + 543;
        [$a, $b] = Asset::orderBy('id')->take(2)->get()->all();

        // สแกน QR (ได้ URL) และพิมพ์เลขครุภัณฑ์ได้ทั้งคู่
        $this->actingAs($m)->postJson('/asset-checks', ['code' => $a->qrUrl(), 'result' => 'found', 'year' => $year])->assertOk()->assertJsonPath('asset.code', $a->code);
        $this->actingAs($m)->postJson('/asset-checks', ['code' => $b->code, 'result' => 'damaged', 'year' => $year])->assertOk()->assertJsonPath('result', 'พบ ชำรุด');
        $this->actingAs($m)->postJson('/asset-checks', ['code' => 'NOPE', 'result' => 'found', 'year' => $year])->assertNotFound();
        // สแกนซ้ำ = แก้ผล ไม่เพิ่มแถว
        $this->actingAs($m)->postJson('/asset-checks', ['code' => $b->code, 'result' => 'found', 'year' => $year])->assertOk();
        $this->assertSame(2, AssetCheck::where('year', $year)->count());

        $total = Asset::inService()->count();
        $res = $this->actingAs($m)->get("/asset-checks?year={$year}")->assertOk()->assertSee('ประธานกรรมการตรวจสอบพัสดุ');
        $this->assertSame(['found' => 2, 'damaged' => 0, 'missing' => 0, 'unchecked' => $total - 2], $res->viewData('counts')->all());
        $this->actingAs($m)->get('/asset-checks/scan')->assertOk()->assertSee('html5-qrcode');
        $this->actingAs($this->teacher())->postJson('/asset-checks', ['code' => $a->code, 'result' => 'found', 'year' => $year])->assertForbidden();
    }

    public function test_settings_choose_facility_managers(): void
    {
        $admin = User::where('username', 'admin')->first();
        $teacher = $this->teacher();
        $this->actingAs($admin)->get('/settings')->assertSee('งานพัสดุ / อาคารสถานที่');
        $this->actingAs($admin)->post('/settings', array_merge(Settings::all(), ['facility_manager_ids' => [$teacher->id]]))->assertRedirect();
        $this->assertSame([$teacher->id], User::facilityManagerIds());
        $this->assertTrue($teacher->fresh()->canManageFacilities());
    }
}
