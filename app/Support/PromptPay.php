<?php

namespace App\Support;

/**
 * สร้างข้อความ QR พร้อมเพย์ (มาตรฐาน EMVCo / Thai QR Payment)
 * รองรับเบอร์มือถือ 10 หลัก, เลขประจำตัวผู้เสียภาษี/บัตรประชาชน 13 หลัก และ e-Wallet 15 หลัก
 */
class PromptPay
{
    public static function payload(string $id, ?float $amount = null): ?string
    {
        $id = preg_replace('/\D/', '', $id);
        $target = match (strlen($id)) {
            10 => self::f('01', '0066'.substr($id, 1)),   // มือถือ
            13 => self::f('02', $id),                     // เลขผู้เสียภาษี
            15 => self::f('03', $id),                     // e-Wallet
            default => null,
        };
        if (! $target) {
            return null;
        }

        $data = self::f('00', '01')
            .self::f('01', $amount ? '12' : '11')
            .self::f('29', self::f('00', 'A000000677010111').$target)
            .self::f('53', '764')
            .($amount ? self::f('54', number_format($amount, 2, '.', '')) : '')
            .self::f('58', 'TH')
            .'6304';

        return $data.strtoupper(str_pad(dechex(self::crc16($data)), 4, '0', STR_PAD_LEFT));
    }

    private static function f(string $id, string $value): string
    {
        return $id.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    /** CRC-16/CCITT-FALSE */
    private static function crc16(string $data): int
    {
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($b = 0; $b < 8; $b++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return $crc;
    }
}
