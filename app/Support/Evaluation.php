<?php

namespace App\Support;

/**
 * คุณลักษณะอันพึงประสงค์ 8 ประการ และการอ่าน คิดวิเคราะห์ และเขียน (หลักสูตรแกนกลางฯ 2551)
 * ระดับผลการประเมิน 4 ระดับ: 3 ดีเยี่ยม · 2 ดี · 1 ผ่าน · 0 ไม่ผ่าน
 */
class Evaluation
{
    public const TRAITS = [
        1 => 'รักชาติ ศาสน์ กษัตริย์',
        2 => 'ซื่อสัตย์สุจริต',
        3 => 'มีวินัย',
        4 => 'ใฝ่เรียนรู้',
        5 => 'อยู่อย่างพอเพียง',
        6 => 'มุ่งมั่นในการทำงาน',
        7 => 'รักความเป็นไทย',
        8 => 'มีจิตสาธารณะ',
    ];

    public const LEVELS = [
        3 => 'ดีเยี่ยม',
        2 => 'ดี',
        1 => 'ผ่าน',
        0 => 'ไม่ผ่าน',
    ];

    /**
     * สรุปผลคุณลักษณะทั้ง 8 ข้อเป็นระดับเดียว (ต้องประเมินครบ 8 ข้อ)
     * - ดีเยี่ยม: ดีเยี่ยม 5–8 ข้อ และไม่มีข้อใดต่ำกว่าดี
     * - ดี: ดีเยี่ยม 1–4 ข้อหรือดีทั้งหมด และไม่มีข้อใดต่ำกว่าดี / หรือ ดีขึ้นไป 5–7 ข้อ และไม่มีข้อใดต่ำกว่าผ่าน
     * - ผ่าน: ผ่านทั้ง 8 ข้อ หรือดีขึ้นไป 1–4 ข้อ และไม่มีข้อใดต่ำกว่าผ่าน
     * - ไม่ผ่าน: มีข้อใดข้อหนึ่งไม่ผ่าน
     *
     * @param  array<int|string, int|null>  $traits
     */
    public static function summarize(array $traits): ?int
    {
        $values = array_values(array_filter(
            array_map(fn ($k) => $traits[$k] ?? $traits[(string) $k] ?? null, array_keys(self::TRAITS)),
            fn ($v) => $v !== null && $v !== '',
        ));
        if (count($values) < count(self::TRAITS)) {
            return null;
        }
        $values = array_map('intval', $values);
        if (min($values) === 0) {
            return 0;
        }
        $excellent = count(array_filter($values, fn ($v) => $v === 3));
        $goodUp = count(array_filter($values, fn ($v) => $v >= 2));
        if ($goodUp === count($values)) {
            return $excellent >= 5 ? 3 : 2;
        }

        return $goodUp >= 5 ? 2 : 1;
    }

    public static function label(?int $level): string
    {
        return $level === null ? '-' : self::LEVELS[$level];
    }

    public static function color(?int $level): string
    {
        return match ($level) {
            null => 'secondary',
            3 => 'success',
            2 => 'info',
            1 => 'warning',
            default => 'danger',
        };
    }
}
