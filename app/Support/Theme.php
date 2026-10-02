<?php

namespace App\Support;

/**
 * สร้างชุดสีทั้งระบบจากสีหลักสีเดียว (ตั้งค่าได้ในหน้า "ตั้งค่าโรงเรียน")
 */
class Theme
{
    public const DEFAULT = '#F26522';

    public const PRESETS = [
        '#F26522' => 'ส้ม',
        '#E11D48' => 'แดงกุหลาบ',
        '#DB2777' => 'ชมพู',
        '#7C3AED' => 'ม่วง',
        '#4F46E5' => 'คราม',
        '#2563EB' => 'น้ำเงิน',
        '#1E3A8A' => 'กรมท่า',
        '#0891B2' => 'ฟ้า',
        '#0D9488' => 'เขียวหัวเป็ด',
        '#16A34A' => 'เขียว',
        '#CA8A04' => 'ทอง',
        '#92400E' => 'น้ำตาล',
    ];

    public static function color(): string
    {
        $c = (string) Settings::get('theme_color', self::DEFAULT);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtoupper($c) : self::DEFAULT;
    }

    /** CSS variables สำหรับใส่ใน :root */
    public static function css(?string $hex = null): string
    {
        $hex = $hex ?? self::color();
        [$h, $s, $l] = self::toHsl($hex);

        $vars = [
            '--sb-primary' => $hex,
            '--sb-primary-rgb' => implode(', ', self::toRgb($hex)),
            '--sb-primary-600' => self::fromHsl($h, $s, max(0, $l - 0.08)),
            '--sb-primary-700' => self::fromHsl($h, $s, max(0, $l - 0.16)),
            '--sb-primary-50' => self::mix($hex, '#FFFFFF', 0.92),
            '--sb-primary-100' => self::mix($hex, '#FFFFFF', 0.84),
            '--sb-primary-200' => self::mix($hex, '#FFFFFF', 0.68),
            // ไล่เฉดหัวแอป: อ่อนและเยื้องสีไปทางอุ่นเล็กน้อย → สีหลัก
            '--sb-grad-from' => self::fromHsl(fmod($h + 14, 360), min(1, $s + 0.05), min(0.72, $l + 0.1)),
            '--sb-grad-to' => self::fromHsl(fmod($h + 356, 360), $s, max(0, $l - 0.03)),
            '--bs-primary' => $hex,
            '--bs-primary-rgb' => implode(', ', self::toRgb($hex)),
            '--bs-primary-bg-subtle' => self::mix($hex, '#FFFFFF', 0.88),
            '--bs-primary-border-subtle' => self::mix($hex, '#FFFFFF', 0.65),
            '--bs-primary-text-emphasis' => self::fromHsl($h, $s, max(0, $l - 0.22)),
            '--bs-link-color' => self::fromHsl($h, $s, max(0, $l - 0.06)),
            '--bs-link-color-rgb' => implode(', ', self::toRgb(self::fromHsl($h, $s, max(0, $l - 0.06)))),
            '--bs-link-hover-color' => self::fromHsl($h, $s, max(0, $l - 0.16)),
        ];

        return implode(';', array_map(fn ($k, $v) => "{$k}:{$v}", array_keys($vars), $vars));
    }

    /** @return array{int,int,int} */
    public static function toRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function toHex(array $rgb): string
    {
        return '#'.implode('', array_map(fn ($c) => str_pad(dechex((int) max(0, min(255, round($c)))), 2, '0', STR_PAD_LEFT), $rgb));
    }

    private static function mix(string $a, string $b, float $t): string
    {
        $x = self::toRgb($a);
        $y = self::toRgb($b);

        return self::toHex([$x[0] + ($y[0] - $x[0]) * $t, $x[1] + ($y[1] - $x[1]) * $t, $x[2] + ($y[2] - $x[2]) * $t]);
    }

    /** @return array{float,float,float} h 0-360, s/l 0-1 */
    private static function toHsl(string $hex): array
    {
        [$r, $g, $b] = array_map(fn ($c) => $c / 255, self::toRgb($hex));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        if ($max === $min) {
            return [0.0, 0.0, $l];
        }
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match ($max) {
            $r => (($g - $b) / $d + ($g < $b ? 6 : 0)),
            $g => (($b - $r) / $d + 2),
            default => (($r - $g) / $d + 4),
        } * 60;

        return [$h, $s, $l];
    }

    private static function fromHsl(float $h, float $s, float $l): string
    {
        $h /= 360;
        if ($s == 0) {
            return self::toHex([$l * 255, $l * 255, $l * 255]);
        }
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $f = function ($t) use ($p, $q) {
            $t = $t < 0 ? $t + 1 : ($t > 1 ? $t - 1 : $t);

            return match (true) {
                $t < 1 / 6 => $p + ($q - $p) * 6 * $t,
                $t < 1 / 2 => $q,
                $t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
                default => $p,
            };
        };

        return self::toHex([$f($h + 1 / 3) * 255, $f($h) * 255, $f($h - 1 / 3) * 255]);
    }
}
