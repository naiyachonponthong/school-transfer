<?php

namespace Tests\Feature;

use App\Support\Installer;
use App\Support\License\Guard;
use App\Support\License\Integrity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** รายการไฟล์ที่เซ็นแล้ว: ไฟล์วิกฤตถูกแก้ = ล็อก ไฟล์อื่นถูกแก้ = รายงานอย่างเดียว */
class IntegrityTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    private string $manifest;

    private string $lockFile;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('sodium')) {
            $this->markTestSkipped('ต้องเปิดส่วนขยาย sodium');
        }
        $pair = sodium_crypto_sign_keypair();
        $this->secret = sodium_crypto_sign_secretkey($pair);
        $this->manifest = sys_get_temp_dir().'/integrity-'.uniqid().'.json';
        $this->lockFile = tempnam(sys_get_temp_dir(), 'lock');
        file_put_contents($this->lockFile, json_encode(['version' => config('school.version')]));
        Installer::flushLock();
        config([
            'license.test_release_public_key' => base64_encode(sodium_crypto_sign_publickey($pair)),
            'license.manifest_path' => $this->manifest,
            'school.test_lock_path' => $this->lockFile,
        ]);
        Guard::flush();
    }

    protected function tearDown(): void
    {
        if (! isset($this->lockFile)) { // ข้ามเทสต์เพราะไม่มี sodium
            parent::tearDown();

            return;
        }
        @unlink($this->manifest);
        @unlink(preg_replace('/\.json$/', '.sig', $this->manifest));
        @unlink($this->lockFile);
        Installer::flushLock();
        parent::tearDown();
    }

    /** เขียนรายการไฟล์ของโค้ดในเครื่องนี้ (แก้ได้ก่อนเซ็น) แล้วเซ็นด้วยกุญแจทดสอบ */
    private function sign(?callable $edit = null): void
    {
        $data = Integrity::build();
        if ($edit) {
            $data = $edit($data);
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->manifest, $json);
        file_put_contents(preg_replace('/\.json$/', '.sig', $this->manifest), base64_encode(sodium_crypto_sign_detached($json, $this->secret)));
        Guard::flush();
    }

    public function test_manifest_covers_critical_files(): void
    {
        $files = Integrity::build()['files'];
        foreach (Integrity::CRITICAL as $path) {
            $this->assertArrayHasKey($path, $files, $path.' ต้องอยู่ในรายการไฟล์');
        }
        $this->assertArrayNotHasKey('bootstrap/cache/packages.php', $files);
    }

    public function test_untouched_files_pass(): void
    {
        $this->sign();
        $status = Integrity::status();

        $this->assertSame('ok', $status['state']);
        $this->assertGreaterThan(100, $status['checked']);
    }

    public function test_changed_normal_file_is_reported_but_not_locked(): void
    {
        $this->sign(function ($d) {
            $d['files']['app/Support/Thai.php'] = str_repeat('0', 64);

            return $d;
        });

        $status = Integrity::status();
        $this->assertSame('changed', $status['state']);
        $this->assertSame(['app/Support/Thai.php'], $status['changed']);
        $this->assertNotSame('tampered', Guard::status()['reason']);
    }

    public function test_changed_or_missing_critical_file_locks_the_system(): void
    {
        $this->sign(function ($d) {
            $d['files']['app/Support/License/Guard.php'] = str_repeat('0', 64);
            unset($d['files']['app/Http/Middleware/LicenseGate.php']);

            return $d;
        });

        $status = Integrity::status();
        $this->assertSame('tampered', $status['state']);
        $this->assertEqualsCanonicalizing(['app/Support/License/Guard.php', 'app/Http/Middleware/LicenseGate.php'], $status['critical']);
        $this->assertSame('tampered', Guard::status()['reason']);
    }

    public function test_forged_manifest_is_not_trusted(): void
    {
        $this->sign();
        // แก้รายการไฟล์หลังเซ็น ลายเซ็นจะไม่ตรง
        file_put_contents($this->manifest, str_replace('"algo"', '"algo2"', (string) file_get_contents($this->manifest)));
        Guard::flush();

        $this->assertSame('unsigned', Integrity::status()['state']);
    }

    public function test_deleting_manifest_of_signed_install_locks(): void
    {
        file_put_contents($this->lockFile, json_encode(['version' => config('school.version'), 'signed' => true]));
        Installer::flushLock();
        Guard::flush();

        $this->assertFalse(Integrity::hasManifest());
        $this->assertSame('tampered', Guard::status()['reason']);
    }
}
