<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\Student;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletSale;
use App\Models\WalletTopup;
use App\Services\WalletService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** กระเป๋าเงินของครู การรับเงินด้วย QR พร้อมเพย์ที่หน้าจอขาย และหน้าจอลูกค้า */
class WalletStaffQrDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    private function teacher(): User
    {
        return User::where('username', 'teacher')->first();
    }

    private function shop(): array
    {
        $shop = Shop::create(['name' => 'ร้านข้าวแกง']);

        return [$shop, $shop->products()->create(['name' => 'ข้าวราดแกง', 'price' => 25, 'stock' => 5])];
    }

    public function test_teacher_has_own_wallet_tops_up_and_pays_with_staff_code(): void
    {
        Storage::fake('local');
        Settings::set(['promptpay_id' => '0812345678']);
        [$shop, $rice] = $this->shop();
        $t = $this->teacher();   // รหัสบุคลากร 90001 จากข้อมูลตัวอย่าง

        // เจ้าตัวเปิดกระเป๋าของตัวเอง ส่งสลิป และตั้งวงเงิน
        $this->actingAs($t)->get('/my-wallet')->assertOk()->assertSee('กระเป๋าเงิน')->assertSee($t->name)->assertSee('ควบคุมการใช้จ่าย');
        $this->actingAs($t)->get('/my-wallet?amount=300')->assertOk()->assertSee('สแกนจ่าย');
        $this->actingAs($t)->post('/my-wallet/topup', ['amount' => 300, 'slip' => UploadedFile::fake()->image('slip.jpg')])->assertRedirect('/my-wallet');
        $topup = WalletTopup::sole();
        $this->assertSame($t->id, $topup->wallet->user_id);
        $this->assertNull($topup->wallet->student_id);

        // การเงินเห็นในรายการรอตรวจ เปิดสลิปได้ และอนุมัติ
        $this->actingAs($this->admin())->get('/wallets')->assertOk()->assertSee($t->name)->assertSee('ครูและบุคลากร');
        $this->actingAs($this->admin())->get("/files/wallet-slip/{$topup->id}")->assertOk();
        $this->actingAs($t)->get("/files/wallet-slip/{$topup->id}")->assertOk();
        $this->actingAs(User::where('username', 't2')->first())->get("/files/wallet-slip/{$topup->id}")->assertForbidden();
        $this->actingAs($this->admin())->post("/wallets/topups/{$topup->id}/approve")->assertRedirect()->assertSessionHas('success');
        $this->assertSame('300.00', Wallet::where('user_id', $t->id)->value('balance'));

        // เติมเงินสดให้ครูที่ห้องการเงิน (เลือกจากรายการ หรือสแกนรหัสบุคลากร)
        $this->actingAs($this->admin())->postJson('/wallets/lookup', ['code' => '90001'])->assertOk()->assertJsonPath('key', 'u:'.$t->id);
        $this->actingAs($this->admin())->post('/wallets/topup', ['owner' => 'u:'.$t->id, 'amount' => 50])->assertSessionHas('success');
        $this->assertSame('350.00', Wallet::where('user_id', $t->id)->value('balance'));

        // ซื้อของที่ร้านด้วยรหัสบุคลากร
        $this->actingAs($this->admin())->postJson("/pos/{$shop->id}/lookup", ['code' => '90001'])
            ->assertOk()->assertJsonPath('student.type', 'staff')->assertJsonPath('student.id', $t->id)->assertJsonPath('student.balance', 350);
        $this->actingAs($this->admin())->postJson("/pos/{$shop->id}/charge", ['staff_id' => $t->id, 'client_key' => 'staff-key-001', 'items' => [['product_id' => $rice->id, 'qty' => 2]]])
            ->assertOk()->assertJsonPath('balance', 300);
        $sale = WalletSale::sole();
        $this->assertSame($t->name, $sale->customerName());
        $this->actingAs($this->admin())->get("/pos/{$shop->id}")->assertOk()->assertSee($t->name);
        $this->actingAs($this->admin())->get("/pos-sales/{$sale->id}/receipt")->assertOk()->assertSee($t->name);
        $this->actingAs($this->admin())->get('/wallets/report')->assertOk()->assertSee($t->name);

        // ผู้ปกครองและนักเรียนเป็นลูกค้าแบบบุคลากรไม่ได้
        $parent = User::where('role', 'parent')->first();
        $this->actingAs($this->admin())->postJson("/pos/{$shop->id}/charge", ['staff_id' => $parent->id, 'client_key' => 'staff-key-002', 'items' => [['product_id' => $rice->id, 'qty' => 1]]])->assertNotFound();
        $this->actingAs($parent)->get('/my-wallet')->assertForbidden();

        // วงเงินต่อวันและการระงับของครู
        $this->actingAs($t)->put('/my-wallet/settings', ['daily_limit' => 60])->assertRedirect();
        $this->actingAs($this->admin())->postJson("/pos/{$shop->id}/charge", ['staff_id' => $t->id, 'client_key' => 'staff-key-003', 'items' => [['product_id' => $rice->id, 'qty' => 1]]])->assertStatus(422);

        // หน้ากระเป๋าของครูในมุมการเงิน และถอนคืน
        $this->actingAs($this->admin())->get("/wallets/staff/{$t->id}")->assertOk()->assertSee($t->name)->assertSee('90001');
        $this->actingAs($this->admin())->post("/wallets/staff/{$t->id}/adjust", ['type' => 'withdraw', 'amount' => 300, 'note' => 'ลาออก'])->assertSessionHas('success');
        $this->assertSame('0.00', Wallet::where('user_id', $t->id)->value('balance'));
        $this->actingAs($this->admin())->get('/wallets/staff/'.$parent->id)->assertNotFound();
    }

    public function test_goods_are_paid_from_wallets_only_and_promptpay_is_for_top_ups(): void
    {
        Settings::set(['promptpay_id' => '0812345678']);
        [$shop, $rice] = $this->shop();
        $items = [['product_id' => $rice->id, 'qty' => 1]];

        // หน้าจอขายมีแต่การจ่ายจากกระเป๋าเงิน ไม่มีทางรับเงินด้วยพร้อมเพย์
        $this->actingAs($this->admin())->get("/pos/{$shop->id}")->assertOk()->assertSee('ให้ลูกค้าสแกนจ่าย')->assertDontSee('พร้อมเพย์');
        $this->actingAs($this->admin())->postJson("/pos/{$shop->id}/qr", ['amount' => 25])->assertNotFound();
        $this->actingAs($this->admin())->postJson("/pos/{$shop->id}/qr-paid", ['client_key' => 'qr-key-0001', 'items' => $items])->assertNotFound();
        $this->actingAs($this->admin())->get("/wallets/shops/{$shop->id}")->assertOk()->assertDontSee('พร้อมเพย์');
        $this->assertSame(0, WalletSale::count());

        // พร้อมเพย์ใช้ที่หน้าเติมเงินของเจ้าของกระเป๋า
        $this->actingAs($this->teacher())->get('/my-wallet?amount=100')->assertOk()->assertSee('พร้อมเพย์ 0812345678');
    }

    public function test_customer_display_shows_what_the_cashier_pushes(): void
    {
        [$shop] = $this->shop();
        $s = Student::active()->first();
        $state = ['status' => 'cart', 'total' => 50, 'items' => [['name' => 'ข้าวราดแกง', 'price' => 25, 'qty' => 2]],
            'customer' => ['name' => $s->fullName(), 'sub' => 'ม.1/1', 'initials' => 'ก', 'balance' => 120], 'balance_after' => 70];

        $this->actingAs($this->admin())->get("/pos/{$shop->id}/display")->assertOk()->assertSee('ร้านข้าวแกง');
        $this->actingAs($this->admin())->getJson("/pos/{$shop->id}/display/state")->assertOk()->assertJsonPath('status', 'idle');

        $this->actingAs($this->admin())->postJson("/pos/{$shop->id}/display", $state)->assertOk();
        $this->actingAs($this->admin())->getJson("/pos/{$shop->id}/display/state")
            ->assertJsonPath('status', 'cart')->assertJsonPath('total', 50)->assertJsonPath('customer.name', $s->fullName())->assertJsonPath('customer.balance', 120);

        // ร้านที่ตั้งไม่ให้แสดงยอดคงเหลือ: ยอดไม่ถูกส่งไปหน้าจอลูกค้า
        $shop->update(['show_balance' => false]);
        $this->actingAs($this->admin())->postJson("/pos/{$shop->id}/display", $state)->assertOk();
        $shown = $this->actingAs($this->admin())->getJson("/pos/{$shop->id}/display/state")->json();
        $this->assertArrayNotHasKey('balance', $shown['customer']);
        $this->assertArrayNotHasKey('balance_after', $shown);
        $this->assertSame($s->fullName(), $shown['customer']['name']);

        // หน้าจอลูกค้าแยกตามบัญชีคนขาย และคนที่ไม่ได้ขายร้านนี้เปิดไม่ได้
        $this->actingAs($this->admin())->postJson("/pos/{$shop->id}/display", ['status' => 'bogus'])->assertStatus(422);
        $this->actingAs($this->teacher())->get("/pos/{$shop->id}/display")->assertForbidden();
        $this->actingAs($this->teacher())->getJson("/pos/{$shop->id}/display/state")->assertForbidden();
        $this->actingAs($this->teacher())->postJson("/pos/{$shop->id}/display", $state)->assertForbidden();
    }
}
