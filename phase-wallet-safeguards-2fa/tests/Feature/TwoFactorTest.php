<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\Settings;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** ยืนยันตัวตน 2 ขั้น: รหัสตามเวลา เปิดใช้ ด่านกรอกรหัสตอนเข้าสู่ระบบ รหัสสำรอง การบังคับใช้ และการล้างโดยผู้ดูแล */
class TwoFactorTest extends TestCase
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

    /** รหัส 6 หลักที่ไม่ตรงกับช่วงเวลาปัจจุบันและช่วงข้างเคียง */
    private function wrongCode(string $secret): string
    {
        $valid = array_map(fn ($d) => Totp::codeAt($secret, Totp::step() + $d), [-1, 0, 1]);
        for ($n = 0; ; $n++) {
            if (! in_array($code = str_pad((string) $n, 6, '0', STR_PAD_LEFT), $valid, true)) {
                return $code;
            }
        }
    }

    /**
     * เปิดใช้ผ่านหน้าเว็บจริง คืนกุญแจลับและรหัสสำรองที่ระบบแสดง
     *
     * @return array{0: string, 1: list<string>}
     */
    private function enableFor(User $user): array
    {
        $this->actingAs($user)->get('/two-factor')->assertOk();
        $secret = session('two_factor.setup_secret');
        $this->actingAs($user)->post('/two-factor', ['code' => Totp::codeAt($secret, Totp::step())])->assertRedirect(route('two-factor.setup'));

        return [$secret, session('recovery_codes')];
    }

    /** รหัสของช่วงเวลาถัดไป (รหัสของช่วงที่ใช้ไปแล้วใช้ซ้ำไม่ได้) */
    private function nextCode(string $secret): string
    {
        $this->travel(Totp::PERIOD + 1)->seconds();

        return Totp::codeAt($secret, Totp::step());
    }

    public function test_totp_matches_rfc_6238_and_refuses_reuse(): void
    {
        // ค่าทดสอบจาก RFC 6238 (SHA1, กุญแจ "12345678901234567890") ตัดเหลือ 6 หลัก
        $secret = Totp::base32Encode('12345678901234567890');
        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $secret);
        $this->assertSame('12345678901234567890', Totp::base32Decode($secret));
        foreach ([59 => '287082', 1111111109 => '081804', 1234567890 => '005924', 2000000000 => '279037'] as $time => $code) {
            $this->assertSame($code, Totp::codeAt($secret, Totp::step($time)));
        }

        $this->travelTo(Carbon::createFromTimestamp(1111111109));
        $step = Totp::step();
        $this->assertSame($step, Totp::verify($secret, '081804'));
        $this->assertSame($step, Totp::verify($secret, ' 081 804 '));
        // รหัสเดิมใช้ซ้ำไม่ได้ · รหัสผิดหรือไม่ครบ 6 หลักไม่ผ่าน
        $this->assertNull(Totp::verify($secret, '081804', $step));
        $this->assertNull(Totp::verify($secret, $this->wrongCode($secret)));
        $this->assertNull(Totp::verify($secret, '81804'));
        // นาฬิกามือถือคลาดได้หนึ่งช่วง แต่เกินกว่านั้นไม่ได้
        $this->assertSame($step - 1, Totp::verify($secret, Totp::codeAt($secret, $step - 1)));
        $this->assertSame($step + 1, Totp::verify($secret, Totp::codeAt($secret, $step + 1)));
        $this->assertNull(Totp::verify($secret, Totp::codeAt($secret, $step - 2)));

        $this->assertNotSame(Totp::generateSecret(), Totp::generateSecret());
        $this->assertStringStartsWith('otpauth://totp/', Totp::uri($secret, 'admin', 'โรงเรียน ตัวอย่าง'));
        $this->assertStringContainsString('secret='.$secret, Totp::uri($secret, 'admin', 'โรงเรียน ตัวอย่าง'));
    }

    public function test_enabling_needs_a_valid_code_and_login_then_needs_the_second_step(): void
    {
        $teacher = $this->teacher();
        $this->actingAs($teacher)->get('/profile')->assertOk()->assertSee('ยืนยันตัวตน 2 ขั้น')->assertSee('ยังไม่ได้เปิด');
        $this->actingAs($teacher)->get('/two-factor')->assertOk()->assertSee('สแกน QR ด้วยแอป');
        $secret = session('two_factor.setup_secret');
        // เปิดหน้าซ้ำได้ QR อันเดิม
        $this->actingAs($teacher)->get('/two-factor');
        $this->assertSame($secret, session('two_factor.setup_secret'));

        $this->actingAs($teacher)->post('/two-factor', ['code' => $this->wrongCode($secret)])->assertSessionHasErrors('code');
        $this->assertFalse($teacher->fresh()->hasTwoFactor());

        $this->actingAs($teacher)->post('/two-factor', ['code' => Totp::codeAt($secret, Totp::step())])->assertRedirect(route('two-factor.setup'))->assertSessionHas('recovery_codes');
        $this->assertCount(8, session('recovery_codes'));
        $teacher = $teacher->fresh();
        $this->assertTrue($teacher->hasTwoFactor());
        // กุญแจลับและรหัสสำรองไม่ถูกเก็บเป็นข้อความที่อ่านได้
        $row = DB::table('users')->where('id', $teacher->id)->first();
        $this->assertStringNotContainsString($secret, $row->two_factor_secret);
        $this->assertStringNotContainsString(session('recovery_codes')[0], $row->two_factor_recovery_codes);
        $this->assertArrayNotHasKey('two_factor_secret', $teacher->toArray());
        $this->assertTrue(AuditLog::where('action', 'user.two_factor')->where('subject_id', $teacher->id)->exists());
        $this->actingAs($teacher)->get('/two-factor')->assertOk()->assertSee('เปิดใช้อยู่')->assertSee('รหัสสำรองที่ยังใช้ได้ 8 รหัส');

        // เข้าสู่ระบบ: รหัสผ่านถูกยังไม่พอ
        $this->post('/logout');
        $this->post('/login', ['username' => 'teacher', 'password' => 'wrong-password'])->assertSessionHasErrors('username');
        $this->post('/login', ['username' => 'teacher', 'password' => 'teacher1234'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/two-factor/challenge')->assertOk()->assertSee('ยืนยันตัวตนขั้นที่ 2');

        $this->post('/two-factor/challenge', ['code' => $this->wrongCode($secret)])->assertSessionHasErrors('code');
        // รหัสที่ใช้ตอนเปิดใช้ไปแล้วใช้ซ้ำไม่ได้
        $this->post('/two-factor/challenge', ['code' => Totp::codeAt($secret, Totp::step())])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/two-factor/challenge', ['code' => $this->nextCode($secret)])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($teacher);
        $this->assertNotNull($teacher->fresh()->last_login_at);
    }

    public function test_challenge_expires_and_locks_after_repeated_wrong_codes(): void
    {
        [$secret] = $this->enableFor($this->teacher());
        $this->post('/logout');

        // ไม่ได้ผ่านรหัสผ่านมาก่อน: ไม่มีด่านให้กรอก
        $this->get('/two-factor/challenge')->assertRedirect(route('login'));
        $this->post('/two-factor/challenge', ['code' => '123456'])->assertRedirect(route('login'));

        $this->post('/login', ['username' => 'teacher', 'password' => 'teacher1234'])->assertRedirect(route('two-factor.challenge'));
        foreach (range(1, 5) as $i) {
            $this->post('/two-factor/challenge', ['code' => $this->wrongCode($secret)])->assertSessionHasErrors('code');
        }
        // ผิดครบ 5 ครั้ง: แม้รหัสถูกก็ยังเข้าไม่ได้จนกว่าจะพ้นเวลาล็อก
        $this->post('/two-factor/challenge', ['code' => $this->nextCode($secret)])->assertSessionHasErrors('code');
        $this->assertStringContainsString('ผิดหลายครั้ง', session('errors')->first('code'));
        $this->assertGuest();

        // ทิ้งไว้เกิน 10 นาที ต้องเริ่มจากรหัสผ่านใหม่
        $this->travel(16)->minutes();
        $this->post('/two-factor/challenge', ['code' => Totp::codeAt($secret, Totp::step())])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_recovery_code_signs_in_once(): void
    {
        $teacher = $this->teacher();
        [, $codes] = $this->enableFor($teacher);
        $this->post('/logout');

        // พิมพ์ตัวเล็กหรือไม่มีขีดก็ได้
        $this->post('/login', ['username' => 'teacher', 'password' => 'teacher1234']);
        $this->post('/two-factor/challenge', ['code' => strtolower(str_replace('-', '', $codes[0]))])->assertRedirect(route('home'))->assertSessionHas('warning');
        $this->assertAuthenticatedAs($teacher);
        $this->assertCount(7, $teacher->fresh()->two_factor_recovery_codes);
        $this->assertTrue(AuditLog::where('action', 'user.two_factor')->where('description', 'like', '%รหัสสำรอง%')->exists());

        $this->post('/logout');
        $this->post('/login', ['username' => 'teacher', 'password' => 'teacher1234']);
        $this->post('/two-factor/challenge', ['code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->post('/two-factor/challenge', ['code' => $codes[1]])->assertRedirect(route('home'));

        // ออกรหัสสำรองชุดใหม่ต้องยืนยันรหัสผ่าน ชุดเดิมใช้ไม่ได้
        $this->post('/two-factor/recovery-codes', ['current_password' => 'nope'])->assertSessionHasErrors('current_password');
        $this->post('/two-factor/recovery-codes', ['current_password' => 'teacher1234'])->assertSessionHas('recovery_codes');
        $fresh = session('recovery_codes');
        $this->assertCount(8, $teacher->fresh()->two_factor_recovery_codes);
        $this->post('/logout');
        $this->post('/login', ['username' => 'teacher', 'password' => 'teacher1234']);
        $this->post('/two-factor/challenge', ['code' => $codes[2]])->assertSessionHasErrors('code');
        $this->post('/two-factor/challenge', ['code' => $fresh[0]])->assertRedirect(route('home'));
    }

    public function test_owner_disables_with_password_and_admin_or_server_can_reset(): void
    {
        $teacher = $this->teacher();
        $this->enableFor($teacher);

        $this->actingAs($teacher)->delete('/two-factor', ['current_password' => 'nope'])->assertSessionHasErrors('current_password');
        $this->assertTrue($teacher->fresh()->hasTwoFactor());
        $this->actingAs($teacher)->delete('/two-factor', ['current_password' => 'teacher1234'])->assertRedirect(route('two-factor.setup'));
        $this->assertFalse($teacher->fresh()->hasTwoFactor());
        $this->assertNull(DB::table('users')->where('id', $teacher->id)->value('two_factor_secret'));

        // เข้าสู่ระบบได้ด้วยรหัสผ่านอย่างเดียวตามเดิม
        $this->post('/logout');
        $this->post('/login', ['username' => 'teacher', 'password' => 'teacher1234'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($teacher);

        // มือถือหาย: ผู้ดูแลล้างให้จากหน้าผู้ใช้งาน
        $this->enableFor($teacher->fresh());
        $this->actingAs($this->admin())->get("/users/{$teacher->id}/edit")->assertOk()->assertSee('ล้างการยืนยันตัวตน 2 ขั้น');
        $this->actingAs($teacher->fresh())->post("/users/{$teacher->id}/two-factor/reset")->assertForbidden();
        $this->actingAs($this->admin())->post("/users/{$teacher->id}/two-factor/reset")->assertSessionHas('success');
        $this->assertFalse($teacher->fresh()->hasTwoFactor());
        $this->actingAs($this->admin())->post("/users/{$teacher->id}/two-factor/reset")->assertNotFound();

        // ผู้ดูแลระบบเองทำมือถือหาย: ล้างจากเครื่องเซิร์ฟเวอร์
        $this->enableFor($this->admin());
        $this->artisan('users:disable-2fa nobody')->assertExitCode(1);
        $this->artisan('users:disable-2fa admin')->assertExitCode(0);
        $this->assertFalse($this->admin()->hasTwoFactor());
    }

    public function test_school_can_require_it_for_accounts_that_handle_money_users_or_settings(): void
    {
        $admin = $this->admin();
        $teacher = $this->teacher();
        $finance = User::where('username', 't3')->first();
        $finance->roles()->sync([Role::where('key', 'finance')->first()->id]);
        $parent = User::where('phone', '0812345678')->first();

        // ยังไม่บังคับ: ทุกคนใช้งานได้ตามเดิม
        $this->actingAs($admin)->get('/')->assertOk();

        // เปิดบังคับจากหน้าตั้งค่า
        $base = ['school_name' => 'โรงเรียนทดสอบ', 'late_time' => '08:00', 'staff_late_time' => '08:00', 'periods_per_day' => 7, 'theme_color' => '#F26522'];
        $this->actingAs($admin)->post('/settings', $base + ['two_factor_required' => 1])->assertSessionHasNoErrors();
        $this->assertSame('1', Settings::get('two_factor_required'));

        // ผู้ดูแลระบบและฝ่ายการเงินถูกพาไปหน้าตั้งค่า 2 ขั้น ครูทั่วไปและผู้ปกครองไม่ถูกบังคับ
        $this->actingAs($admin)->get('/')->assertRedirect(route('two-factor.setup'));
        $this->actingAs($admin)->getJson('/search?q=a')->assertStatus(423);
        $this->actingAs($admin)->get('/two-factor')->assertOk()->assertSee('โรงเรียนกำหนดให้บัญชีนี้ต้องเปิดใช้');
        $this->actingAs($finance->fresh())->get('/invoices')->assertRedirect(route('two-factor.setup'));
        $this->actingAs($teacher)->get('/')->assertOk();
        $this->actingAs($parent)->get('/parent')->assertOk();

        // เปิดใช้แล้วเข้าหน้าอื่นได้
        $this->enableFor($admin);
        $this->actingAs($admin->fresh())->get('/')->assertOk();
        $this->actingAs($admin->fresh())->get('/settings')->assertOk()->assertSee('ตอนนี้เปิดใช้แล้ว 1 บัญชี');

        // ปิดการบังคับ: ฝ่ายการเงินที่ยังไม่ได้เปิดใช้กลับมาใช้งานได้
        $this->actingAs($admin->fresh())->post('/settings', $base)->assertSessionHasNoErrors();
        $this->actingAs($finance->fresh())->get('/invoices')->assertOk();
    }

    public function test_line_login_does_not_skip_the_second_step(): void
    {
        $teacher = $this->teacher();
        [$secret] = $this->enableFor($teacher);
        $teacher->forceFill(['line_user_id' => 'Uteacher'])->save();
        $this->post('/logout');

        Settings::set(['line_login_channel_id' => '1650000001', 'line_login_channel_secret' => 'secret']);
        Http::fake([
            'api.line.me/oauth2/v2.1/token' => Http::response(['id_token' => 'tok']),
            'api.line.me/oauth2/v2.1/verify' => Http::response(['sub' => 'Uteacher', 'aud' => '1650000001']),
        ]);
        $this->get(route('line.login'));
        $this->get(route('line.callback', ['code' => 'c', 'state' => session('line_login.state')]))->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        $this->post('/two-factor/challenge', ['code' => $this->nextCode($secret)])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($teacher);
    }
}
