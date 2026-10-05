<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\HealthMeasurement;
use App\Models\Invoice;
use App\Models\Shop;
use App\Models\Submission;
use App\Models\Term;
use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use App\Support\RiskScan;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** งานเก็บตก: หมวดสินค้าที่ไม่ให้ซื้อ สัญญาณเสี่ยงการบ้าน/BMI ลบข้อมูลตามเวลา กระเป๋าเงินในแดชบอร์ด และเงินเป็นทศนิยม 2 ตำแหน่ง */
class FollowUpsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    public function test_parent_blocks_a_product_category(): void
    {
        $parent = User::where('phone', '0812345678')->first();
        $child = $parent->children()->first();
        $shop = Shop::create(['name' => 'สหกรณ์']);
        $soda = $shop->products()->create(['name' => 'น้ำอัดลม', 'price' => 15, 'category' => 'เครื่องดื่มหวาน']);
        $rice = $shop->products()->create(['name' => 'ข้าวผัด', 'price' => 30, 'category' => 'อาหาร']);
        WalletService::topupCash($child, 200, $this->admin());
        $charge = fn (array $items, string $key) => $this->actingAs($this->admin())
            ->postJson("/pos/{$shop->id}/charge", ['student_id' => $child->id, 'client_key' => $key, 'items' => $items]);

        $this->actingAs($parent)->get("/parent/wallet/{$child->id}")->assertOk()->assertSee('หมวดสินค้าที่ไม่ให้ซื้อ')->assertSee('เครื่องดื่มหวาน');
        $this->actingAs($parent)->put("/parent/wallet/{$child->id}/settings", ['blocked_categories' => ['เครื่องดื่มหวาน']])->assertSessionHasNoErrors();
        $this->assertSame(['เครื่องดื่มหวาน'], Wallet::where('student_id', $child->id)->first()->blocked_categories);

        // ทั้งรายการถูกปฏิเสธ ไม่ตัดเงินบางส่วน · หมวดอื่นซื้อได้ตามเดิม
        $charge([['product_id' => $rice->id, 'qty' => 1], ['product_id' => $soda->id, 'qty' => 1]], 'key-block-01')
            ->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertSame('200.00', Wallet::where('student_id', $child->id)->value('balance'));
        $charge([['product_id' => $rice->id, 'qty' => 1]], 'key-block-02')->assertOk()->assertJsonPath('balance', 170);

        // เลิกติ๊ก = ซื้อได้
        $this->actingAs($parent)->put("/parent/wallet/{$child->id}/settings", [])->assertSessionHasNoErrors();
        $charge([['product_id' => $soda->id, 'qty' => 1]], 'key-block-03')->assertOk();
    }

    public function test_risk_scan_flags_homework_backlog_and_bmi(): void
    {
        $course = Course::where('term_id', Term::current()->id)->whereDoesntHave('members')->first();
        $students = $course->classroom->students()->orderBy('id')->limit(2)->get();
        [$behind, $fine] = [$students[0], $students[1]];
        Assignment::whereHas('course', fn ($q) => $q->where('classroom_id', $course->classroom_id))->delete();
        HealthMeasurement::whereIn('student_id', $students->pluck('id'))->delete();

        foreach (range(1, RiskScan::HOMEWORK_MISSING) as $i) {
            $a = Assignment::create(['course_id' => $course->id, 'title' => "งาน {$i}", 'due_at' => now()->subDays($i), 'max_score' => 10]);
            Submission::create(['assignment_id' => $a->id, 'student_id' => $fine->id, 'submitted_at' => now()->subDays($i + 1)]);
        }
        // งานที่ยังไม่ถึงกำหนดไม่นับ
        Assignment::create(['course_id' => $course->id, 'title' => 'งานใหม่', 'due_at' => now()->addDay(), 'max_score' => 10]);
        HealthMeasurement::create(['student_id' => $fine->id, 'measured_on' => today(), 'weight' => 80, 'height' => 150]);
        HealthMeasurement::create(['student_id' => $behind->id, 'measured_on' => today(), 'weight' => 45, 'height' => 150]);

        $risks = RiskScan::forClassrooms([$course->classroom_id])->keyBy(fn ($r) => $r['student']->id);
        $this->assertSame(RiskScan::HOMEWORK_MISSING.' ชิ้น', $risks[$behind->id]['signals']['homework']);
        $this->assertArrayNotHasKey('bmi', $risks[$behind->id]['signals']);
        $this->assertArrayNotHasKey('homework', $risks[$fine->id]['signals']);
        $this->assertStringContainsString('อ้วน', $risks[$fine->id]['signals']['bmi']);

        $this->actingAs($this->admin())->get('/care')->assertOk()->assertSee('ค้างส่งการบ้าน')->assertSee('น้ำหนักผิดเกณฑ์');
    }

    public function test_scheduled_purge_only_runs_when_switched_on(): void
    {
        $old = AuditLog::create(['action' => 'student.update', 'description' => 'เก่า']);
        AuditLog::whereKey($old->id)->update(['created_at' => now()->subYears(4)]);

        $this->artisan('privacy:purge --scheduled')->assertExitCode(0);
        $this->assertModelExists($old);

        Settings::set(['privacy_purge_auto' => '1']);
        $this->artisan('privacy:purge --scheduled')->assertExitCode(0);
        $this->assertModelMissing($old);
    }

    public function test_executive_dashboard_shows_wallet_summary(): void
    {
        $shop = Shop::create(['name' => 'ร้านน้ำ']);
        $child = User::where('phone', '0812345678')->first()->children()->first();
        WalletService::topupCash($child, 100, $this->admin());
        WalletService::charge($child, $shop, [['name' => 'น้ำ', 'price' => 10.0, 'qty' => 2]], $this->admin(), 'key-exec-0001');

        $this->actingAs($this->admin())->get('/executive')->assertOk()
            ->assertSee('กระเป๋าเงินและร้านค้า')->assertSee('ร้านน้ำ')->assertSee('ยอดขายที่ยังไม่ได้จ่ายให้ร้านค้า 20 บาท')->assertSee('ยังไม่เคยกระทบยอด');
    }

    public function test_fee_money_is_kept_as_two_decimal_strings(): void
    {
        $invoice = Invoice::whereHas('payments')->with('payments', 'items')->first();
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $invoice->total);
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $invoice->payments->first()->amount);
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $invoice->items->first()->amount);
        // 0.1 + 0.2 ต้องไม่เพี้ยนเมื่อบันทึกและอ่านกลับ
        $invoice->forceFill(['discount' => 0.1 + 0.2])->save();
        $this->assertSame('0.30', $invoice->fresh()->discount);
    }
}
