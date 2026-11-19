<?php

namespace App\Support\License;

use App\Support\Installer;
use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * คุยกับศูนย์ License ของร้าน (digitalstore) และเก็บ token ที่ร้านเซ็นไว้ในตาราง settings
 *
 * token รูปแบบ base64url(payload json).base64url(signature) เซ็นด้วย ed25519
 * แก้ค่าในตาราง settings เองไม่ได้ประโยชน์ เพราะลายเซ็นจะไม่ตรงกับ Keys::LICENSE_PUBLIC
 */
class Client
{
    public const KEYS = ['key', 'token', 'plan', 'trial', 'domain', 'expires_at', 'checked_at', 'grace_until', 'lock_reason'];

    /**
     * เปิดใช้งานด้วยคีย์ที่ลูกค้าซื้อมา (ผูกโดเมนครั้งแรก)
     * $persist = false ใช้ตอนติดตั้ง เพราะยังไม่มีตาราง settings ให้เก็บ token
     */
    public static function activate(string $key, ?string $domain = null, bool $persist = true): array
    {
        $key = strtoupper(trim($key));
        $res = self::post('activate', ['key' => $key, 'domain' => self::domain($domain), 'version' => self::version()]);

        if ($res['ok']) {
            if ($persist) {
                self::remember($res['json'], $key);
            }

            return ['ok' => true, 'message' => 'เปิดใช้งาน License สำเร็จ', 'key' => $key, 'json' => $res['json'], 'license' => $res['json']['license'] ?? []];
        }

        return $res;
    }

    /** ขอสิทธิ์ทดลองของโดเมนนี้ (1 โดเมนขอได้ครั้งเดียว ติดตั้งใหม่ไม่ได้เวลาเพิ่ม) */
    public static function trial(?string $domain = null, ?string $org = null, bool $persist = true): array
    {
        $res = self::post('trial', [
            'product' => (string) config('license.product'),
            'domain' => self::domain($domain),
            'version' => self::version(),
            'org' => $org,
        ]);

        if ($res['ok']) {
            $key = $res['json']['license']['key'] ?? null;
            if ($persist) {
                self::remember($res['json'], $key);
            }

            return ['ok' => true, 'message' => 'เริ่มทดลองใช้งานแล้ว', 'key' => $key, 'json' => $res['json'], 'license' => $res['json']['license'] ?? []];
        }

        return $res;
    }

    /** ตรวจกับร้านว่ายังใช้ได้อยู่ (วันละครั้ง) และรับ token ใหม่ */
    public static function refresh(): array
    {
        $key = (string) Settings::get('license.key', '');
        if ($key === '') {
            return ['ok' => false, 'reason' => 'unactivated', 'message' => 'ยังไม่ได้เปิดใช้งาน License'];
        }

        $res = self::post('verify', ['key' => $key, 'domain' => self::domain(), 'version' => self::version()]);

        if ($res['ok']) {
            self::remember($res['json'], $key);

            return ['ok' => true, 'message' => 'ตรวจสอบ License แล้ว'];
        }

        // ร้านตอบชัดเจนว่าใช้ไม่ได้แล้ว ลบ token ทิ้ง และจำสาเหตุไว้แสดงในหน้าล็อก
        if (in_array($res['reason'] ?? '', ['revoked', 'suspended', 'domain_mismatch', 'invalid'], true)) {
            Settings::set(['license.token' => '', 'license.lock_reason' => $res['reason']]);
            Guard::flush();
        }

        return $res;
    }

    /** แจ้งร้านว่าไฟล์ของระบบถูกแก้ */
    public static function report(array $files): bool
    {
        return self::post('report', [
            'domain' => self::domain(),
            'version' => self::version(),
            'file_count' => count($files),
            'files' => array_slice(array_values($files), 0, 50),
        ])['ok'];
    }

    // ------------------------------------------------------------- token

    /** payload ของ token ปัจจุบัน ตรวจลายเซ็นแล้ว (null = ไม่มี หรือลายเซ็นไม่ผ่าน) */
    public static function payload(): ?array
    {
        $token = (string) Settings::get('license.token', '');

        return $token === '' ? null : self::verifyToken($token);
    }

    /**
     * ตรวจลายเซ็น token ด้วย public key ที่ฝังในโค้ด
     * โฮสต์ที่ไม่มีส่วนขยาย sodium อ่าน payload ได้อย่างเดียว แล้วพึ่งการตรวจกับร้านรายวันแทน
     */
    public static function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $json = self::b64urlDecode($parts[0]);
        $payload = json_decode($json, true);
        if (! is_array($payload) || ! isset($payload['key'])) {
            return null;
        }

        if (! extension_loaded('sodium')) {
            return $payload + ['unsigned' => true];
        }

        $public = Keys::raw(self::publicKey());
        $sig = self::b64urlDecode($parts[1]);
        if ($public === null || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }

        try {
            return sodium_crypto_sign_verify_detached($sig, $json, $public) ? $payload : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** กุญแจสาธารณะที่ใช้ตรวจลายเซ็น (สลับเป็นกุญแจทดสอบได้เฉพาะตอนรันชุดทดสอบ) */
    protected static function publicKey(): string
    {
        $test = config('license.test_public_key');

        return Guard::testing() && is_string($test) && $test !== '' ? $test : Keys::LICENSE_PUBLIC;
    }

    /** เก็บ token และวันหมดอายุที่ร้านส่งมา */
    public static function remember(array $json, ?string $key): void
    {
        $license = $json['license'] ?? [];
        $payload = self::verifyToken((string) ($json['token'] ?? '')) ?? [];

        Settings::set([
            'license.key' => $key ?: (string) Settings::get('license.key', ''),
            'license.token' => (string) ($json['token'] ?? ''),
            'license.plan' => (string) ($license['plan'] ?? ($payload['product'] ?? '')),
            'license.trial' => ($license['trial'] ?? ($payload['trial'] ?? false)) ? '1' : '0',
            'license.domain' => (string) ($license['domain'] ?? ($payload['domain'] ?? self::domain())),
            'license.expires_at' => (string) ($payload['expires_at'] ?? ''),
            'license.checked_at' => (string) time(),
            'license.grace_until' => (string) (time() + (int) config('license.grace_days', 7) * 86400),
            'license.lock_reason' => '',
        ]);

        // เปิดใช้งานได้แล้ว ไม่ต้องใช้ช่วงผ่อนผันของระบบที่อัปเดตมาอีก (เฉพาะหลังติดตั้งเสร็จ ห้ามสร้างไฟล์ล็อกระหว่างติดตั้ง)
        if (Installer::isInstalled() && ! empty(Installer::lock()['upgrade_grace_until'])) {
            Installer::writeLock(Installer::installedVersion(), ['upgrade_grace_until' => null]);
        }

        Guard::flush();
    }

    public static function forget(): void
    {
        Settings::forget(array_map(fn ($k) => 'license.'.$k, self::KEYS));
        Guard::flush();
    }

    // ------------------------------------------------------------ helper

    /** โดเมนของระบบนี้ ตัด scheme / www / port / path ออกให้ตรงกับฝั่งร้าน */
    public static function domain(?string $explicit = null): string
    {
        $raw = $explicit ?: (string) config('app.url');
        if (trim($raw) === '' && app()->bound('request')) {
            $raw = (string) request()->getHost();
        }

        $d = strtolower(trim($raw));
        $d = (string) preg_replace('#^https?://#', '', $d);
        $d = (string) preg_replace('#/.*$#', '', $d);
        $d = (string) preg_replace('#:\d+$#', '', $d);
        $d = (string) preg_replace('#^www\.#', '', $d);

        return $d !== '' ? $d : 'localhost';
    }

    protected static function version(): string
    {
        return (string) config('school.version');
    }

    /** คืน ['ok' => true, 'json' => array] หรือ ['ok' => false, 'reason' => ..., 'message' => ...] */
    protected static function post(string $path, array $body): array
    {
        $url = rtrim((string) config('license.server'), '/').'/api/license/'.$path;

        try {
            $response = Http::timeout((int) config('license.timeout', 10))
                ->acceptJson()
                ->post($url, array_filter($body, fn ($v) => $v !== null));
        } catch (\Throwable $e) {
            Log::warning('license '.$path.' failed: '.$e->getMessage());

            return ['ok' => false, 'reason' => 'offline', 'message' => 'ติดต่อศูนย์ License ไม่ได้ ตรวจอินเทอร์เน็ตของโฮสต์แล้วลองใหม่'];
        }

        $json = $response->json();
        if (is_array($json) && array_key_exists('ok', $json)) {
            return $json['ok']
                ? ['ok' => true, 'json' => $json]
                : ['ok' => false, 'reason' => $json['reason'] ?? 'invalid', 'message' => $json['message'] ?? 'License ใช้ไม่ได้'];
        }

        return ['ok' => false, 'reason' => 'server', 'message' => 'ศูนย์ License ตอบกลับผิดปกติ (HTTP '.$response->status().')'];
    }

    protected static function b64urlDecode(string $input): string
    {
        $pad = strlen($input) % 4;
        if ($pad) {
            $input .= str_repeat('=', 4 - $pad);
        }

        return (string) base64_decode(strtr($input, '-_', '+/'));
    }
}
