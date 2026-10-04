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

    /**
     * QR ชำระบิล (Bill Payment, Tag 30) สำหรับบัญชีรับชำระของโรงเรียนที่ธนาคารออก Biller ID ให้
     * ธนาคารแจ้งผลกลับพร้อมเลขอ้างอิง ระบบจึงรู้ว่าเงินก้อนนี้เป็นของรายการใด
     */
    public static function billPayment(string $billerId, string $ref1, float $amount, ?string $ref2 = null): ?string
    {
        $billerId = preg_replace('/\D/', '', $billerId);
        $ref1 = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $ref1));
        if (strlen($billerId) !== 15 || $ref1 === '' || $amount <= 0) {
            return null;
        }
        $merchant = self::f('00', 'A000000677010112').self::f('01', $billerId).self::f('02', $ref1)
            .($ref2 ? self::f('03', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $ref2))) : '');
        $data = self::f('00', '01').self::f('01', '12').self::f('30', $merchant).self::f('53', '764')
            .self::f('54', number_format($amount, 2, '.', '')).self::f('58', 'TH').'6304';

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
