<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Supply;
use App\Models\User;
use App\Support\AssetNumber;
use App\Support\SupplyNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** ออกเลขครุภัณฑ์ / รหัสวัสดุอัตโนมัติ · ตั้งรูปแบบเลข · เพิ่มหลายชิ้นพร้อมกัน */
class AssetNumberTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function facility(): User
    {
        return User::find(User::facilityManagerIds()[0]);
    }

    public function test_fiscal_year_and_pattern_rules(): void
    {
        $this->assertSame(2570, AssetNumber::fiscalYear(Carbon::parse('2026-10-01')));
        $this->assertSame(2569, AssetNumber::fiscalYear(Carbon::parse('2026-09-30')));

        $this->assertNull(AssetNumber::problem('{CAT}-{FY}-{SEQ}'));
        $this->assertNotNull(AssetNumber::problem('{CAT}-{FY}'));
        $this->assertNotNull(AssetNumber::problem('{SEQ}-{SEQ}'));
        $this->assertStringContainsString('{ABC}', AssetNumber::problem('{ABC}-{SEQ}'));

        $date = Carbon::parse('2026-11-01');
        $this->assertSame(['7440-2570-0001'], AssetNumber::next('ครุภัณฑ์คอมพิวเตอร์', $date));
        $this->assertSame(['สธ.7110-001/70', 'สธ.7110-002/70'], AssetNumber::next('ครุภัณฑ์สำนักงาน', $date, 2, 'สธ.{CAT}-{SEQ3}/{FY2}'));
        $this->assertSame(['2569-00001'], AssetNumber::next(null, $date, 1, '{YEAR}-{SEQ5}'));
        $this->assertSame(['9999-2570-0001'], AssetNumber::next('หมวดที่ไม่รู้จัก', $date));
    }

    public function test_blank_code_continues_the_series(): void
    {
        $m = $this->facility();
        // เลขเดิมในชุดเดียวกัน (รวมที่นำเข้ามา) → นับต่อจากเลขสูงสุด
        Asset::create(['code' => '7440-2570-0007', 'name' => 'ของเดิม', 'status' => 'normal']);
        $this->actingAs($m)->post('/inventory', ['name' => 'โน้ตบุ๊ก', 'category' => 'ครุภัณฑ์คอมพิวเตอร์', 'acquired_on' => '2026-11-01', 'status' => 'normal'])
            ->assertRedirect()->assertSessionHas('success', 'บันทึก 7440-2570-0008 แล้ว');

        // ประเภทอื่น / ปีงบอื่น = นับใหม่
        $this->actingAs($m)->post('/inventory', ['name' => 'ตู้เอกสาร', 'category' => 'ครุภัณฑ์สำนักงาน', 'acquired_on' => '2026-09-30', 'status' => 'normal']);
        $this->assertSame('7110-2569-0001', Asset::where('name', 'ตู้เอกสาร')->value('code'));

        // เลขที่จะได้ (แสดงในฟอร์ม)
        $this->actingAs($m)->getJson('/inventory/next-number?'.http_build_query(['category' => 'ครุภัณฑ์คอมพิวเตอร์', 'acquired_on' => '2026-11-01', 'quantity' => 3]))
            ->assertOk()->assertJson(['first' => '7440-2570-0009', 'last' => '7440-2570-0011', 'count' => 3]);
        $this->actingAs(User::where('username', 'teacher')->first())->getJson('/inventory/next-number')->assertForbidden();

        // แก้ไข: ลบเลขเดิมออก = ออกเลขใหม่ตามรูปแบบ · ใส่เองยังได้
        $old = Asset::where('code', '7440-001-0001')->first();
        $this->actingAs($m)->put("/inventory/{$old->id}", ['code' => '', 'name' => $old->name, 'category' => $old->category, 'acquired_on' => '2026-11-02', 'status' => 'normal'])->assertRedirect();
        $this->assertSame('7440-2570-0009', $old->fresh()->code);
        $this->assertTrue(AuditLog::where('action', 'asset.update')->where('subject_id', $old->id)->exists());
        $this->actingAs($m)->post('/inventory', ['code' => 'MANUAL-1', 'name' => 'พิมพ์เอง', 'status' => 'normal']);
        $this->assertTrue(Asset::where('code', 'MANUAL-1')->exists());

        $this->actingAs($m)->get('/inventory/create')->assertOk()->assertSee('เว้นว่าง = ออกเลขอัตโนมัติ')->assertSee('inventory\\/next-number', false);
    }

    public function test_multiple_items_get_consecutive_numbers_and_own_photos(): void
    {
        Storage::fake('public');
        $m = $this->facility();
        $this->actingAs($m)->followingRedirects()->post('/inventory', [
            'name' => 'โต๊ะนักเรียน', 'category' => 'ครุภัณฑ์การศึกษา', 'acquired_on' => '2026-11-01', 'quantity' => 3, 'price' => 1200,
            'serial_no' => 'SN-1', 'status' => 'normal', 'location' => 'ห้อง ม.1/1', 'photo' => UploadedFile::fake()->image('desk.jpg'),
        ])->assertOk()->assertSee('6910-2570-0001 ถึง 6910-2570-0003')->assertSee('พิมพ์สติกเกอร์ชุดนี้');

        $desks = Asset::where('name', 'โต๊ะนักเรียน')->orderBy('code')->get();
        $this->assertSame(['6910-2570-0001', '6910-2570-0002', '6910-2570-0003'], $desks->pluck('code')->all());
        $this->assertSame([null], $desks->pluck('serial_no')->unique()->values()->all()); // หมายเลขเครื่องเติมรายชิ้นภายหลัง
        $this->assertSame([1200.0], $desks->pluck('price')->unique()->values()->all());
        $this->assertCount(3, $desks->pluck('photo')->unique());
        $desks->each(fn ($d) => Storage::disk('public')->assertExists($d->photo));
        $this->assertTrue(AuditLog::where('action', 'asset.create')->where('description', 'like', '%6910-2570-0001 ถึง 6910-2570-0003%')->exists());

        // เพิ่มหลายชิ้นแต่ใส่เลขเอง → ไม่รับ
        $this->actingAs($m)->post('/inventory', ['code' => 'X-1', 'name' => 'x', 'quantity' => 2, 'status' => 'normal'])->assertSessionHasErrors('code');
        $this->actingAs($m)->post('/inventory', ['name' => 'x', 'quantity' => 500, 'status' => 'normal'])->assertSessionHasErrors('quantity');
    }

    public function test_manager_sets_pattern_and_category_codes(): void
    {
        $m = $this->facility();
        $this->actingAs(User::where('username', 'teacher')->first())->get('/inventory/numbering')->assertForbidden();
        $this->actingAs($m)->get('/inventory/numbering')->assertOk()->assertSee('รูปแบบเลขครุภัณฑ์')->assertSee('7440')->assertSee('{CAT}-{FY}-{SEQ}');

        $codes = AssetNumber::codes();
        $this->actingAs($m)->put('/inventory/numbering', ['pattern' => '{CAT}-{FY}', 'codes' => $codes])->assertSessionHasErrors('pattern');
        $this->actingAs($m)->put('/inventory/numbering', ['pattern' => '{CAT}-{XX}-{SEQ}', 'codes' => $codes])->assertSessionHasErrors('pattern');
        $this->actingAs($m)->put('/inventory/numbering', ['pattern' => '{SEQ}', 'codes' => ['ครุภัณฑ์กีฬา' => 'a b'] + $codes])->assertSessionHasErrors('codes.ครุภัณฑ์กีฬา');

        $this->actingAs($m)->put('/inventory/numbering', ['pattern' => 'สธ.{CAT}-{SEQ3}/{FY2}', 'codes' => ['ครุภัณฑ์กีฬา' => 'KH'] + $codes])
            ->assertRedirect(route('assets.numbering'));
        $this->assertSame('สธ.{CAT}-{SEQ3}/{FY2}', AssetNumber::pattern());
        $this->assertSame('KH', AssetNumber::codeFor('ครุภัณฑ์กีฬา'));
        $this->assertTrue(AuditLog::where('action', 'setting.update')->where('description', 'like', '%รูปแบบเลขครุภัณฑ์%')->exists());

        $this->actingAs($m)->post('/inventory', ['name' => 'ลูกฟุตบอล', 'category' => 'ครุภัณฑ์กีฬา', 'acquired_on' => '2026-11-01', 'quantity' => 2, 'status' => 'normal']);
        $this->assertSame(['สธ.KH-001/70', 'สธ.KH-002/70'], Asset::where('name', 'ลูกฟุตบอล')->orderBy('code')->pluck('code')->all());
        $this->actingAs($m)->get('/inventory/numbering')->assertSee('สธ.KH-003/70');
    }

    public function test_supply_codes_are_assigned_automatically(): void
    {
        $m = $this->facility();
        $base = ['unit' => 'ชิ้น', 'min_stock' => 0];
        // ต่อจากรหัสที่มีอยู่ (seeder มี OF-001)
        $this->actingAs($m)->post('/supplies', $base + ['name' => 'แฟ้มเอกสาร', 'category' => 'วัสดุสำนักงาน'])->assertRedirect();
        $this->assertSame('OF-002', Supply::where('name', 'แฟ้มเอกสาร')->value('code'));
        // หมวดที่พิมพ์ใหม่ยังไม่มีรหัส → ใช้รหัส "วัสดุอื่น ๆ"
        $this->actingAs($m)->post('/supplies', $base + ['name' => 'ปุ๋ย', 'category' => 'วัสดุการเกษตร']);
        $this->assertSame('OT-001', Supply::where('name', 'ปุ๋ย')->value('code'));
        $this->actingAs($m)->getJson('/supplies/next-number?category='.urlencode('วัสดุการเกษตร'))->assertOk()->assertJson(['first' => 'OT-002', 'needs_category' => true]);
        $this->actingAs($m)->getJson('/supplies/next-number?category='.urlencode('วัสดุสำนักงาน'))->assertJson(['first' => 'OF-003', 'needs_category' => false]);
        // พิมพ์เองได้
        $this->actingAs($m)->post('/supplies', $base + ['name' => 'กาว', 'code' => 'GLUE-1']);
        $this->assertTrue(Supply::where('code', 'GLUE-1')->exists());

        // หน้าตั้งรหัส: หมวดที่ใช้อยู่ขึ้นในตารางให้ตั้งรหัสได้
        $this->actingAs(User::where('username', 'teacher')->first())->get('/supplies/numbering')->assertForbidden();
        $this->actingAs($m)->get('/supplies/numbering')->assertOk()->assertSee('รูปแบบรหัสวัสดุ')->assertSee('วัสดุการเกษตร')->assertSee('OF-003');
        $codes = SupplyNumber::codes();
        $this->actingAs($m)->put('/supplies/numbering', ['pattern' => '{CAT}{SEQ}', 'codes' => $codes + ['วัสดุการเกษตร' => 'AG']])->assertRedirect(route('supplies.numbering'));
        $this->actingAs($m)->post('/supplies', $base + ['name' => 'จอบ', 'category' => 'วัสดุการเกษตร']);
        $this->assertSame('AG0001', Supply::where('name', 'จอบ')->value('code'));
        $this->assertSame('{CAT}-{FY}-{SEQ}', AssetNumber::pattern()); // ไม่กระทบเลขครุภัณฑ์

        // แก้ไข: ลบรหัสออก = ออกรหัสใหม่
        $paper = Supply::where('code', 'OF-001')->first();
        $this->actingAs($m)->put("/supplies/{$paper->id}", ['name' => $paper->name, 'unit' => $paper->unit, 'category' => $paper->category, 'code' => ''])->assertRedirect();
        $this->assertSame('OF0001', $paper->fresh()->code);
        $this->actingAs($m)->get('/supplies/create')->assertOk()->assertSee('เว้นว่าง = ออกรหัสอัตโนมัติ');
    }
}
