<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Attendance;
use App\Models\Book;
use App\Models\BookLoan;
use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\PaymentSlip;
use App\Models\StaffAttendance;
use App\Models\StaffLeave;
use App\Models\Student;
use App\Models\User;
use App\Support\PromptPay;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SchoolBrightParityTest extends TestCase
{
    use \Tests\Concerns\AppliesOnline;
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

    public function test_all_new_pages_render(): void
    {
        $student = Student::first();
        $admission = Admission::first();
        foreach (['/gate', '/student-cards', '/health', '/health/measure', '/library', '/library-loans', '/staff-leaves', '/reports',
            '/reports/dmc', '/calendar', '/slips', '/admissions', "/admissions/{$admission->id}", '/settings/messages', "/transcript/{$student->id}"] as $url) {
            $code = $this->actingAs($this->admin())->get($url)->baseResponse->getStatusCode();
            $this->assertSame(200, $code, "GET {$url}");
        }
        $this->get('/apply')->assertOk()->assertSee('สมัครเรียนออนไลน์');
        $this->get('/apply/status')->assertOk();
    }

    public function test_gate_scan_in_late_repeat_and_out(): void
    {
        $this->travelTo(today()->setTime(7, 40));
        $s = Student::active()->first();
        Attendance::where('student_id', $s->id)->where('date', today()->toDateString())->delete();

        $this->actingAs($this->teacher())->postJson('/gate/scan', ['code' => $s->qr_token])
            ->assertOk()->assertJsonPath('kind', 'present');
        $this->actingAs($this->teacher())->postJson('/gate/scan', ['code' => $s->qr_token])
            ->assertJsonPath('kind', 'repeat');
        $this->actingAs($this->teacher())->postJson('/gate/scan', ['code' => 'nope'])->assertNotFound();

        $this->travelTo(today()->setTime(15, 30));
        $this->actingAs($this->teacher())->postJson('/gate/scan', ['code' => $s->qr_token])->assertJsonPath('kind', 'out');
        $att = Attendance::where('student_id', $s->id)->where('date', today()->toDateString())->first();
        $this->assertSame('07:40:00', $att->checked_at);
        $this->assertSame('15:30:00', $att->checkout_at);

        // มาหลังเวลาสาย
        $late = Student::active()->skip(1)->first();
        Attendance::where('student_id', $late->id)->where('date', today()->toDateString())->delete();
        $this->travelTo(today()->setTime(8, 20));
        $this->actingAs($this->teacher())->postJson('/gate/scan', ['code' => $late->student_code])->assertJsonPath('kind', 'late');
    }

    public function test_line_link_via_webhook_and_push_is_sent(): void
    {
        Settings::set(['line_channel_secret' => 'sekret', 'line_channel_token' => 'tok']);
        $parent = User::where('phone', '0812345678')->first();
        $this->actingAs($parent)->post('/profile/line')->assertRedirect();
        $code = $parent->fresh()->line_link_code;
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        Http::fake(['api.line.me/*' => Http::response(['ok' => true])]);
        $body = json_encode(['events' => [['type' => 'message', 'replyToken' => 'r1', 'source' => ['userId' => 'U123'], 'message' => ['type' => 'text', 'text' => $code]]]]);
        $sig = base64_encode(hash_hmac('sha256', $body, 'sekret', true));

        // ลายเซ็นผิดต้องถูกปฏิเสธ
        $this->call('POST', '/line/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_LINE_SIGNATURE' => 'bad'], $body)->assertForbidden();
        $this->call('POST', '/line/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_LINE_SIGNATURE' => $sig], $body)->assertOk();
        $this->assertSame('U123', $parent->fresh()->line_user_id);

        // เช็คชื่อลูกว่าขาดวันนี้ → ส่ง LINE ถึงผู้ปกครอง
        $child = $parent->children()->first();
        $this->actingAs($this->teacher())->post('/attendance', [
            'classroom_id' => $child->classroom_id, 'date' => today()->toDateString(), 'status' => [$child->id => 'absent'],
        ])->assertRedirect();
        Http::assertSent(fn ($req) => str_contains($req->url(), '/message/multicast') && in_array('U123', $req['to']) && str_contains($req['messages'][0]['text'], 'ไม่ได้มาเรียน'));
        $this->assertTrue(MessageLog::where('user_id', $parent->id)->where('status', 'sent')->exists());
    }

    public function test_promptpay_payload_and_slip_approval_creates_payment(): void
    {
        $this->assertSame('00020101021129370016A0000006770101110113006681234567853037645802TH6304823E', PromptPay::payload('0812345678'));
        $this->assertNull(PromptPay::payload('123'));

        Storage::fake('public');
        $inv = Invoice::where('status', 'unpaid')->first();
        $parent = $inv->student->guardians()->first();
        $this->actingAs($parent)->get("/invoices/{$inv->id}")->assertOk()->assertSee('data-qr="000201', false);

        $this->actingAs($parent)->post("/invoices/{$inv->id}/slips", ['amount' => $inv->balance(), 'slip' => UploadedFile::fake()->image('slip.jpg')])->assertRedirect();
        $slip = PaymentSlip::where('invoice_id', $inv->id)->latest('id')->first();
        $this->assertSame('pending', $slip->status);

        $this->actingAs($this->admin())->post("/slips/{$slip->id}/approve")->assertRedirect();
        $this->assertSame('approved', $slip->fresh()->status);
        $this->assertSame('paid', $inv->fresh()->status);

        // ผู้ปกครองคนอื่นส่งสลิปให้ใบแจ้งหนี้ที่ไม่ใช่ของลูกไม่ได้
        $other = Invoice::where('status', 'unpaid')->whereDoesntHave('student.guardians', fn ($q) => $q->whereKey($parent->id))->first();
        $this->actingAs($parent)->post("/invoices/{$other->id}/slips", ['amount' => 10, 'slip' => UploadedFile::fake()->image('s.jpg')])->assertForbidden();
    }

    public function test_gps_checkin_enforced_when_required(): void
    {
        Settings::set(['gps_required' => '1', 'school_lat' => '16.4419', 'school_lng' => '102.8360', 'gps_radius' => '300']);
        $t = $this->teacher();
        StaffAttendance::where('user_id', $t->id)->where('date', today()->toDateString())->delete();

        $this->actingAs($t)->post('/checkin', ['action' => 'in'])->assertSessionHasErrors('gps');
        $this->actingAs($t)->post('/checkin', ['action' => 'in', 'lat' => '16.5000', 'lng' => '102.8360'])->assertSessionHasErrors('gps');
        $this->actingAs($t)->post('/checkin', ['action' => 'in', 'lat' => '16.4420', 'lng' => '102.8361'])->assertSessionHasNoErrors();
        $rec = StaffAttendance::where('user_id', $t->id)->where('date', today()->toDateString())->first();
        $this->assertNotNull($rec->check_in);
        $this->assertLessThan(50, $rec->distance_m);
    }

    public function test_library_borrow_limit_and_return(): void
    {
        $book = Book::create(['code' => 'T001', 'title' => 'หนังสือทดสอบ', 'copies' => 1]);
        [$a, $b] = Student::active()->whereDoesntHave('bookLoans')->take(2)->get();

        $this->actingAs($this->teacher())->post('/library-loans/borrow', ['student' => $a->student_code, 'book' => 'T001'])->assertSessionHasNoErrors();
        $this->actingAs($this->teacher())->post('/library-loans/borrow', ['student' => $b->student_code, 'book' => 'T001'])->assertSessionHasErrors('book');
        $this->actingAs($this->teacher())->post('/library-loans/return', ['book' => 'T001'])->assertSessionHasNoErrors();
        $this->assertNotNull(BookLoan::where('book_id', $book->id)->first()->returned_on);
    }

    public function test_health_visit_and_measurements(): void
    {
        $s = Student::active()->first();
        $this->actingAs($this->teacher())->post('/health', ['student_code' => $s->student_code.' '.$s->fullName(), 'symptom' => 'ปวดหัว', 'action' => 'rest'])->assertSessionHasNoErrors();
        $this->assertTrue($s->healthVisits()->where('symptom', 'ปวดหัว')->exists());

        $this->actingAs($this->teacher())->post('/health/measure', ['measured_on' => today()->toDateString(), 'rows' => [$s->id => ['weight' => 45, 'height' => 155]]])->assertSessionHasNoErrors();
        $this->assertEquals(18.7, $s->measurements()->first()->bmi());
    }

    public function test_admission_public_submit_then_enroll(): void
    {
        $a = $this->applyOnline($this->applicantData([
            'level' => 'ม.1', 'prefix' => 'เด็กหญิง', 'first_name' => 'ทดสอบ', 'last_name' => 'สมัครเรียน', 'gender' => 'F',
            'birthdate' => '2014-01-01', 'citizen_id' => '1234567890123', 'parent_name' => 'แม่ ทดสอบ', 'parent_phone' => '0877777777',
        ]));
        $this->assertSame('submitted', $a->status);
        $this->get('/apply/status?app_no='.$a->app_no.'&phone=0877777777')->assertSee('ทดสอบ');

        // บอทกรอก honeypot
        $this->post('/apply/start', ['website' => 'spam', 'level' => 'ม.1'])->assertSessionHasErrors('website');

        $this->actingAs($this->admin())->put("/admissions/{$a->id}", ['status' => 'accepted'])->assertRedirect();
        $this->actingAs($this->admin())->post("/admissions/{$a->id}/enroll", ['student_code' => '99999'])->assertRedirect();
        $student = Student::where('student_code', '99999')->first();
        $this->assertSame('ทดสอบ', $student->first_name);
        $this->assertTrue($student->guardians()->where('phone', '0877777777')->exists());
        $this->assertSame('enrolled', $a->fresh()->status);
    }

    public function test_staff_leave_approval_fills_staff_attendance(): void
    {
        $t = $this->teacher();
        $day = today()->addWeekdays(3)->toDateString();
        $this->actingAs($t)->post('/staff-leaves', ['type' => 'personal', 'start_date' => $day, 'end_date' => $day, 'reason' => 'ธุระ'])->assertRedirect();
        $leave = StaffLeave::where('user_id', $t->id)->where('status', 'pending')->latest('id')->first();

        $this->actingAs($t)->post("/staff-leaves/{$leave->id}/approve")->assertForbidden(); // ครูอนุมัติเองไม่ได้
        $this->actingAs($this->admin())->post("/staff-leaves/{$leave->id}/approve")->assertRedirect();
        $this->assertSame('leave', StaffAttendance::where('user_id', $t->id)->where('date', $day)->value('status'));
    }

    public function test_calendar_visibility_and_transcript_scope(): void
    {
        $parent = User::where('phone', '0812345678')->first();
        $this->actingAs($parent)->get('/calendar')->assertOk()->assertSee('สอบปลายภาค')->assertDontSee('ส่งผลการเรียนภาคเรียนที่ 1');
        $this->actingAs($this->teacher())->get('/calendar')->assertSee('ส่งผลการเรียนภาคเรียนที่ 1');

        $child = $parent->children()->first();
        $this->actingAs($parent)->get("/transcript/{$child->id}")->assertOk()->assertSee('GPAX');
        $stranger = Student::whereDoesntHave('guardians', fn ($q) => $q->whereKey($parent->id))->first();
        $this->actingAs($parent)->get("/transcript/{$stranger->id}")->assertForbidden();
    }
}
