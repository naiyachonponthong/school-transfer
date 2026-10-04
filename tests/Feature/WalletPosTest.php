<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Shop;
use App\Models\Student;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletSale;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** กระเป๋าเงินนักเรียนและหน้าจอขาย: เติมเงิน ตัดเงิน วงเงิน ยกเลิก สิทธิ์ และรายงาน */
class WalletPosTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    private function parentOf(Student $s): User
    {
        return $s->guardians()->first();
    }

    private function child(): Student
    {
        return User::where('phone', '0812345678')->first()->children()->first();
    }

    /** ร้านพร้อมสินค้า 2 รายการ และคนขาย 1 คน (ครูที่ได้ตำแหน่งผู้ขาย) */
    private function shop(): array
    {
        $shop = Shop::create(['name' => 'ร้านข้าวแกง']);
        $rice = $shop->products()->create(['name' => 'ข้าวราดแกง', 'price' => 25]);
        $water = $shop->products()->create(['name' => 'น้ำเปล่า', 'price' => 7]);
        $cashier = User::where('username', 't3')->first();
        $cashier->roles()->sync([Role::where('key', 'cashier')->first()->id]);
        $cashier->flushPermissions();
        $shop->cashiers()->attach($cashier->id);

        return [$shop, $rice, $water, $cashier->fresh()];
    }

    private function charge(User $cashier, Shop $shop, Student $s, array $items, string $key = 'key-00000001')
    {
        return $this->actingAs($cashier)->postJson("/pos/{$shop->id}/charge", ['student_id' => $s->id, 'client_key' => $key, 'items' => $items]);
    }

    public function test_cash_topup_then_purchase_updates_balance_and_ledger(): void
    {
        [$shop, $rice, $water, $cashier] = $this->shop();
        $s = $this->child();

        $this->actingAs($this->admin())->post('/wallets/topup', ['student_id' => $s->id, 'amount' => 100])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('100.00', Wallet::where('student_id', $s->id)->value('balance'));

        // สแกนบัตร: ได้ชื่อและยอดคงเหลือ
        $this->actingAs($cashier)->postJson("/pos/{$shop->id}/lookup", ['code' => $s->student_code])
            ->assertOk()->assertJsonPath('student.id', $s->id)->assertJsonPath('student.balance', 100);
        $this->actingAs($cashier)->postJson("/pos/{$shop->id}/lookup", ['code' => 'nope'])->assertNotFound();

        // ราคายึดจากฐานข้อมูล แม้หน้าจอส่งราคาอื่นมา
        $this->charge($cashier, $shop, $s, [['product_id' => $rice->id, 'price' => 1, 'qty' => 2], ['product_id' => $water->id, 'qty' => 1], ['price' => 3, 'qty' => 1]])
            ->assertOk()->assertJsonPath('total', 60)->assertJsonPath('balance', 40);

        $wallet = Wallet::where('student_id', $s->id)->first();
        $this->assertSame('40.00', $wallet->balance);
        $this->assertSame(['topup', 'purchase'], $wallet->transactions()->orderBy('id')->pluck('type')->all());
        $this->assertSame('40.00', $wallet->transactions()->latest('id')->value('balance_after'));
        // ยอดคงเหลือ = ผลรวมของสมุดรายการเสมอ
        $this->assertEquals((float) $wallet->balance, (float) $wallet->transactions()->sum('amount'));

        $this->actingAs($cashier)->get("/pos/{$shop->id}")->assertOk()->assertSee('ข้าวราดแกง')->assertSee($s->fullName());
    }

    public function test_charge_is_refused_when_balance_limit_or_freeze_blocks_it(): void
    {
        [$shop, $rice, , $cashier] = $this->shop();
        $s = $this->child();
        WalletService::topupCash($s, 60, $this->admin());

        // ยอดไม่พอ
        $this->charge($cashier, $shop, $s, [['product_id' => $rice->id, 'qty' => 3]], 'key-over-balance')->assertStatus(422)->assertJsonPath('ok', false);

        // เกินวงเงินต่อวันที่ผู้ปกครองตั้ง
        $parent = $this->parentOf($s);
        $this->actingAs($parent)->put("/parent/wallet/{$s->id}/settings", ['daily_limit' => 30])->assertRedirect();
        $this->charge($cashier, $shop, $s, [['product_id' => $rice->id, 'qty' => 1]], 'key-within-limit')->assertOk();
        $this->charge($cashier, $shop, $s, [['product_id' => $rice->id, 'qty' => 1]], 'key-over-limit')->assertStatus(422);

        // ระงับบัตร
        $this->actingAs($parent)->put("/parent/wallet/{$s->id}/settings", ['is_frozen' => 1])->assertRedirect();
        $this->charge($cashier, $shop, $s, [['price' => 5, 'qty' => 1]], 'key-frozen')->assertStatus(422);

        $this->assertSame('35.00', Wallet::where('student_id', $s->id)->value('balance'));
        $this->assertSame(1, WalletSale::count());
    }

    public function test_repeating_the_same_charge_does_not_take_money_twice(): void
    {
        [$shop, $rice, , $cashier] = $this->shop();
        $s = $this->child();
        WalletService::topupCash($s, 100, $this->admin());

        $first = $this->charge($cashier, $shop, $s, [['product_id' => $rice->id, 'qty' => 1]], 'same-key-123')->assertOk()->json('sale_id');
        $second = $this->charge($cashier, $shop, $s, [['product_id' => $rice->id, 'qty' => 1]], 'same-key-123')->assertOk()->json('sale_id');
        $this->assertSame($first, $second);
        $this->assertSame('75.00', Wallet::where('student_id', $s->id)->value('balance'));
        $this->assertSame(1, WalletTransaction::where('type', 'purchase')->count());
    }

    public function test_void_returns_the_money_and_cannot_be_done_twice(): void
    {
        [$shop, $rice, , $cashier] = $this->shop();
        $s = $this->child();
        WalletService::topupCash($s, 100, $this->admin());
        $saleId = $this->charge($cashier, $shop, $s, [['product_id' => $rice->id, 'qty' => 2]])->json('sale_id');

        $this->actingAs($cashier)->post("/pos-sales/{$saleId}/void", [])->assertSessionHasErrors('reason');
        $this->actingAs($cashier)->post("/pos-sales/{$saleId}/void", ['reason' => 'กดผิด'])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('100.00', Wallet::where('student_id', $s->id)->value('balance'));
        $this->assertNotNull(WalletSale::find($saleId)->voided_at);

        $this->actingAs($cashier)->post("/pos-sales/{$saleId}/void", ['reason' => 'อีกครั้ง'])->assertSessionHas('warning');
        $this->assertSame('100.00', Wallet::where('student_id', $s->id)->value('balance'));

        // รายการของวันก่อน: คนขายยกเลิกไม่ได้ ผู้จัดการยกเลิกได้
        $old = $this->charge($cashier, $shop, $s, [['product_id' => $rice->id, 'qty' => 1]], 'key-yesterday')->json('sale_id');
        WalletSale::whereKey($old)->update(['created_at' => now()->subDay()]);
        $this->actingAs($cashier)->post("/pos-sales/{$old}/void", ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->admin())->post("/pos-sales/{$old}/void", ['reason' => 'แก้ย้อนหลัง'])->assertRedirect();
        $this->assertSame('100.00', Wallet::where('student_id', $s->id)->value('balance'));
    }

    public function test_only_assigned_cashiers_can_sell_at_a_shop(): void
    {
        [$shop, $rice] = $this->shop();
        $s = $this->child();
        WalletService::topupCash($s, 100, $this->admin());

        // ครูทั่วไปไม่มีสิทธิ์ขาย · คนขายของร้านอื่นขายร้านนี้ไม่ได้
        $teacher = User::where('username', 'teacher')->first();
        $this->actingAs($teacher)->get("/pos/{$shop->id}")->assertForbidden();
        $this->charge($teacher, $shop, $s, [['product_id' => $rice->id, 'qty' => 1]])->assertForbidden();
        $this->actingAs($teacher)->get('/wallets')->assertForbidden();
        $this->actingAs($teacher)->post('/wallets/topup', ['student_id' => $s->id, 'amount' => 999])->assertForbidden();

        $other = User::where('username', 't4')->first();
        $other->roles()->sync([Role::where('key', 'cashier')->first()->id]);
        $this->actingAs($other->fresh())->get("/pos/{$shop->id}")->assertForbidden();
        $this->actingAs($other->fresh())->get('/pos')->assertOk()->assertSee('ยังไม่มีร้านที่คุณขายได้');

        // สินค้าของร้านอื่นใช้ที่ร้านนี้ไม่ได้
        $elsewhere = Shop::create(['name' => 'สหกรณ์'])->products()->create(['name' => 'สมุด', 'price' => 12]);
        $this->charge($this->admin(), $shop, $s, [['product_id' => $elsewhere->id, 'qty' => 1]])->assertStatus(422);
        $this->assertSame('100.00', Wallet::where('student_id', $s->id)->value('balance'));
    }

    public function test_parent_tops_up_by_transfer_slip_and_finance_approves(): void
    {
        Storage::fake('local');
        \App\Support\Settings::set(['promptpay_id' => '0812345678']);
        $s = $this->child();
        $parent = $this->parentOf($s);

        $this->actingAs($parent)->get("/parent/wallet/{$s->id}")->assertOk()->assertSee('กระเป๋าเงิน')->assertSee('เลือกจำนวนเงิน');
        $this->actingAs($parent)->get("/parent/wallet/{$s->id}?amount=200")->assertOk()->assertSee('สแกนจ่าย');

        $slip = UploadedFile::fake()->image('slip.jpg');
        $this->actingAs($parent)->post("/parent/wallet/{$s->id}/topup", ['amount' => 200, 'slip' => $slip])->assertRedirect();
        $topup = WalletTopup::sole();
        $this->assertSame('pending', $topup->status);
        $this->assertSame('0.00', Wallet::where('student_id', $s->id)->value('balance'));

        // สลิปใบเดิมส่งซ้ำไม่ได้
        $this->actingAs($parent)->post("/parent/wallet/{$s->id}/topup", ['amount' => 200, 'slip' => $slip])->assertSessionHasErrors('slip');

        $this->actingAs($this->admin())->get('/wallets')->assertOk()->assertSee($s->fullName())->assertSee('ดูสลิป');
        $this->actingAs($this->admin())->get("/files/wallet-slip/{$topup->id}")->assertOk();
        $this->actingAs($this->admin())->post("/wallets/topups/{$topup->id}/approve")->assertRedirect();
        $this->assertSame('200.00', Wallet::where('student_id', $s->id)->value('balance'));
        // อนุมัติซ้ำไม่เพิ่มเงิน
        $this->actingAs($this->admin())->post("/wallets/topups/{$topup->id}/approve")->assertSessionHas('warning');
        $this->assertSame('200.00', Wallet::where('student_id', $s->id)->value('balance'));

        // ผู้ปกครองคนอื่นดูกระเป๋าหรือเติมให้เด็กคนนี้ไม่ได้
        $stranger = User::where('role', 'parent')->whereDoesntHave('children', fn ($q) => $q->whereKey($s->id))->first();
        $this->actingAs($stranger)->get("/parent/wallet/{$s->id}")->assertForbidden();
        $this->actingAs($stranger)->put("/parent/wallet/{$s->id}/settings", ['is_frozen' => 1])->assertForbidden();
        $this->actingAs($stranger)->get("/files/wallet-slip/{$topup->id}")->assertForbidden();
    }

    public function test_rejected_slip_adds_no_money(): void
    {
        $s = $this->child();
        $topup = WalletService::for($s)->topups()->create(['amount' => 500, 'method' => 'transfer', 'status' => 'pending']);
        $this->actingAs($this->admin())->post("/wallets/topups/{$topup->id}/reject", ['note' => 'ยอดไม่ตรง'])->assertRedirect();
        $this->assertSame('rejected', $topup->fresh()->status);
        $this->actingAs($this->admin())->post("/wallets/topups/{$topup->id}/approve")->assertSessionHas('warning');
        $this->assertSame('0.00', Wallet::where('student_id', $s->id)->value('balance'));
    }

    public function test_student_sees_own_wallet_read_only(): void
    {
        $s = $this->child();
        WalletService::topupCash($s, 50, $this->admin());
        $u = $s->user ?? User::create(['name' => $s->fullName(), 'username' => 'stu-wallet', 'password' => 'secret123', 'role' => 'student', 'is_active' => true]);
        $s->update(['user_id' => $u->id]);

        $this->actingAs($u->fresh())->get('/me/wallet')->assertOk()->assertSee('50.00')->assertDontSee('ควบคุมการใช้จ่าย')->assertDontSee('ส่งสลิป');
        $this->actingAs($u->fresh())->put("/parent/wallet/{$s->id}/settings", ['is_frozen' => 1])->assertForbidden();
    }

    public function test_adjust_withdraw_and_report(): void
    {
        [$shop, $rice, , $cashier] = $this->shop();
        $s = $this->child();
        WalletService::topupCash($s, 100, $this->admin());
        $this->charge($cashier, $shop, $s, [['product_id' => $rice->id, 'qty' => 2]])->assertOk();

        // ถอนเกินยอดไม่ได้
        $this->actingAs($this->admin())->post("/wallets/students/{$s->id}/adjust", ['type' => 'withdraw', 'amount' => 80, 'note' => 'ย้ายออก'])->assertSessionHas('warning');
        $this->actingAs($this->admin())->post("/wallets/students/{$s->id}/adjust", ['type' => 'withdraw', 'amount' => 50, 'note' => 'ย้ายออก'])->assertSessionHas('success');
        $this->assertSame('0.00', Wallet::where('student_id', $s->id)->value('balance'));
        $this->actingAs($this->admin())->get("/wallets/students/{$s->id}")->assertOk()->assertSee('ถอนคืน')->assertSee('ย้ายออก');

        $report = $this->actingAs($this->admin())->get('/wallets/report')->assertOk();
        $report->assertSee('ร้านข้าวแกง')->assertSee('ข้าวราดแกง')->assertSee('50.00')->assertSee('100.00');
    }

    public function test_finance_manages_shops_and_products(): void
    {
        $this->actingAs($this->admin())->post('/wallets/shops', ['name' => 'สหกรณ์โรงเรียน', 'location' => 'อาคาร 1'])->assertRedirect();
        $shop = Shop::where('name', 'สหกรณ์โรงเรียน')->first();
        $cashier = User::where('username', 't5')->first();

        $this->actingAs($this->admin())->post("/wallets/shops/{$shop->id}/products", ['name' => 'ปากกา', 'price' => 10, 'category' => 'เครื่องเขียน'])->assertRedirect();
        $product = $shop->products()->first();
        $this->actingAs($this->admin())->put("/wallets/products/{$product->id}", ['name' => 'ปากกาน้ำเงิน', 'price' => 12, 'is_active' => 0])->assertRedirect();
        $this->assertSame(['ปากกาน้ำเงิน', '12.00', false], [$product->fresh()->name, $product->fresh()->price, $product->fresh()->is_active]);

        $this->actingAs($this->admin())->put("/wallets/shops/{$shop->id}", ['name' => 'สหกรณ์', 'is_active' => 1, 'cashiers' => [$cashier->id]])->assertRedirect();
        $this->assertTrue($shop->cashiers()->whereKey($cashier->id)->exists());
        $this->actingAs($this->admin())->get("/wallets/shops/{$shop->id}")->assertOk()->assertSee('ปากกาน้ำเงิน')->assertSee('งดขาย');

        // สินค้าที่งดขายไม่ขึ้นหน้าจอขาย และตัดเงินไม่ได้
        $this->actingAs($this->admin())->get("/pos/{$shop->id}")->assertOk()->assertDontSee('ปากกาน้ำเงิน');
        $this->actingAs($this->admin())->delete("/wallets/products/{$product->id}")->assertRedirect();
        $this->assertSame(0, $shop->products()->count());
    }
}
