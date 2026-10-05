<?php

namespace App\Support;

/**
 * รหัสผ่านใช้ครั้งเดียวตามเวลา (TOTP, RFC 6238) แบบที่แอป Google Authenticator / Microsoft Authenticator ใช้
 * HMAC-SHA1 · รหัส 6 หลัก · เปลี่ยนทุก 30 วินาที — เขียนเองเพื่อไม่ต้องเพิ่ม package
 */
class Totp
{
    public const PERIOD = 30;

    public const DIGITS = 6;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** กุญแจลับใหม่แบบ base32 (160 บิต ตามที่ RFC 4226 แนะนำ) */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /** ลำดับช่วงเวลา 30 วินาทีของเวลาปัจจุบัน */
    public static function step(?int $timestamp = null): int
    {
        return intdiv($timestamp ?? now()->timestamp, self::PERIOD);
    }

    public static function codeAt(string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * ตรวจรหัส 6 หลัก ยอมให้นาฬิกาของมือถือคลาดได้ ±1 ช่วง
     *
     * @param  int|null  $lastStep  ช่วงเวลาของรหัสล่าสุดที่บัญชีนี้ใช้ไปแล้ว รหัสของช่วงนั้นหรือก่อนหน้าใช้ซ้ำไม่ได้
     * @return int|null ช่วงเวลาของรหัสที่ตรง (ให้ผู้เรียกบันทึกไว้กันใช้ซ้ำ) หรือว่างถ้ารหัสผิด
     */
    public static function verify(string $secret, string $code, ?int $lastStep = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code);
        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $code)) {
            return null;
        }
        $now = self::step();
        foreach ([0, -1, 1] as $drift) {
            $step = $now + $drift;
            if (($lastStep === null || $step > $lastStep) && hash_equals(self::codeAt($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    /** ที่อยู่ที่ใส่ใน QR ให้แอปสร้างรหัสสแกน */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account).'?'.http_build_query(
            ['secret' => $secret, 'issuer' => $issuer, 'algorithm' => 'SHA1', 'digits' => self::DIGITS, 'period' => self::PERIOD], '', '&', PHP_QUERY_RFC3986);
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $secret): string
    {
        $bits = '';
        foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $secret))) as $char) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr(bindec($chunk));
            }
        }

        return $out;
    }
}
