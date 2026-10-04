<?php

namespace App\Services;

use App\Models\PushMessage;
use App\Models\PushSubscription;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * แจ้งเตือนบนอุปกรณ์ (Web Push) โดยไม่ใช้ไลบรารีเพิ่ม
 *
 * ส่ง "สัญญาณปลุก" ที่ไม่มีเนื้อหาไปยังบริการ push ของเบราว์เซอร์ (ยืนยันตัวด้วย VAPID)
 * แล้ว service worker (public/sw.js) จะมาดึงข้อความที่รออยู่จาก /push/pending เอง
 * จึงไม่ต้องเข้ารหัสเนื้อหา และข้อความไม่ผ่านเซิร์ฟเวอร์ของผู้ให้บริการ push
 */
class WebPush
{
    /** บริการ push ที่ยอมให้ส่งไป (กันการใช้ช่องสมัครรับแจ้งเตือนสั่งให้เซิร์ฟเวอร์ยิงไปที่อื่น) */
    private const HOSTS = ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com'];

    private const HOST_SUFFIXES = ['.push.services.mozilla.com', '.notify.windows.com', '.push.apple.com'];

    public static function allowedEndpoint(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        if (($parts['scheme'] ?? '') !== 'https' || $host === '') {
            return false;
        }
        if (in_array($host, self::HOSTS, true)) {
            return true;
        }
        foreach (self::HOST_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /** กุญแจสาธารณะ (base64url ของจุดบนเส้นโค้ง P-256 แบบไม่บีบอัด) สร้างคู่กุญแจให้อัตโนมัติครั้งแรก */
    public static function publicKey(): string
    {
        if (! Settings::get('webpush_public_key') || ! Settings::get('webpush_private_key')) {
            try {
                self::generateKeys();
            } catch (\Throwable $e) {
                // เซิร์ฟเวอร์ไม่มี OpenSSL ที่สร้างกุญแจได้: ปิดฟีเจอร์นี้ไว้ ไม่ให้หน้าเว็บล่ม
                Log::warning($e->getMessage());

                return '';
            }
        }

        return (string) Settings::get('webpush_public_key');
    }

    public static function generateKeys(): void
    {
        // ระบุไฟล์ค่าตั้งเอง เพราะ PHP บน Windows มักหา openssl.cnf ของระบบไม่เจอ
        $config = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1', 'config' => resource_path('openssl.cnf')];
        $key = openssl_pkey_new($config);
        if (! $key || ! openssl_pkey_export($key, $pem, null, $config)) {
            throw new RuntimeException('สร้างกุญแจ Web Push ไม่ได้: '.openssl_error_string());
        }
        $ec = openssl_pkey_get_details($key)['ec'];
        $point = "\x04".str_pad($ec['x'], 32, "\0", STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);

        Settings::set(['webpush_public_key' => self::b64($point), 'webpush_private_key' => $pem]);
    }

    /**
     * เก็บข้อความให้ผู้ใช้ที่เปิดรับแจ้งเตือนบนอุปกรณ์ไว้ (ยังไม่ส่งสัญญาณ)
     *
     * @return list<int> ผู้ใช้ที่มีอุปกรณ์รอรับ ส่งต่อให้ send()
     */
    public static function notify(Collection $users, string $text, ?string $url = null): array
    {
        $ids = $users->pluck('id')->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }
        $ids = PushSubscription::whereIn('user_id', $ids)->distinct()->pluck('user_id')->all();
        foreach ($ids as $id) {
            PushMessage::create(['user_id' => $id, 'text' => mb_substr($text, 0, 1000), 'url' => $url ? mb_substr($url, 0, 500) : null]);
        }

        return $ids;
    }

    /** ส่งสัญญาณปลุกไปทุกอุปกรณ์ของผู้ใช้ · อุปกรณ์ที่ยกเลิกไปแล้ว (404/410) ถูกลบออก */
    public static function send(array $userIds): void
    {
        if (! $userIds || self::publicKey() === '') {
            return;
        }
        $jwt = [];
        foreach (PushSubscription::whereIn('user_id', $userIds)->get() as $sub) {
            $parts = parse_url($sub->endpoint);
            $audience = $parts['scheme'].'://'.$parts['host'];
            try {
                $response = Http::timeout(10)->withHeaders([
                    'TTL' => '86400',
                    'Urgency' => 'normal',
                    'Authorization' => 'vapid t='.($jwt[$audience] ??= self::jwt($audience)).', k='.self::publicKey(),
                ])->withBody('', 'application/octet-stream')->post($sub->endpoint);

                if (in_array($response->status(), [404, 410], true)) {
                    $sub->delete();
                } elseif ($response->successful()) {
                    $sub->forceFill(['last_used_at' => now()])->save();
                } else {
                    Log::warning('Web Push ส่งไม่สำเร็จ', ['status' => $response->status(), 'host' => $parts['host']]);
                }
            } catch (\Throwable $e) {
                Log::warning('Web Push ส่งไม่สำเร็จ: '.$e->getMessage(), ['host' => $parts['host']]);
            }
        }
    }

    /** โทเคน VAPID (JWT ลงชื่อด้วย ES256) อายุ 12 ชั่วโมง */
    public static function jwt(string $audience): string
    {
        self::publicKey();
        $subject = filter_var(Settings::get('school_email'), FILTER_VALIDATE_EMAIL) ? 'mailto:'.Settings::get('school_email') : url('/');
        $unsigned = self::b64(json_encode(['typ' => 'JWT', 'alg' => 'ES256'])).'.'
            .self::b64(json_encode(['aud' => $audience, 'exp' => time() + 43200, 'sub' => $subject]));

        if (! openssl_sign($unsigned, $der, (string) Settings::get('webpush_private_key'), OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('ลงชื่อ VAPID ไม่ได้: '.openssl_error_string());
        }

        return $unsigned.'.'.self::b64(self::derToRaw($der));
    }

    /** ลายเซ็น ECDSA จาก openssl เป็นรูปแบบ DER ต้องแปลงเป็น r|s อย่างละ 32 ไบต์ตามที่ JWT ใช้ */
    public static function derToRaw(string $der): string
    {
        $pos = 2 + ((ord($der[1]) & 0x80) ? (ord($der[1]) & 0x7F) : 0);
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$pos + 1]);
            $out .= str_pad(ltrim(substr($der, $pos + 2, $len), "\0"), 32, "\0", STR_PAD_LEFT);
            $pos += 2 + $len;
        }

        return $out;
    }

    public static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
