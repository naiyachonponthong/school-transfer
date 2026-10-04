<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Shop;
use App\Models\Student;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTopup;
use App\Services\WalletService;
use App\Support\PromptPay;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** กระเป๋าเงินระยะที่ 2–3: บัตรแตะ ใบเสร็จ รายละเอียดและสต็อกสินค้า เติมเงินอัตโนมัติผ่านธนาคาร */
class WalletPhase23Test extends TestCase
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

    private function charge(Shop $shop, Student $s, array $items, string $key)
    {
        return $this->actingAs($this->admin())->postJson("/pos/{$shop->id}/charge", ['student_id' => $s->id, 'client_key' => $key, 'items' => $items]);
    }

    public function test_tap_cards_are_bound_per_classroom_and_work_everywhere_a_card_is_scanned(): void
    {
        $s = $this->child();
        $mate = Student::active()->where('classroom_id', $s->classroom_id)->where('id', '!=', $s->id)->first();
        $shop = Shop::create(['name' => 'ร้าน']);

        $this->actingAs($this->admin())->get('/wallets/cards?classroom='.$s->classroom_id)->assertOk()->assertSee($s->fullName());
        $this->actingAs($this->admin())->post('/wallets/cards', ['cards' => [$s->id => '04a1b2c3d4', $mate->id => '']])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('04A1B2C3D4', $s->fresh()->card_uid);

        // บัตรใบเดียวผูกได้คนเดียว และห้ามซ้ำกับรหัสนักเรียนของใคร
        $this->actingAs($this->admin())->post('/wallets/cards', ['cards' => [$s->id => 'AAA111', $mate->id => 'AAA111']])->assertSessionHas('warning');
        $this->actingAs($this->admin())->post('/wallets/cards', ['cards' => [$mate->id => $s->student_code]])->assertSessionHas('warning');
        $this->assertSame('04A1B2C3D4', $s->fresh()->card_uid);

        // แตะบัตรที่หน้าจอขาย ที่ช่องเติมเงิน และที่จุดสแกนหน้าประตู
        $this->actingAs($this->admin())->postJson("/pos/{$shop->id}/lookup", ['code' => '04A1B2C3D4'])->assertOk()->assertJsonPath('student.id', $s->id);
        $this->actingAs($this->admin())->postJson('/wallets/lookup', ['code' => '04A1B2C3D4'])->assertOk()->assertJsonPath('id', $s->id);
        $this->actingAs($this->admin())->postJson('/wallets/lookup', ['code' => 'ZZZ'])->assertNotFound();
        Attendance::where('student_id', $s->id)->where('date', today()->toDateString())->delete();
        $this->travelTo(today()->setTime(7, 30));
        $this->actingAs($this->admin())->postJson('/gate/scan', ['code' => '04A1B2C3D4'])->assertOk()->assertJsonPath('kind', 'present');

        // สลับบัตรระหว่างสองคนในครั้งเดียวได้ และล้างช่อง = ยกเลิกบัตร
        $this->actingAs($this->admin())->post('/wallets/cards', ['cards' => [$s->id => 'BBB222', $mate->id => '04A1B2C3D4']])->assertSessionHas('success');
        $this->assertSame(['BBB222', '04A1B2C3D4'], [$s->fresh()->card_uid, $mate->fresh()->card_uid]);
        $this->actingAs($this->admin())->post('/wallets/cards', ['cards' => [$s->id => '', $mate->id => '04A1B2C3D4']])->assertSessionHas('success');
        $this->assertNull($s->fresh()->card_uid);

        $this->actingAs(User::where('username', 'teacher')->first())->get('/wallets/cards')->assertForbidden();
    }

    public function test_product_details_image_stock_and_gross_profit(): void
    {
        Storage::fake('public');
        $shop = Shop::create(['name' => 'สหกรณ์']);
        $s = $this->child();
        WalletService::topupCash($s, 200, $this->admin());

        $this->actingAs($this->admin())->post("/wallets/shops/{$shop->id}/products", [
            'name' => 'นมกล่อง', 'price' => 12, 'cost' => 9, 'stock' => 3, 'unit' => 'กล่อง', 'barcode' => '8850000000017',
            'description' => 'นมจืด 200 มล.', 'category' => 'เครื่องดื่ม', 'image' => UploadedFile::fake()->image('milk.jpg'),
        ])->assertRedirect();
        $milk = $shop->products()->sole();
        $this->assertSame([3, '9.00', 'กล่อง', '8850000000017'], [$milk->stock, $milk->cost, $milk->unit, $milk->barcode]);
        Storage::disk('public')->assertExists($milk->image);

        $this->actingAs($this->admin())->get("/wallets/shops/{$shop->id}")->assertOk()->assertSee('8850000000017')->assertSee('นมจืด 200 มล.');
        $this->actingAs($this->admin())->get("/pos/{$shop->id}")->assertOk()->assertSee('data-barcode="8850000000017"', false)->assertSee('เหลือ 3')->assertSee($milk->imageUrl(), false);

        // ขายแล้วตัดสต็อก · เกินสต็อกขายไม่ได้ · ยกเลิกแล้วคืนสต็อก
        $saleId = $this->charge($shop, $s, [['product_id' => $milk->id, 'qty' => 2]], 'stock-key-0001')->assertOk()->json('sale_id');
        $this->assertSame(1, $milk->fresh()->stock);
        $this->charge($shop, $s, [['product_id' => $milk->id, 'qty' => 2]], 'stock-key-0002')->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertSame(1, $milk->fresh()->stock);
        $this->assertSame('176.00', Wallet::where('student_id', $s->id)->value('balance'));

        // รายงาน: กำไรขั้นต้น = (12 - 9) × 2
        $this->actingAs($this->admin())->get('/wallets/report')->assertOk()->assertSee('กำไรขั้นต้น')->assertSee('6.00');

        $this->actingAs($this->admin())->post("/pos-sales/{$saleId}/void", ['reason' => 'คืนของ'])->assertRedirect();
        $this->assertSame(3, $milk->fresh()->stock);

        // แก้สินค้า: เว้นสต็อกว่าง = ไม่นับ และลบรูปได้
        $path = $milk->image;
        $this->actingAs($this->admin())->put("/wallets/products/{$milk->id}", ['name' => 'นมกล่อง', 'price' => 12, 'is_active' => 1, 'remove_image' => 1])->assertRedirect();
        $this->assertNull($milk->fresh()->stock);
        $this->assertNull($milk->fresh()->image);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_receipt_prints_for_the_shop_and_not_for_outsiders(): void
    {
        $shop = Shop::create(['name' => 'ร้านข้าวแกง']);
        $rice = $shop->products()->create(['name' => 'ข้าวราดแกง', 'price' => 25]);
        $s = $this->child();
        WalletService::topupCash($s, 100, $this->admin());
        $saleId = $this->charge($shop, $s, [['product_id' => $rice->id, 'qty' => 2]], 'receipt-key-01')->json('sale_id');

        $this->actingAs($this->admin())->get("/pos-sales/{$saleId}/receipt")->assertOk()
            ->assertSee('ร้านข้าวแกง')->assertSee('ข้าวราดแกง')->assertSee('50.00')->assertSee($s->fullName())->assertSee('คงเหลือหลังซื้อ');
        $this->actingAs(User::where('username', 'teacher')->first())->get("/pos-sales/{$saleId}/receipt")->assertForbidden();
    }

    private function sign(string $body): array
    {
        return ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => hash_hmac('sha256', $body, Settings::get('wallet_gateway_secret'))];
    }

    public function test_bank_callback_tops_up_automatically_once(): void
    {
        $s = $this->child();
        $parent = $s->guardians()->first();

        // ยังไม่ตั้งค่า: ที่อยู่รับแจ้งผลปิดอยู่ และผู้ปกครองไม่เห็นการสแกนจ่ายอัตโนมัติ
        $this->postJson('/wallet/hook', ['reference' => 'X'])->assertNotFound();
        $this->actingAs($parent)->post("/parent/wallet/{$s->id}/auto", ['amount' => 100])->assertNotFound();

        $response = $this->actingAs($this->admin())->post('/wallets/gateway', ['wallet_biller_id' => '099400012345601'])->assertRedirect();
        $response->assertSessionHas('gateway_secret');
        $this->assertSame(48, strlen(Settings::get('wallet_gateway_secret')));
        // บันทึกซ้ำโดยไม่ติ๊กออกรหัสใหม่ รหัสลับเดิมยังอยู่
        $secret = Settings::get('wallet_gateway_secret');
        $this->actingAs($this->admin())->post('/wallets/gateway', ['wallet_biller_id' => '099400012345601'])->assertSessionMissing('gateway_secret');
        $this->assertSame($secret, Settings::get('wallet_gateway_secret'));
        $this->actingAs($this->admin())->post('/wallets/gateway', ['wallet_biller_id' => '123'])->assertSessionHasErrors('wallet_biller_id');

        // ผู้ปกครองเปิดรายการ ได้ QR ชำระบิลที่มีเลขอ้างอิง
        $this->actingAs($parent)->post("/parent/wallet/{$s->id}/auto", ['amount' => 150])->assertRedirect();
        $topup = WalletTopup::where('method', 'auto')->sole();
        $this->assertMatchesRegularExpression('/^W[A-Z0-9]{11}$/', $topup->reference);
        $qr = PromptPay::billPayment('099400012345601', $topup->reference, 150);
        $this->assertStringContainsString('A000000677010112', $qr);
        $this->assertStringContainsString($topup->reference, $qr);
        $this->assertStringContainsString('5406150.00', $qr);
        $this->actingAs($parent)->get("/parent/wallet/{$s->id}?topup={$topup->id}")->assertOk()->assertSee($topup->reference)->assertSee('รอการชำระ');
        $this->actingAs($parent)->getJson("/parent/wallet/{$s->id}/topups/{$topup->id}")->assertJsonPath('status', 'pending');
        auth()->logout();

        // ลายเซ็นผิด หรือยอดไม่ตรง = ไม่เติม
        $body = json_encode(['reference' => $topup->reference, 'amount' => 150, 'transaction_id' => 'TXN-001']);
        $this->call('POST', '/wallet/hook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => 'bad'], $body)->assertForbidden();
        $wrong = json_encode(['reference' => $topup->reference, 'amount' => 15, 'transaction_id' => 'TXN-000']);
        $this->call('POST', '/wallet/hook', [], [], [], $this->sign($wrong), $wrong)->assertStatus(422);
        $this->assertSame('0.00', Wallet::where('student_id', $s->id)->value('balance'));

        // แจ้งผลถูกต้อง: เงินเข้า และธนาคารส่งซ้ำก็ไม่เข้าซ้ำ
        $this->call('POST', '/wallet/hook', [], [], [], $this->sign($body), $body)->assertOk()->assertJsonPath('result', 'approved');
        $this->call('POST', '/wallet/hook', [], [], [], $this->sign($body), $body)->assertOk()->assertJsonPath('result', 'duplicate');
        $again = json_encode(['reference' => $topup->reference, 'amount' => 150, 'transaction_id' => 'TXN-002']);
        $this->call('POST', '/wallet/hook', [], [], [], $this->sign($again), $again)->assertOk()->assertJsonPath('result', 'already processed');
        $this->assertSame('150.00', Wallet::where('student_id', $s->id)->value('balance'));
        $this->assertSame(['approved', 'TXN-001'], [$topup->fresh()->status, $topup->fresh()->gateway_txn]);

        $unknown = json_encode(['reference' => 'WNOPE0000000', 'amount' => 150, 'transaction_id' => 'TXN-003']);
        $this->call('POST', '/wallet/hook', [], [], [], $this->sign($unknown), $unknown)->assertNotFound();

        $this->actingAs($parent)->get("/parent/wallet/{$s->id}?topup={$topup->id}")->assertOk()->assertSee('เข้ากระเป๋าแล้ว');
        // ผู้ปกครองคนอื่นถามสถานะรายการนี้ไม่ได้
        $stranger = User::where('role', 'parent')->whereDoesntHave('children', fn ($q) => $q->whereKey($s->id))->first();
        $this->actingAs($stranger)->getJson("/parent/wallet/{$s->id}/topups/{$topup->id}")->assertForbidden();
    }
}
