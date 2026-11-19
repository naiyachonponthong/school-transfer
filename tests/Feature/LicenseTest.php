<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Installer;
use App\Support\License\Client;
use App\Support\License\Guard;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** ด่าน License: ใช้กุญแจทดสอบแทนกุญแจของร้าน และไฟล์ล็อกชั่วคราว ไม่ติดต่อร้านจริง */
class LicenseTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    private string $lockFile;

    /** @var \Closure คำตอบของร้านจำลอง */
    private \Closure $store;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('sodium')) {
            $this->markTestSkipped('ต้องเปิดส่วนขยาย sodium');
        }
        $pair = sodium_crypto_sign_keypair();
        $this->secret = sodium_crypto_sign_secretkey($pair);
        $this->lockFile = tempnam(sys_get_temp_dir(), 'lock');
        config([
            'license.test_public_key' => base64_encode(sodium_crypto_sign_publickey($pair)),
            'license.test_enforce' => true,
            'license.server' => 'https://store.test',
            'license.manifest_path' => sys_get_temp_dir().'/no-manifest-'.uniqid().'.json',
            'school.test_lock_path' => $this->lockFile,
            'app.url' => 'https://school.example.com',
        ]);
        $this->installed();
        $this->store = fn () => Http::response(['ok' => false, 'reason' => 'invalid', 'message' => 'ไม่รู้จัก'], 404);
        Http::fake(fn ($request) => ($this->store)($request));
        Guard::flush();
    }

    protected function tearDown(): void
    {
        if (! isset($this->lockFile)) { // ข้ามเทสต์เพราะไม่มี sodium
            parent::tearDown();

            return;
        }
        @unlink($this->lockFile);
        Installer::flushLock();
        parent::tearDown();
    }

    private function installed(array $extra = []): void
    {
        file_put_contents($this->lockFile, json_encode($extra + ['version' => config('school.version'), 'installed_at' => now()->toDateTimeString()]));
        Installer::flushLock();
    }

    private function token(array $payload): string
    {
        $payload += ['key' => 'SCH1-TEST-AAAA-BBBB', 'domain' => 'school.example.com', 'product' => 'school', 'status' => 'active',
            'trial' => false, 'issued_at' => time(), 'expires_at' => time() + 365 * 86400, 'nonce' => bin2hex(random_bytes(4))];
        $json = json_encode($payload);
        $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');

        return $b64($json).'.'.$b64(sodium_crypto_sign_detached($json, $this->secret));
    }

    private function storeAnswers(array $payload = [], bool $trial = false): void
    {
        $token = $this->token($payload + ['trial' => $trial]);
        $this->store = fn () => Http::response(['ok' => true, 'token' => $token,
            'license' => ['key' => 'SCH1-TEST-AAAA-BBBB', 'plan' => 'school', 'trial' => $trial, 'domain' => 'school.example.com']]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_token_signature_is_checked_with_public_key(): void
    {
        $token = $this->token([]);
        $this->assertSame('SCH1-TEST-AAAA-BBBB', Client::verifyToken($token)['key']);

        // แก้ payload (เช่นยืดวันหมดอายุ) แล้วลายเซ็นไม่ตรง
        [$p, $s] = explode('.', $token);
        $forged = rtrim(strtr(base64_encode(str_replace('"active"', '"activX"', base64_decode(strtr($p, '-_', '+/')))), '+/', '-_'), '=').'.'.$s;
        $this->assertNull(Client::verifyToken($forged));
        $this->assertNull(Client::verifyToken('garbage'));
    }

    public function test_domain_is_normalized(): void
    {
        $this->assertSame('school.example.com', Client::domain('https://www.School.Example.com:8443/sub/path'));
        $this->assertSame('localhost', Client::domain('http://localhost:8013'));
    }

    public function test_installed_system_without_license_is_locked(): void
    {
        $status = Guard::status();
        $this->assertSame('locked', $status['state']);
        $this->assertSame('unactivated', $status['reason']);

        // ผู้ดูแลถูกพาไปหน้า License · ครูเห็นหน้าล็อก · API ได้ 503
        $this->actingAs($this->admin())->get('/')->assertRedirect(route('settings.license').'#workspace');
        $this->actingAs($this->admin())->get(route('settings.license'))->assertOk()->assertSee('ยังไม่ได้เปิดใช้งาน License');
        $this->actingAs(User::factory()->create(['role' => 'teacher']))->get('/')->assertStatus(503)->assertSee('ระบบถูกล็อก');
        $this->getJson('/')->assertStatus(503);
        \Illuminate\Support\Facades\Auth::logout();
        $this->get('/login')->assertOk();
    }

    public function test_activate_with_key_unlocks_and_remembers_token(): void
    {
        $this->storeAnswers();
        $this->actingAs($this->admin())->post(route('settings.license.activate'), ['license_key' => 'sch1-test-aaaa-bbbb'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('SCH1-TEST-AAAA-BBBB', Settings::get('license.key'));
        $this->assertSame('ok', Guard::status()['state']);
        $this->actingAs(User::factory()->create(['role' => 'teacher']))->get('/')->assertOk();
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/activate') && $r['domain'] === 'school.example.com');
    }

    public function test_only_admin_can_manage_license(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->actingAs($teacher)->post(route('settings.license.activate'), ['license_key' => 'SCH1-TEST-AAAA-BBBB'])->assertForbidden();
    }

    public function test_trial_shows_notice_with_days_left(): void
    {
        $this->storeAnswers(['expires_at' => time() + 30 * 86400], true);
        $this->actingAs($admin = $this->admin())->post(route('settings.license.trial'))->assertSessionHas('success');

        $status = Guard::status();
        $this->assertSame('trial', $status['state']);
        $this->assertSame(30, $status['days_left']);
        $this->actingAs($admin)->get('/')->assertOk()->assertSee('ทดลองใช้งาน เหลืออีก 30 วัน');
    }

    public function test_store_rejections_lock_the_system(): void
    {
        $this->storeAnswers();
        Client::activate('SCH1-TEST-AAAA-BBBB');
        $this->store = fn () => Http::response(['ok' => false, 'reason' => 'revoked', 'message' => 'ยกเลิกแล้ว'], 403);

        $this->assertFalse(Client::refresh()['ok']);
        Guard::flush();
        $status = Guard::status();
        $this->assertSame('locked', $status['state']);
        $this->assertSame('revoked', $status['reason']);
    }

    public function test_token_for_another_domain_is_locked(): void
    {
        $this->storeAnswers(['domain' => 'other-school.com']);
        Client::activate('SCH1-TEST-AAAA-BBBB');

        $this->assertSame('domain_mismatch', Guard::status()['reason']);
    }

    public function test_expired_trial_is_locked_when_store_does_not_renew(): void
    {
        $this->storeAnswers(['expires_at' => time() - 60], true);
        Client::trial();
        $this->store = fn () => Http::response(['ok' => false, 'reason' => 'expired', 'message' => 'หมดอายุ'], 403);
        Guard::flush();

        $this->assertSame('trial_expired', Guard::status()['reason']);
    }

    public function test_offline_store_gives_grace_then_locks(): void
    {
        $this->storeAnswers();
        Client::activate('SCH1-TEST-AAAA-BBBB');
        $this->store = fn () => throw new ConnectionException('down');

        // ตรวจครั้งล่าสุดเมื่อ 2 วันก่อน ยังอยู่ในช่วงผ่อนผัน
        Settings::set(['license.checked_at' => (string) (time() - 2 * 86400), 'license.grace_until' => (string) (time() + 3 * 86400)]);
        Guard::flush();
        $this->assertSame('grace', Guard::status()['state']);

        Settings::set(['license.grace_until' => (string) (time() - 60)]);
        Guard::flush();
        $this->assertSame('offline', Guard::status()['reason']);
    }

    public function test_clock_set_back_is_locked(): void
    {
        $this->storeAnswers();
        Client::activate('SCH1-TEST-AAAA-BBBB');
        Settings::set(['license.checked_at' => (string) (time() + 3 * 86400)]);
        Guard::flush();

        $this->assertSame('clock', Guard::status()['reason']);
    }

    public function test_upgraded_system_gets_grace_period(): void
    {
        $this->installed(['upgrade_grace_until' => time() + 20 * 86400]);
        Guard::flush();
        $status = Guard::status();

        $this->assertSame('grace', $status['state']);
        $this->assertSame('upgrade', $status['grace_kind']);
        $this->actingAs($this->admin())->get('/')->assertOk()->assertSee('ต้องเปิดใช้งานภายใน 20 วัน');
    }

    public function test_update_from_old_version_requests_trial_or_grants_grace(): void
    {
        // อัปเดตจาก 1.0.0 (ก่อนมี License): ร้านให้ทดลอง > ได้ token
        $this->installed(['version' => '1.0.0']);
        $this->storeAnswers(['expires_at' => time() + 30 * 86400], true);
        $this->actingAs($this->admin())->post(route('update.run'), ['backup' => 0])->assertRedirect(route('home'));
        $this->assertNotEmpty(Settings::get('license.token'));
        Installer::flushLock();
        $this->assertSame(config('school.version'), Installer::installedVersion());

        // ติดต่อร้านไม่ได้: ผ่อนผัน 30 วันแทน
        Client::forget();
        $this->installed(['version' => '1.0.0']);
        $this->store = fn () => throw new ConnectionException('down');
        $this->actingAs($this->admin())->post(route('update.run'), ['backup' => 0])->assertRedirect(route('home'));
        Installer::flushLock();
        $this->assertGreaterThan(time() + 29 * 86400, (int) Installer::lock()['upgrade_grace_until']);
        Guard::flush();
        $this->assertSame('grace', Guard::status()['state']);
    }

    public function test_installer_license_step_keeps_result_in_session(): void
    {
        $this->storeAnswers(['expires_at' => time() + 30 * 86400], true);
        $this->post(route('install.license.save'), ['mode' => 'trial'])->assertRedirect(route('install.database'));
        $this->assertTrue(session('install.license.license.trial'));
        $this->assertSame('', (string) Settings::get('license.token', '')); // ยังไม่บันทึกจนถึงขั้นติดตั้ง

        $this->post(route('install.license.save'), ['mode' => 'skip'])->assertRedirect(route('install.database'));
        $this->assertNull(session('install.license'));

        $this->post(route('install.license.save'), ['mode' => 'key', 'license_key' => ''])->assertSessionHasErrors('license_key');
    }
}
