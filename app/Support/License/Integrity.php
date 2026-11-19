<?php

namespace App\Support\License;

use Illuminate\Support\Facades\Cache;

/**
 * ตรวจว่าไฟล์ของระบบยังเป็นชุดเดิมที่ผู้พัฒนาส่งมา
 *
 * ชุดติดตั้งมีไฟล์ 2 ไฟล์ที่สร้างตอน build
 *   bootstrap/integrity.json  รายการ sha256 ของไฟล์ php และ blade ทั้งชุด
 *   bootstrap/integrity.sig   ลายเซ็น ed25519 ของไฟล์ json ข้างบน (เซ็นด้วย release key ของผู้พัฒนา)
 *
 * แก้ไฟล์ไหนก็ตาม sha256 จะไม่ตรง และสร้างรายการใหม่เองไม่ได้เพราะเซ็นลายเซ็นไม่ได้
 * ไฟล์ในชุดวิกฤต (CRITICAL) ไม่ตรง = ล็อกระบบ ไฟล์อื่นไม่ตรง = รายงานกลับร้านอย่างเดียว
 */
class Integrity
{
    public const MANIFEST = 'bootstrap/integrity.json';

    public const SIGNATURE = 'bootstrap/integrity.sig';

    public const CACHE_KEY = 'school.integrity.v1';

    /** โฟลเดอร์และนามสกุลที่นับเข้ารายการไฟล์ (ไม่รวม vendor, storage, public/css ที่สร้างใหม่ได้) */
    public const SCAN = [
        'app' => ['php'],
        'config' => ['php'],
        'routes' => ['php'],
        'database' => ['php'],
        'resources/views' => ['php'],
        'bootstrap' => ['php'],
    ];

    /**
     * ไฟล์ที่ถ้าถูกแก้ = ล็อกระบบทันที
     * คือไฟล์ที่ประกอบกันเป็นด่าน License และจุดที่แสดงเครดิตผู้พัฒนา
     */
    public const CRITICAL = [
        'app/Support/License/Client.php',
        'app/Support/License/Guard.php',
        'app/Support/License/Keys.php',
        'app/Support/License/Integrity.php',
        'app/Support/Brand.php',
        'app/Support/Installer.php',
        'app/Support/WebCron.php',
        'app/Http/Middleware/LicenseGate.php',
        'app/Http/Middleware/InstallGate.php',
        'app/Http/Controllers/LicenseController.php',
        'app/Http/Controllers/InstallController.php',
        'app/Http/Controllers/UpdateController.php',
        'bootstrap/app.php',
        'bootstrap/preboot.php',
        'config/license.php',
        'config/school.php',
        'routes/web.php',
        'routes/console.php',
        'resources/views/layouts/app.blade.php',
        'resources/views/install/layout.blade.php',
        'resources/views/license/locked.blade.php',
    ];

    public static function manifestPath(): string
    {
        $custom = config('license.manifest_path');

        return $custom ? (string) $custom : base_path(self::MANIFEST);
    }

    public static function signaturePath(): string
    {
        $custom = config('license.manifest_path');

        return $custom ? preg_replace('/\.json$/', '.sig', (string) $custom) : base_path(self::SIGNATURE);
    }

    /** มีรายการไฟล์มาด้วยไหม (ชุดติดตั้งของลูกค้ามี ซอร์สของนักพัฒนาไม่มี) */
    public static function hasManifest(): bool
    {
        return is_file(self::manifestPath()) && is_file(self::signaturePath());
    }

    /** ตรวจลายเซ็นไม่ได้เพราะโฮสต์ไม่มีส่วนขยาย sodium */
    public static function canVerify(): bool
    {
        return extension_loaded('sodium');
    }

    /** รายการไฟล์ที่ลายเซ็นถูกต้องแล้ว (null = ไม่มีไฟล์ ลายเซ็นผิด หรือตรวจไม่ได้) */
    public static function manifest(): ?array
    {
        if (! self::hasManifest() || ! self::canVerify()) {
            return null;
        }

        $json = (string) @file_get_contents(self::manifestPath());
        $sig = base64_decode(trim((string) @file_get_contents(self::signaturePath())), true);
        $public = Keys::raw(self::releasePublicKey());

        if ($json === '' || $sig === false || $public === null || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }

        try {
            if (! sodium_crypto_sign_verify_detached($sig, $json, $public)) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        $data = json_decode($json, true);

        return is_array($data) && ! empty($data['files']) && is_array($data['files']) ? $data : null;
    }

    /**
     * สรุปสถานะไฟล์ (แคชไว้ 1 ชั่วโมง)
     * state: ok · tampered (ไฟล์วิกฤตถูกแก้) · changed (ไฟล์อื่นถูกแก้) · unsigned (ไม่มีรายการหรือตรวจไม่ได้)
     */
    public static function status(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 3600, fn () => self::evaluate());
        } catch (\Throwable) {
            return self::evaluate();
        }
    }

    public static function flush(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable) {
            // ไม่มีแคชก็ไม่เป็นไร
        }
    }

    /** ไฟล์วิกฤตถูกแก้หรือหาย = ต้องล็อกระบบ */
    public static function broken(): bool
    {
        return self::status()['state'] === 'tampered';
    }

    protected static function evaluate(): array
    {
        $manifest = self::manifest();

        if (! $manifest) {
            return [
                'state' => 'unsigned',
                'checked' => 0,
                'critical' => [],
                'changed' => [],
                'version' => null,
                'reason' => self::hasManifest() ? (self::canVerify() ? 'ลายเซ็นของรายการไฟล์ไม่ถูกต้อง' : 'โฮสต์ไม่มีส่วนขยาย sodium จึงตรวจลายเซ็นไฟล์ไม่ได้') : 'ชุดนี้ไม่มีรายการไฟล์ (ซอร์สของนักพัฒนา)',
            ];
        }

        $critical = [];
        $changed = [];
        foreach ($manifest['files'] as $path => $hash) {
            if (! self::matches((string) $path, (string) $hash)) {
                if (in_array($path, self::CRITICAL, true)) {
                    $critical[] = $path;
                } else {
                    $changed[] = $path;
                }
            }
        }

        // ไฟล์วิกฤตที่หายไปจากรายการเอง ถือว่าผิดปกติเหมือนกัน
        foreach (self::CRITICAL as $path) {
            if (! array_key_exists($path, $manifest['files']) && ! in_array($path, $critical, true)) {
                $critical[] = $path;
            }
        }

        return [
            'state' => $critical ? 'tampered' : ($changed ? 'changed' : 'ok'),
            'checked' => count($manifest['files']),
            'critical' => $critical,
            'changed' => $changed,
            'version' => $manifest['version'] ?? null,
            'reason' => null,
        ];
    }

    /** กุญแจตรวจลายเซ็นรายการไฟล์ (สลับเป็นกุญแจทดสอบได้เฉพาะตอนรันชุดทดสอบ) */
    protected static function releasePublicKey(): string
    {
        $test = config('license.test_release_public_key');

        return Guard::testing() && is_string($test) && $test !== '' ? $test : Keys::RELEASE_PUBLIC;
    }

    protected static function matches(string $path, string $hash): bool
    {
        $full = base_path($path);

        return is_file($full) && hash_file('sha256', $full) === $hash;
    }

    /** สร้างรายการไฟล์จากโค้ดในเครื่อง (ใช้ตอน build เท่านั้น ลูกค้าสร้างเองได้แต่เซ็นลายเซ็นไม่ได้) */
    public static function build(?string $root = null): array
    {
        $root = rtrim($root ?: base_path(), '/\\');
        $files = [];

        foreach (self::SCAN as $dir => $extensions) {
            $base = $root.'/'.$dir;
            if (! is_dir($base)) {
                continue;
            }
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($items as $item) {
                /** @var \SplFileInfo $item */
                if (! $item->isFile() || ! in_array($item->getExtension(), $extensions, true)) {
                    continue;
                }
                $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
                // แคชที่ระบบสร้างเองไม่ใช่ไฟล์ของชุดติดตั้ง
                if (str_starts_with($relative, 'bootstrap/cache/')) {
                    continue;
                }
                $files[$relative] = hash_file('sha256', $item->getPathname());
            }
        }

        ksort($files);

        return [
            'product' => (string) config('license.product'),
            'version' => (string) config('school.version'),
            'generated_at' => now()->toIso8601String(),
            'algo' => 'sha256',
            'files' => $files,
        ];
    }
}
