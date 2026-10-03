<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\Payment;
use App\Models\PaymentSlip;
use App\Models\Student;
use App\Models\User;
use App\Support\Sequence;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityAndFinanceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    private function parent(): User
    {
        return User::where('phone', '0812345678')->first();
    }

    /* ---------------- บัญชี ---------------- */

    public function test_account_is_locked_after_repeated_wrong_passwords(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'admin', 'password' => 'wrong-'.$i])->assertSessionHasErrors('username');
        }
        // รหัสถูกก็ยังเข้าไม่ได้จนกว่าจะพ้นเวลาล็อก
        $this->post('/login', ['username' => 'admin', 'password' => 'admin1234'])->assertSessionHasErrors('username');
        $this->assertGuest();

        $this->travel(16)->minutes();
        $this->post('/login', ['username' => 'admin', 'password' => 'admin1234'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    /* ---------------- LINE ---------------- */

    private function lineMessage(string $lineId, string $text)
    {
        $body = json_encode(['events' => [['type' => 'message', 'source' => ['userId' => $lineId], 'message' => ['type' => 'text', 'text' => $text]]]]);

        return $this->call('POST', '/line/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_LINE_SIGNATURE' => base64_encode(hash_hmac('sha256', $body, 'test-secret', true)),
        ], $body);
    }

    public function test_line_link_code_expires_and_guessing_is_throttled(): void
    {
        Settings::set(['line_channel_secret' => 'test-secret']);
        $parent = $this->parent();

        $this->actingAs($parent)->post('/profile/line')->assertRedirect();
        $code = $parent->fresh()->line_link_code;
        $this->assertNotNull($parent->fresh()->activeLineCode());

        // หมดอายุแล้วใช้ไม่ได้
        $this->travel(11)->minutes();
        $this->lineMessage('Uexpired', $code)->assertOk();
        $this->assertNull($parent->fresh()->line_user_id);

        // เดาผิดเกิน 5 ครั้ง แม้ได้รหัสที่ถูกก็ไม่ผูกให้
        $this->actingAs($parent)->post('/profile/line');
        $code = $parent->fresh()->line_link_code;
        foreach (range(1, 5) as $i) {
            $this->lineMessage('Uattacker', '00000'.$i)->assertOk();
        }
        $this->lineMessage('Uattacker', $code)->assertOk();
        $this->assertNull($parent->fresh()->line_user_id);

        // เจ้าของบัญชีตัวจริงยังเชื่อมได้
        $this->lineMessage('Uowner', $code)->assertOk();
        $this->assertSame('Uowner', $parent->fresh()->line_user_id);
    }

    public function test_line_secrets_are_encrypted_at_rest(): void
    {
        Settings::set(['line_channel_token' => 'tok-123', 'line_channel_secret' => 'sec-456']);

        $this->assertSame('tok-123', Settings::get('line_channel_token'));
        $this->assertSame('sec-456', Settings::get('line_channel_secret'));
        $this->assertNotSame('tok-123', DB::table('settings')->where('key', 'line_channel_token')->value('value'));

        // ค่าที่บันทึกไว้ก่อนเริ่มเข้ารหัสยังอ่านได้
        DB::table('settings')->where('key', 'line_channel_secret')->update(['value' => 'legacy-plain']);
        Settings::set([]);
        $this->assertSame('legacy-plain', Settings::get('line_channel_secret'));
    }

    /* ---------------- ไฟล์ส่วนตัว ---------------- */

    public function test_slip_and_leave_files_are_private_and_authorized(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $inv = Invoice::where('status', 'unpaid')->first();
        $parent = $inv->student->guardians()->first();
        $stranger = User::where('role', 'parent')->whereDoesntHave('children', fn ($q) => $q->whereKey($inv->student_id))->first();

        $this->actingAs($parent)->post("/invoices/{$inv->id}/slips", ['amount' => 100, 'slip' => UploadedFile::fake()->image('slip.jpg')])->assertSessionHasNoErrors();
        $slip = PaymentSlip::latest('id')->first();
        Storage::disk('local')->assertExists($slip->image);
        Storage::disk('public')->assertMissing($slip->image);
        $this->assertStringNotContainsString('/storage/', $slip->imageUrl());

        $this->get($slip->imageUrl())->assertOk();
        $this->actingAs($this->admin())->get($slip->imageUrl())->assertOk();
        $this->actingAs($stranger)->get($slip->imageUrl())->assertForbidden();
        auth()->logout();
        $this->get($slip->imageUrl())->assertRedirect(route('login'));

        // ใบลา: ผู้ปกครองคนอื่นเปิดใบรับรองแพทย์ไม่ได้
        $child = $parent->children()->first();
        $this->actingAs($parent)->post(route('parent.leave.store'), [
            'student_id' => $child->id, 'type' => 'sick', 'start_date' => today()->toDateString(), 'end_date' => today()->toDateString(),
            'reason' => 'ไข้', 'attachment' => UploadedFile::fake()->image('cert.jpg'),
        ])->assertSessionHasNoErrors();
        $leave = LeaveRequest::whereNotNull('attachment')->latest('id')->first();
        Storage::disk('local')->assertExists($leave->attachment);
        $this->get(route('files.show', ['leave', $leave->id]))->assertOk();
        $this->actingAs($stranger)->get(route('files.show', ['leave', $leave->id]))->assertForbidden();
    }

    public function test_privatize_command_moves_old_public_files(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $inv = Invoice::where('status', 'unpaid')->first();
        Storage::disk('public')->put('slips/old.jpg', 'x');
        $slip = PaymentSlip::create(['invoice_id' => $inv->id, 'amount' => 50, 'image' => 'slips/old.jpg', 'uploaded_by' => $this->admin()->id]);

        // ก่อนย้าย ยังเปิดผ่านหน้าตรวจสิทธิ์ได้
        $this->actingAs($this->admin())->get($slip->imageUrl())->assertOk();

        $this->artisan('files:privatize')->assertSuccessful();
        Storage::disk('public')->assertMissing('slips/old.jpg');
        Storage::disk('local')->assertExists('slips/old.jpg');
        $this->get($slip->imageUrl())->assertOk();
    }

    /* ---------------- การเงิน ---------------- */

    public function test_sequence_continues_from_existing_numbers_and_never_repeats(): void
    {
        $ym = now()->format('Ym');
        $existing = (int) substr((string) Payment::where('receipt_no', 'like', "RC{$ym}%")->max('receipt_no'), -5);

        $first = Payment::nextNumber();
        $second = Payment::nextNumber();

        $this->assertSame('RC'.$ym.str_pad((string) ($existing + 1), 5, '0', STR_PAD_LEFT), $first);
        $this->assertSame('RC'.$ym.str_pad((string) ($existing + 2), 5, '0', STR_PAD_LEFT), $second);
        $this->assertSame(1, Sequence::next('TEST', '2569', fn () => 0));
        $this->assertSame(8, Sequence::next('TEST2', '2569', fn () => 7));
    }

    public function test_voiding_a_receipt_keeps_the_record_and_reopens_the_invoice(): void
    {
        $inv = Invoice::where('status', 'unpaid')->first();
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('invoices.pay', $inv), ['amount' => $inv->balance(), 'method' => 'cash'])->assertRedirect();
        $payment = $inv->payments()->first();
        $this->assertSame('paid', $inv->fresh()->status);

        $this->post(route('payments.void', $payment), [])->assertSessionHasErrors('void_reason');
        $this->post(route('payments.void', $payment), ['void_reason' => 'รับเงินผิดคน'])->assertRedirect();

        $payment->refresh();
        $this->assertTrue($payment->isVoided());
        $this->assertSame($admin->id, $payment->voided_by);
        $this->assertSame('unpaid', $inv->fresh()->status);
        $this->assertEquals(0, $inv->fresh()->paid);
        $this->get(route('payments.receipt', $payment))->assertOk()->assertSee('ยกเลิก');
        $this->get(route('invoices.show', $inv))->assertOk()->assertSee('รับเงินผิดคน');

        // ยกเลิกซ้ำไม่ได้ และใบเสร็จใบใหม่ไม่ใช้เลขเดิม
        $this->post(route('payments.void', $payment), ['void_reason' => 'x'])->assertStatus(422);
        $this->post(route('invoices.pay', $inv), ['amount' => 10, 'method' => 'cash'])->assertRedirect();
        $this->assertNotSame($payment->receipt_no, $inv->payments()->valid()->first()->receipt_no);

        // ครูยกเลิกใบเสร็จไม่ได้
        $this->actingAs(User::where('username', 'teacher')->first())->post(route('payments.void', $inv->payments()->valid()->first()), ['void_reason' => 'x'])->assertForbidden();
    }

    public function test_discount_requires_a_reason_and_reduces_the_balance(): void
    {
        $inv = Invoice::where('status', 'unpaid')->first();
        $total = $inv->total;

        $this->actingAs($this->admin())->post(route('invoices.discount', $inv), ['discount' => 100])->assertSessionHasErrors('discount_note');
        $this->post(route('invoices.discount', $inv), ['discount' => $total + 1, 'discount_note' => 'x'])->assertSessionHasErrors('discount');
        $this->post(route('invoices.discount', $inv), ['discount' => 100, 'discount_note' => 'ทุนเรียนดี'])->assertSessionHasNoErrors();

        $inv->refresh();
        $this->assertEquals($total - 100, $inv->balance());
        $this->get(route('invoices.show', $inv))->assertOk()->assertSee('ทุนเรียนดี');

        // ลดเต็มจำนวน = ชำระครบ
        $this->post(route('invoices.discount', $inv), ['discount' => $total, 'discount_note' => 'ทุนเต็มจำนวน']);
        $this->assertEquals(0, $inv->fresh()->balance());
    }

    public function test_same_slip_cannot_be_submitted_twice_or_approved_twice(): void
    {
        Storage::fake('local');
        $inv = Invoice::where('status', 'unpaid')->first();
        $parent = $inv->student->guardians()->first();
        $file = UploadedFile::fake()->image('slip.jpg');

        $this->actingAs($parent)->post("/invoices/{$inv->id}/slips", ['amount' => 100, 'slip' => $file])->assertSessionHasNoErrors();
        $this->post("/invoices/{$inv->id}/slips", ['amount' => 100, 'slip' => $file])->assertSessionHasErrors('slip');
        $this->assertSame(1, PaymentSlip::where('invoice_id', $inv->id)->count());

        $slip = PaymentSlip::where('invoice_id', $inv->id)->first();
        $this->actingAs($this->admin())->post("/slips/{$slip->id}/approve")->assertRedirect();
        $this->post("/slips/{$slip->id}/approve")->assertStatus(422);
        $this->assertSame(1, $inv->payments()->count());
    }
}
