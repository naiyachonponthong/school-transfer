<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\Student;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletSale;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** สแกนจ่ายจากกระเป๋าเงิน: ร้านเปิดรายการเป็น QR ลูกค้าสแกนจากหน้ากระเป๋าเงินแล้วยืนยันตัดเงินเอง */
class WalletScanPayTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    private function child(): Student
    {
        return User::where('phone', '0812345678')->first()->children()->first();
    }

    private function studentUser(Student $s): User
    {
        $u = $s->user ?? User::create(['name' => $s->fullName(), 'username' => 'stu-pay', 'password' => 'secret123', 'role' => 'student', 'is_active' => true]);
        $s->update(['user_id' => $u->id]);

        return $u->fresh();
    }

    /** ร้านเปิดรายการ 2 จาน (50 บาท) แล้วได้ token ของ QR */
    private function open(Shop $shop, int $productId, string $key = 'scan-key-0001'): string
    {
        $data = $this->actingAs($this->admin())->postJson("/pos/{$shop->id}/pay-request", ['client_key' => $key, 'items' => [['product_id' => $productId, 'qty' => 2]]])
            ->assertOk()->assertJsonPath('total', 50)->json();
        $this->assertSame(url('/pay/'.$data['token']), $data['url']);
        auth()->logout();

        return $data['token'];
    }

    public function test_student_scans_the_shop_qr_and_pays_from_own_wallet(): void
    {
        $shop = Shop::create(['name' => 'ร้านข้าวแกง']);
        $rice = $shop->products()->create(['name' => 'ข้าวราดแกง', 'price' => 25, 'stock' => 10]);
        $s = $this->child();
        $u = $this->studentUser($s);
        WalletService::topupCash($s, 120, $this->admin());

        // หน้ากระเป๋าเงินมีปุ่มสแกนจ่าย และหน้ากล้องเปิดได้
        $this->actingAs($u)->get('/me/wallet')->assertOk()->assertSee('สแกนจ่าย');
        $this->actingAs($u)->get('/wallet/scan')->assertOk()->assertSee('ส่องกล้องไปที่ QR');

        $token = $this->open($shop, $rice->id);
        // เปิดรายการเดิมซ้ำ ได้ QR เดิม
        $this->assertSame($token, $this->open($shop, $rice->id));
        $this->actingAs($this->admin())->getJson("/pos/{$shop->id}/pay-request/{$token}")->assertOk()->assertJsonPath('status', 'pending');

        // ลูกค้าสแกน: เห็นร้าน รายการ ยอด และกระเป๋าของตัวเอง แล้วยืนยัน
        $this->actingAs($u)->get("/pay/{$token}")->assertOk()->assertSee('ร้านข้าวแกง')->assertSee('ข้าวราดแกง')->assertSee('50.00')->assertSee('120.00');
        $this->actingAs($u)->post("/pay/{$token}", ['payer' => 's:'.$s->id])->assertRedirect("/pay/{$token}")->assertSessionHas('success');

        $this->assertSame('70.00', Wallet::where('student_id', $s->id)->value('balance'));
        $sale = WalletSale::sole();
        $this->assertSame(['wallet', '50.00', $this->admin()->id], [$sale->payment, $sale->total, $sale->cashier_id]);
        $this->assertSame(8, $rice->fresh()->stock);

        // หน้าจอขายเห็นว่าจ่ายแล้ว พร้อมชื่อลูกค้าและยอดคงเหลือ
        $this->actingAs($this->admin())->getJson("/pos/{$shop->id}/pay-request/{$token}")
            ->assertJsonPath('status', 'paid')->assertJsonPath('sale_id', $sale->id)->assertJsonPath('balance', 70)->assertJsonPath('customer.name', $s->fullName());

        // QR ใช้ได้ครั้งเดียว: กดยืนยันซ้ำหรือคนอื่นสแกนซ้ำ ไม่ตัดเงินอีก
        $this->actingAs($u)->get("/pay/{$token}")->assertOk()->assertSee('ชำระแล้ว');
        $this->actingAs($u)->post("/pay/{$token}", ['payer' => 's:'.$s->id])->assertSessionHas('warning');
        $this->assertSame('70.00', Wallet::where('student_id', $s->id)->value('balance'));
        $this->assertSame(1, WalletSale::count());
    }

    public function test_only_own_wallets_can_pay_and_wallet_rules_still_apply(): void
    {
        $shop = Shop::create(['name' => 'ร้านข้าวแกง']);
        $rice = $shop->products()->create(['name' => 'ข้าวราดแกง', 'price' => 25]);
        $s = $this->child();
        $u = $this->studentUser($s);
        $other = Student::active()->where('id', '!=', $s->id)->first();
        WalletService::topupCash($other, 500, $this->admin());
        WalletService::topupCash($s, 30, $this->admin());
        $token = $this->open($shop, $rice->id);

        // จ่ายจากกระเป๋าของคนอื่นไม่ได้
        $this->actingAs($u)->post("/pay/{$token}", ['payer' => 's:'.$other->id])->assertForbidden();
        $this->actingAs($u)->post("/pay/{$token}", ['payer' => 'u:'.$this->admin()->id])->assertForbidden();
        // ยอดไม่พอ: ไม่ตัดเงิน และรายการยังรออยู่ให้จ่ายใหม่ได้
        $this->actingAs($u)->post("/pay/{$token}", ['payer' => 's:'.$s->id])->assertSessionHas('warning');
        $this->assertSame(0, WalletSale::count());
        $this->actingAs($this->admin())->getJson("/pos/{$shop->id}/pay-request/{$token}")->assertJsonPath('status', 'pending');
        auth()->logout();

        // ผู้ปกครองจ่ายจากกระเป๋าของบุตรหลานได้ · ครูจ่ายจากกระเป๋าของตัวเองได้
        $parent = $s->guardians()->first();
        $this->actingAs($parent)->get("/pay/{$token}")->assertOk()->assertSee($s->fullName());
        WalletService::topupCash($s, 100, $this->admin());
        $this->actingAs($parent)->post("/pay/{$token}", ['payer' => 's:'.$s->id])->assertSessionHas('success');
        $this->assertSame('80.00', Wallet::where('student_id', $s->id)->value('balance'));

        $teacher = User::where('username', 'teacher')->first();
        WalletService::topupCash($teacher, 100, $this->admin());
        $token2 = $this->open($shop, $rice->id, 'scan-key-0002');
        $this->actingAs($teacher)->get("/pay/{$token2}")->assertOk()->assertSee($teacher->name);
        $this->actingAs($teacher)->post("/pay/{$token2}", ['payer' => 'u:'.$teacher->id])->assertSessionHas('success');
        $this->assertSame('50.00', Wallet::where('user_id', $teacher->id)->value('balance'));

        // QR ที่ไม่มีอยู่/หมดอายุ และต้องเข้าสู่ระบบก่อน
        $this->actingAs($u)->get('/pay/doesnotexist')->assertOk()->assertSee('หมดอายุ');
        $this->actingAs($u)->post('/pay/doesnotexist', ['payer' => 's:'.$s->id])->assertSessionHas('warning');
        auth()->logout();
        $this->get("/pay/{$token2}")->assertRedirect('/login');
        // คนที่ไม่ได้ขายร้านนี้ถามสถานะรายการไม่ได้
        $this->actingAs($teacher)->getJson("/pos/{$shop->id}/pay-request/{$token2}")->assertForbidden();
    }
}
