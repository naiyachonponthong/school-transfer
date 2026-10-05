<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CashClosing;
use App\Models\Classroom;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\Shop;
use App\Models\ShopSettlement;
use App\Models\Student;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletSale;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use App\Support\WalletReconciler;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** กระเป๋าเงิน: กันลบ จ่ายเงินร้านค้า ปิดยอดรวมเงินสดของกระเป๋า คืนเงินผู้พ้นสภาพ และกระทบยอด */
class WalletSafeguardsTest extends TestCase
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

    private function newStudent(string $code): Student
    {
        return Student::create(['student_code' => $code, 'first_name' => 'ทดสอบ', 'last_name' => $code, 'classroom_id' => Classroom::first()->id]);
    }

    /** ร้านพร้อมสินค้า 2 รายการ และคนขาย 1 คน */
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

    private function charge(User $cashier, Shop $shop, Student $s, array $items, string $key): int
    {
        return $this->actingAs($cashier)->postJson("/pos/{$shop->id}/charge", ['student_id' => $s->id, 'client_key' => $key, 'items' => $items])
            ->assertOk()->json('sale_id');
    }

    public function test_owner_with_wallet_history_cannot_be_deleted(): void
    {
        // กระเป๋าเปล่าที่ระบบสร้างไว้ตอนสแกนบัตร: ลบนักเรียนได้ กระเป๋าหายไปด้วย
        $empty = $this->newStudent('W9001');
        $emptyWallet = WalletService::for($empty);
        $this->actingAs($this->admin())->delete("/students/{$empty->id}")->assertRedirect(route('students.index'));
        $this->assertModelMissing($empty);
        $this->assertModelMissing($emptyWallet);

        // มีเงินในกระเป๋า: ลบไม่ได้ และบอกเหตุผล
        $funded = $this->newStudent('W9002');
        WalletService::topupCash($funded, 50, $this->admin());
        $this->actingAs($this->admin())->delete("/students/{$funded->id}")->assertSessionHasErrors('student');
        $this->assertStringContainsString('กระเป๋าเงิน', session('errors')->first('student'));
        $this->assertModelExists($funded);
        $this->assertSame('50.00', Wallet::where('student_id', $funded->id)->value('balance'));

        // ชั้นฐานข้อมูลก็กันไว้ แม้โค้ดส่วนอื่นสั่งลบตรง ๆ
        try {
            $funded->delete();
            $this->fail('ลบนักเรียนที่มีกระเป๋าเงินได้');
        } catch (QueryException) {
            $this->assertModelExists($funded);
        }

        // บัญชีผู้ใช้: เจ้าของกระเป๋าที่มีรายการ และคนขายที่เคยขาย ลบไม่ได้ ให้ปิดใช้งานแทน
        [$shop, $rice, , $cashier] = $this->shop();
        $this->charge($cashier, $shop, $funded, [['product_id' => $rice->id, 'qty' => 1]], 'key-guard-01');
        $this->actingAs($this->admin())->delete("/users/{$cashier->id}")->assertSessionHasErrors('user');
        $this->assertStringContainsString('ปิดสวิตช์', session('errors')->first('user'));
        $this->assertModelExists($cashier);

        $teacher = User::where('username', 't6')->first();
        WalletService::topupCash($teacher, 100, $this->admin());
        $this->actingAs($this->admin())->delete("/users/{$teacher->id}")->assertSessionHasErrors('user');
        $this->assertModelExists($teacher);

        // บัญชีที่ไม่มีประวัติยังลบได้ตามเดิม (รวมกระเป๋าเปล่า)
        $blank = User::create(['name' => 'บัญชีสร้างผิด', 'username' => 'mistake01', 'password' => 'secret123', 'role' => 'teacher', 'is_active' => true]);
        WalletService::for($blank);
        $this->actingAs($this->admin())->delete("/users/{$blank->id}")->assertSessionHasNoErrors();
        $this->assertModelMissing($blank);
    }

    public function test_settlement_pays_shop_net_of_fee_and_locks_its_sales(): void
    {
        [$shop, $rice, $water, $cashier] = $this->shop();
        $s = $this->child();
        WalletService::topupCash($s, 500, $this->admin());

        // ตั้งส่วนแบ่งของโรงเรียน 10% และช่องทางรับเงินของร้าน
        $this->actingAs($this->admin())->put("/wallets/shops/{$shop->id}", ['name' => $shop->name, 'is_active' => 1, 'cashiers' => [$cashier->id],
            'fee_percent' => 10, 'payout_account' => 'กสิกรไทย 123-4-56789-0'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(AuditLog::where('action', 'finance.settlement')->where('subject_id', $shop->id)->exists());

        $first = $this->charge($cashier, $shop, $s, [['product_id' => $rice->id, 'qty' => 2]], 'key-settle-01');
        $second = $this->charge($cashier, $shop, $s, [['product_id' => $water->id, 'qty' => 1]], 'key-settle-02');
        $voided = $this->charge($cashier, $shop, $s, [['product_id' => $rice->id, 'qty' => 1]], 'key-settle-03');
        $this->actingAs($cashier)->post("/pos-sales/{$voided}/void", ['reason' => 'กดผิด'])->assertSessionHas('success');

        // หน้าจ่ายเงิน: ยอดขาย 57 หัก 10% = 5.70 จ่ายให้ร้าน 51.30 (รายการที่ยกเลิกไม่นับ)
        $this->actingAs($this->admin())->get('/wallets/settlements')->assertOk()
            ->assertSee('ร้านข้าวแกง')->assertSee('57.00')->assertSee('5.70')->assertSee('51.30')->assertSee('กสิกรไทย 123-4-56789-0');
        $this->actingAs($this->admin())->get('/wallets')->assertOk()->assertSee('ยอดขายที่ยังไม่ได้จ่ายให้ร้านค้า 57.00 บาท');
        $this->actingAs($cashier)->get("/pos/{$shop->id}")->assertOk()->assertSee('ยอดขายที่รอรับเงินจากโรงเรียน 57.00 บาท');

        // เฉพาะผู้จัดการกระเป๋าเงินจ่ายเงินได้
        $this->actingAs($cashier)->get('/wallets/settlements')->assertForbidden();
        $this->actingAs($cashier)->post('/wallets/settlements', ['shop_id' => $shop->id, 'until' => today()->toDateString(), 'method' => 'cash'])->assertForbidden();

        $this->actingAs($this->admin())->post('/wallets/settlements', ['shop_id' => $shop->id, 'until' => today()->toDateString(), 'method' => 'transfer', 'note' => 'อ้างอิง 001'])
            ->assertRedirect()->assertSessionHas('success');
        $settlement = ShopSettlement::sole();
        $this->assertSame(['57.00', '10.00', '5.70', '51.30', 2, 'transfer'],
            [$settlement->gross, $settlement->fee_percent, $settlement->fee, $settlement->net, $settlement->sales_count, $settlement->method]);
        $this->assertStringStartsWith('SP'.now()->format('Ym'), $settlement->doc_no);
        $this->assertSame([$settlement->id, $settlement->id, null], WalletSale::whereKey([$first, $second, $voided])->orderBy('id')->pluck('settlement_id')->all());

        // กดจ่ายซ้ำ: ไม่มียอดค้าง ไม่ออกใบซ้ำ
        $this->actingAs($this->admin())->post('/wallets/settlements', ['shop_id' => $shop->id, 'until' => today()->toDateString(), 'method' => 'transfer'])->assertSessionHas('warning');
        $this->assertSame(1, ShopSettlement::count());

        // ใบจ่ายเงิน: ผู้จัดการและคนขายของร้านเปิดได้ คนอื่นเปิดไม่ได้
        $this->actingAs($this->admin())->get("/wallets/settlements/{$settlement->id}")->assertOk()->assertSee($settlement->doc_no)->assertSee('ห้าสิบเอ็ดบาทสามสิบสตางค์')->assertSee('ผู้รับเงิน (ร้านค้า)');
        $this->actingAs($cashier)->get("/wallets/settlements/{$settlement->id}")->assertOk()->assertDontSee('ยกเลิกใบนี้');
        $this->actingAs(User::where('username', 'teacher')->first())->get("/wallets/settlements/{$settlement->id}")->assertForbidden();

        // รายการขายที่จ่ายเงินให้ร้านแล้วยกเลิกไม่ได้ เงินจึงไม่ออกสองทาง
        $balance = Wallet::where('student_id', $s->id)->value('balance');
        $this->actingAs($this->admin())->post("/pos-sales/{$first}/void", ['reason' => 'ขอคืนเงิน'])->assertSessionHas('warning');
        $this->assertNull(WalletSale::find($first)->voided_at);
        $this->assertSame($balance, Wallet::where('student_id', $s->id)->value('balance'));

        // ขายเพิ่มหลังจ่ายเงิน: ยอดค้างจ่ายใหม่มีเฉพาะรายการนั้น
        $this->charge($cashier, $shop, $s, [['product_id' => $water->id, 'qty' => 2]], 'key-settle-04');
        $this->assertEquals(14.0, (float) $shop->unsettledSales()->sum('total'));

        // ยกเลิกใบจ่ายเงิน: ต้องมีเหตุผล รายการขายกลับไปเป็นยอดค้างจ่าย เลขที่ใบไม่ถูกใช้ซ้ำ
        $this->actingAs($this->admin())->post("/wallets/settlements/{$settlement->id}/void", [])->assertSessionHasErrors('reason');
        $this->actingAs($this->admin())->post("/wallets/settlements/{$settlement->id}/void", ['reason' => 'โอนผิดบัญชี'])->assertRedirect(route('wallets.settlements'));
        $this->assertTrue($settlement->fresh()->isVoided());
        $this->assertEquals(71.0, (float) $shop->unsettledSales()->sum('total'));
        $this->actingAs($this->admin())->post("/wallets/settlements/{$settlement->id}/void", ['reason' => 'อีกครั้ง'])->assertSessionHas('warning');

        $this->actingAs($this->admin())->post('/wallets/settlements', ['shop_id' => $shop->id, 'until' => today()->toDateString(), 'method' => 'cash'])->assertSessionHas('success');
        $again = ShopSettlement::valid()->sole();
        $this->assertNotSame($settlement->doc_no, $again->doc_no);
        $this->assertSame(['71.00', '63.90', 3], [$again->gross, $again->net, $again->sales_count]);
        $this->assertSame([], WalletReconciler::check());
    }

    public function test_settlement_only_takes_sales_up_to_the_chosen_date(): void
    {
        [$shop, $rice, , $cashier] = $this->shop();
        $s = $this->child();
        WalletService::topupCash($s, 200, $this->admin());
        $old = $this->charge($cashier, $shop, $s, [['product_id' => $rice->id, 'qty' => 1]], 'key-date-01');
        WalletSale::whereKey($old)->update(['created_at' => now()->subDays(3)]);
        $today = $this->charge($cashier, $shop, $s, [['product_id' => $rice->id, 'qty' => 2]], 'key-date-02');

        $until = today()->subDay()->toDateString();
        $this->actingAs($this->admin())->get('/wallets/settlements?until='.$until)->assertOk()->assertSee('25.00')->assertDontSee('75.00');
        $this->actingAs($this->admin())->post('/wallets/settlements', ['shop_id' => $shop->id, 'until' => $until, 'method' => 'cash'])->assertSessionHas('success');

        $settlement = ShopSettlement::sole();
        $this->assertSame(['25.00', '25.00', 1], [$settlement->gross, $settlement->net, $settlement->sales_count]);
        $this->assertNull(WalletSale::find($today)->settlement_id);
        // วันที่ในอนาคตใช้ไม่ได้
        $this->actingAs($this->admin())->post('/wallets/settlements', ['shop_id' => $shop->id, 'until' => today()->addDay()->toDateString(), 'method' => 'cash'])->assertSessionHasErrors('until');
    }

    public function test_daily_closing_counts_wallet_cash_in_the_remittance(): void
    {
        [$shop, $rice, , $cashier] = $this->shop();
        $s = $this->child();
        $admin = $this->admin();

        // ค่าธรรมเนียมรับเป็นเงินสด 100
        $invoice = Invoice::whereIn('status', ['unpaid', 'partial'])->get()->first(fn (Invoice $i) => $i->balance() >= 100);
        $this->actingAs($admin)->post("/invoices/{$invoice->id}/payments", ['amount' => 100, 'method' => 'cash'])->assertRedirect();

        // กระเป๋าเงิน: เติมเงินสด 300 · ถอนคืนเงินสด 50 · ถอนคืนด้วยการโอน 20 (ไม่ผ่านลิ้นชัก) · จ่ายร้านค้าเงินสด 25
        $this->actingAs($admin)->post('/wallets/topup', ['student_id' => $s->id, 'amount' => 300])->assertSessionHas('success');
        $this->actingAs($admin)->post("/wallets/students/{$s->id}/adjust", ['type' => 'withdraw', 'amount' => 50, 'note' => 'ผู้ปกครองขอคืน', 'method' => 'cash'])->assertSessionHas('success');
        $this->actingAs($admin)->post("/wallets/students/{$s->id}/adjust", ['type' => 'withdraw', 'amount' => 20, 'note' => 'โอนคืน', 'method' => 'transfer'])->assertSessionHas('success');
        $this->assertSame(['cash', 'transfer'], WalletTransaction::where('type', 'withdraw')->orderBy('id')->pluck('method')->all());
        $this->charge($cashier, $shop, $s, [['product_id' => $rice->id, 'qty' => 1]], 'key-close-01');
        $this->actingAs($admin)->post('/wallets/settlements', ['shop_id' => $shop->id, 'until' => today()->toDateString(), 'method' => 'cash'])->assertSessionHas('success');
        $settlement = ShopSettlement::sole();

        // เงินสดที่ต้องนำส่ง = 100 + 300 − 50 − 25 = 325
        $this->actingAs($admin)->get('/finance/closing')->assertOk()
            ->assertSee('เติมเงินสดเข้ากระเป๋า')->assertSee('+300.00')->assertSee('ถอนเงินคืนจากกระเป๋า')->assertSee('จ่ายเงินร้านค้า '.$settlement->doc_no)
            ->assertSee('เงินสดที่ต้องนำส่ง 325.00 บาท');

        $this->actingAs($admin)->post('/finance/closing', ['date' => today()->toDateString()])->assertSessionHas('success');
        $closing = CashClosing::sole();
        $this->assertSame([300.0, 50.0, 25.0, 325.0], [$closing->wallet_cash_in, $closing->wallet_cash_out, $closing->shop_cash_out, $closing->cashToRemit()]);
        $this->actingAs($admin)->get('/finance/closing')->assertOk()->assertSee('เงินสดนำส่ง 325.00 บาท')->assertDontSee('ยอดปัจจุบันไม่ตรงกับยอดตอนปิด');

        // ปิดยอดแล้วยกเลิกใบจ่ายเงินสดของวันนั้นไม่ได้ · มีเงินสดเข้าหลังปิดยอด หน้าปิดยอดเตือนว่าไม่ตรง
        $this->actingAs($admin)->post("/wallets/settlements/{$settlement->id}/void", ['reason' => 'แก้'])->assertStatus(422);
        $this->assertFalse($settlement->fresh()->isVoided());
        $this->actingAs($admin)->post('/wallets/topup', ['student_id' => $s->id, 'amount' => 40]);
        $this->actingAs($admin)->get('/finance/closing')->assertOk()->assertSee('ยอดปัจจุบันไม่ตรงกับยอดตอนปิด');
    }

    public function test_leavers_with_money_are_listed_and_refunded_in_one_go(): void
    {
        $admin = $this->admin();
        $leaver = $this->child();
        WalletService::topupCash($leaver, 120, $admin);
        $active = $this->newStudent('W9100');
        WalletService::topupCash($active, 70, $admin);
        $staff = User::where('username', 't6')->first();
        WalletService::topupCash($staff, 80, $admin);

        // ยังไม่มีใครพ้นสภาพ
        $this->actingAs($admin)->get('/wallets/leavers')->assertOk()->assertSee('ไม่มีเงินค้างคืน');

        // เปลี่ยนสถานะนักเรียนแล้วระบบเตือนว่ายังมีเงินในกระเป๋า
        $this->actingAs($admin)->put("/students/{$leaver->id}", ['student_code' => $leaver->student_code, 'first_name' => $leaver->first_name, 'last_name' => $leaver->last_name,
            'classroom_id' => $leaver->classroom_id, 'status' => 'moved'])->assertSessionHasNoErrors()->assertSessionHas('warning');
        $this->assertStringContainsString('120.00', session('warning'));
        $staff->update(['is_active' => false]);

        $this->actingAs($admin)->get('/wallets')->assertOk()->assertSee('ผู้พ้นสภาพ 2 คน ยังมีเงินในกระเป๋ารวม 200.00 บาท');
        $this->actingAs($admin)->get('/wallets/leavers')->assertOk()->assertSee($leaver->fullName())->assertSee($staff->name)->assertSee('ย้ายโรงเรียน')->assertDontSee($active->fullName());

        $ids = Wallet::whereIn('student_id', [$leaver->id, $active->id])->orWhere('user_id', $staff->id)->pluck('id')->all();
        $this->actingAs($admin)->post('/wallets/leavers', ['wallets' => $ids, 'method' => 'cash'])->assertSessionHasErrors('note');
        $this->actingAs($admin)->post('/wallets/leavers', ['wallets' => $ids, 'method' => 'cash', 'note' => 'คืนเงินคงเหลือเมื่อพ้นสภาพ'])->assertSessionHas('success');
        $this->assertStringContainsString('2 คน รวม 200.00 บาท', session('success'));

        // คนที่ยังเรียนอยู่ไม่ถูกถอน แม้ส่งรหัสกระเป๋ามาด้วย
        $this->assertSame('0.00', Wallet::where('student_id', $leaver->id)->value('balance'));
        $this->assertSame('0.00', Wallet::where('user_id', $staff->id)->value('balance'));
        $this->assertSame('70.00', Wallet::where('student_id', $active->id)->value('balance'));
        $last = Wallet::where('student_id', $leaver->id)->first()->transactions()->latest('id')->first();
        $this->assertSame(['withdraw', '-120.00', 'cash'], [$last->type, $last->amount, $last->method]);
        $this->assertSame(2, AuditLog::where('action', 'finance.wallet')->where('description', 'like', 'ถอนเงินคืน%')->count());

        $this->actingAs($admin)->get('/wallets/leavers')->assertOk()->assertSee('ไม่มีเงินค้างคืน');
        $this->actingAs(User::where('username', 'teacher')->first())->get('/wallets/leavers')->assertForbidden();
    }

    public function test_reconcile_passes_on_normal_use_and_reports_tampering(): void
    {
        [$shop, $rice, , $cashier] = $this->shop();
        $s = $this->child();
        $admin = $this->admin();
        WalletService::topupCash($s, 200, $admin);
        $sale = $this->charge($cashier, $shop, $s, [['product_id' => $rice->id, 'qty' => 2]], 'key-rec-01');
        $voided = $this->charge($cashier, $shop, $s, [['product_id' => $rice->id, 'qty' => 1]], 'key-rec-02');
        WalletService::void(WalletSale::find($voided), $admin, 'กดผิด');
        WalletService::adjust($s, 'withdraw', -30, $admin, 'ขอคืน', 'cash');
        WalletService::for($s)->topups()->create(['amount' => 500, 'method' => 'transfer', 'status' => 'pending']);
        WalletService::settle($shop, now(), 'cash', $admin);

        $this->actingAs($admin)->get('/wallets')->assertOk()->assertSee('ยังไม่เคยกระทบยอด');
        $this->artisan('wallet:reconcile')->assertExitCode(0);
        $this->assertSame(0, WalletReconciler::lastResult()['count']);
        $this->actingAs($admin)->get('/wallets')->assertOk()->assertSee('กระทบยอดแล้ว ตรงกันทั้งหมด');

        // แก้ยอดคงเหลือในฐานข้อมูลตรง ๆ: ยอดไม่ตรงกับสมุดรายการ
        $wallet = Wallet::where('student_id', $s->id)->first();
        Wallet::whereKey($wallet->id)->update(['balance' => 999]);
        $this->artisan('wallet:reconcile')->assertExitCode(1);
        $result = WalletReconciler::lastResult();
        $this->assertSame(1, $result['count']);
        $this->assertStringContainsString($s->fullName(), $result['issues'][0]);
        $this->assertStringContainsString('ไม่ตรงกับผลรวมสมุดรายการ', $result['issues'][0]);
        $this->actingAs($admin)->get('/wallets')->assertOk()->assertSee('กระทบยอดพบ 1 จุดที่ไม่ตรง');
        Wallet::whereKey($wallet->id)->update(['balance' => $wallet->balance]);

        // แก้ยอดของรายการขายที่จ่ายเงินให้ร้านแล้ว: ไม่ตรงทั้งกับบรรทัดตัดเงินและใบจ่ายเงิน
        WalletSale::whereKey($sale)->update(['total' => 10]);
        $issues = WalletReconciler::check();
        $this->assertCount(2, $issues);
        $this->assertStringContainsString("รายการขาย #{$sale}", $issues[0]);
        $this->assertStringContainsString('ใบจ่ายเงินร้านค้า', $issues[1]);
        WalletSale::whereKey($sale)->update(['total' => 50]);

        // รายการเติมเงินที่ยังไม่อนุมัติแต่มีเงินเข้า
        $pending = WalletService::for($s)->topups()->where('status', 'pending')->first();
        WalletTransaction::where('type', 'topup')->limit(1)->update(['topup_id' => $pending->id]);
        $this->assertNotEmpty(array_filter(WalletReconciler::check(), fn ($i) => str_contains($i, 'ยังไม่อนุมัติแต่มีเงินเข้ากระเป๋าแล้ว')));

        // ปุ่มตรวจตอนนี้บนหน้ากระเป๋าเงิน
        $this->actingAs($admin)->post('/wallets/reconcile')->assertSessionHas('warning');
        $this->actingAs($cashier)->post('/wallets/reconcile')->assertForbidden();
    }
}
