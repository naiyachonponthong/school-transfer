<?php

namespace App\Support\License;

use App\Support\Installer;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;

/**
 * ตัดสินว่าระบบนี้ใช้งานได้หรือไม่ จากลายเซ็นของ token + การตรวจกับร้านรายวัน
 *
 * สถานะ: ok (ใช้ได้) · trial (ทดลอง) · grace (ติดต่อร้านไม่ได้ ใช้ต่อได้ชั่วคราว) · locked (ล็อก)
 */
class Guard
{
    public const CACHE_KEY = 'school.license.v1';

    public const CACHE_MINUTES = 60;

    /** ข้อความของแต่ละสาเหตุที่ล็อก */
    public const REASONS = [
        'unactivated' => 'ระบบนี้ยังไม่ได้เปิดใช้งาน License',
        'invalid_token' => 'ข้อมูล License ไม่ถูกต้อง กรุณาเปิดใช้งานใหม่อีกครั้ง',
        'expired' => 'License หมดอายุแล้ว',
        'trial_expired' => 'ครบกำหนดทดลองใช้ 30 วันแล้ว',
        'domain_mismatch' => 'License นี้ผูกกับโดเมนอื่น',
        'revoked' => 'License ถูกยกเลิกโดยผู้พัฒนา',
        'suspended' => 'License ถูกระงับชั่วคราว',
        'offline' => 'ติดต่อศูนย์ License ไม่ได้เกินกำหนดผ่อนผัน',
        'clock' => 'เวลาของเครื่องแม่ข่ายถูกตั้งย้อนหลัง',
        'tampered' => 'ไฟล์ของระบบถูกแก้ไข',
    ];

    /**
     * กำลังรันชุดทดสอบของนักพัฒนาจริงหรือไม่
     * ต้องมีคลาสของ PHPUnit โหลดอยู่ด้วย ชุดติดตั้งของลูกค้าไม่มี (composer install --no-dev)
     * จึงตั้ง APP_ENV=testing ใน .env เพื่อข้ามด่าน License ไม่ได้
     */
    public static function testing(): bool
    {
        return app()->runningUnitTests() && class_exists(\PHPUnit\Framework\TestCase::class, false);
    }

    public static function status(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_MINUTES * 60, fn () => self::evaluate());
        } catch (\Throwable) {
            return self::evaluate();
        }
    }

    public static function allowed(): bool
    {
        return self::status()['state'] !== 'locked';
    }

    public static function locked(): bool
    {
        return ! self::allowed();
    }

    /** เหลือเวลาผ่อนผันของระบบที่อัปเดตมาจากรุ่นก่อนมี License กี่วัน (0 = ไม่มี) */
    public static function upgradeGraceLeft(): int
    {
        $until = (int) (Installer::lock()['upgrade_grace_until'] ?? 0);

        return $until > time() ? (int) ceil(($until - time()) / 86400) : 0;
    }

    /** ชุดที่ติดตั้งไว้มีรายการไฟล์ที่เซ็นแล้วหรือไม่ (บันทึกไว้ตอนติดตั้ง/อัปเดต) */
    public static function signedAtInstall(): bool
    {
        return (bool) (Installer::lock()['signed'] ?? false);
    }

    public static function flush(): void
    {
        Integrity::flush();

        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable) {
            // ไม่มีแคชก็ไม่เป็นไร
        }
    }

    /** แถบเตือนบนหน้าจอ: ทดลองใช้ ใกล้หมดอายุ หรือติดต่อร้านไม่ได้ */
    public static function notice(): ?array
    {
        $s = self::status();
        $days = $s['days_left'];

        if ($s['state'] === 'grace') {
            return $s['grace_kind'] === 'upgrade'
                ? ['tone' => 'danger', 'text' => 'ระบบนี้ยังไม่ได้เปิดใช้งาน License ต้องเปิดใช้งานภายใน '.$s['grace_days_left'].' วัน']
                : ['tone' => 'warning', 'text' => 'ติดต่อศูนย์ License ไม่ได้ ระบบยังใช้งานได้อีก '.$s['grace_days_left'].' วัน'];
        }
        if ($s['state'] === 'trial') {
            return ['tone' => $days !== null && $days <= 7 ? 'danger' : 'info',
                'text' => $days === null ? 'กำลังทดลองใช้งาน' : 'ทดลองใช้งาน เหลืออีก '.$days.' วัน'];
        }
        if ($s['state'] === 'ok' && $days !== null && $days <= (int) config('license.warn_days', 7)) {
            return ['tone' => 'warning', 'text' => 'License จะหมดอายุในอีก '.$days.' วัน'];
        }

        return null;
    }

    // ----------------------------------------------------------- ตัดสิน

    protected static function evaluate(): array
    {
        // ยังติดตั้งไม่เสร็จ ปล่อยให้ตัวติดตั้งทำงานไปก่อน
        if (! Installer::isInstalled()) {
            return self::ok('ok', null);
        }

        // ไฟล์ของระบบถูกแก้ หรือรายการไฟล์ที่เคยมาพร้อมชุดติดตั้งถูกลบทิ้ง
        $files = Integrity::status();
        if ($files['state'] === 'tampered'
            || ($files['state'] === 'unsigned' && Integrity::canVerify() && self::signedAtInstall())) {
            return self::lock('tampered');
        }

        $payload = Client::payload();
        if (! $payload) {
            // สาเหตุที่ร้านบอกไว้ตอนตรวจครั้งล่าสุด (ถูกยกเลิก ถูกระงับ ฯลฯ) ตรงกว่าการเดาเอง
            $saved = (string) Settings::get('license.lock_reason', '');
            $hasKey = (string) Settings::get('license.key', '') !== '';

            $reason = match (true) {
                $saved === 'invalid' => 'invalid_token',
                isset(self::REASONS[$saved]) => $saved,
                default => $hasKey ? 'invalid_token' : 'unactivated',
            };

            // ระบบเดิมที่เพิ่งอัปเดตมาเป็นรุ่นที่มี License ให้เวลาไปหาคีย์ก่อน
            $graceLeft = self::upgradeGraceLeft();
            if ($graceLeft > 0 && in_array($reason, ['unactivated', 'invalid_token'], true)) {
                return self::ok('grace', null, $graceLeft, 'upgrade');
            }

            return self::lock($reason);
        }

        if (($payload['domain'] ?? null) && $payload['domain'] !== Client::domain()) {
            return self::lock('domain_mismatch');
        }

        if (in_array($payload['status'] ?? '', ['revoked', 'suspended'], true)) {
            return self::lock($payload['status']);
        }

        // ตั้งนาฬิกาเครื่องย้อนหลังเพื่อยืดอายุ
        $checkedAt = (int) Settings::get('license.checked_at', 0);
        if ($checkedAt > 0 && time() < $checkedAt - 86400) {
            return self::lock('clock');
        }

        $trial = (bool) ($payload['trial'] ?? false);
        $expires = isset($payload['expires_at']) ? (int) $payload['expires_at'] : null;

        // หมดอายุแล้ว ลองถามร้านอีกครั้งเผื่อเพิ่งต่ออายุ
        if ($expires !== null && $expires < time()) {
            if (Client::refresh()['ok']) {
                $payload = Client::payload() ?: $payload;
                $expires = isset($payload['expires_at']) ? (int) $payload['expires_at'] : null;
            }
            if ($expires !== null && $expires < time()) {
                return self::lock($trial ? 'trial_expired' : 'expired', $payload);
            }
        }

        // ถึงรอบตรวจกับร้าน (วันละครั้ง)
        $stale = time() - $checkedAt > (int) config('license.refresh_hours', 24) * 3600;
        if ($stale) {
            $res = Client::refresh();
            if ($res['ok']) {
                $payload = Client::payload() ?: $payload;
            } elseif (in_array($res['reason'] ?? '', ['revoked', 'suspended', 'domain_mismatch', 'invalid'], true)) {
                return self::lock($res['reason'] === 'invalid' ? 'invalid_token' : $res['reason'], $payload);
            } else {
                // เน็ตล่ม: ใช้ต่อได้จนหมดเวลาผ่อนผัน
                $graceUntil = (int) Settings::get('license.grace_until', 0);
                if (time() > $graceUntil) {
                    return self::lock('offline', $payload);
                }

                return self::ok('grace', $payload, (int) ceil(($graceUntil - time()) / 86400));
            }
        }

        return self::ok(($payload['trial'] ?? false) ? 'trial' : 'ok', $payload);
    }

    protected static function ok(string $state, ?array $payload, int $graceDaysLeft = 0, string $graceKind = 'offline'): array
    {
        $expires = isset($payload['expires_at']) ? (int) $payload['expires_at'] : null;

        return [
            'state' => $state,
            'reason' => null,
            'message' => null,
            'plan' => $payload['product'] ?? (string) Settings::get('license.plan', ''),
            'trial' => (bool) ($payload['trial'] ?? false),
            'key' => (string) Settings::get('license.key', ''),
            'domain' => $payload['domain'] ?? null,
            'expires_at' => $expires,
            'days_left' => $expires ? max(0, (int) ceil(($expires - time()) / 86400)) : null,
            'grace_days_left' => $graceDaysLeft,
            'grace_kind' => $graceKind,
            'checked_at' => (int) Settings::get('license.checked_at', 0),
            'unsigned' => (bool) ($payload['unsigned'] ?? false),
        ];
    }

    protected static function lock(string $reason, ?array $payload = null): array
    {
        return array_merge(self::ok('locked', $payload), [
            'reason' => $reason,
            'message' => self::REASONS[$reason] ?? 'ใช้งาน License ไม่ได้',
        ]);
    }
}
