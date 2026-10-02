<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * บาร์โค้ด Code 128 (มาตรฐานที่เครื่องอ่านบาร์โค้ดห้องสมุดทั่วไปอ่านได้) วาดเป็น SVG ฝั่งเซิร์ฟเวอร์ — พิมพ์คมชัด ไม่ต้องพึ่ง JavaScript
 * ใช้ชุด B (ตัวอักษร/ตัวเลข) สลับชุด C (ตัวเลขคู่) อัตโนมัติเมื่อมีตัวเลขยาว ให้แท่งสั้นลง
 */
class Barcode
{
    /** ความกว้างแท่ง-ช่องว่าง ของค่า 0–106 (106 = Stop) */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const START_B = 104;

    private const START_C = 105;

    private const CODE_B = 100;

    private const CODE_C = 99;

    private const STOP = 106;

    /**
     * ค่าสัญลักษณ์ (รวม start + checksum + stop)
     *
     * @return list<int>
     */
    public static function encode(string $text, bool $autoC = true): array
    {
        if ($text === '' || preg_match('/[^\x20-\x7E]/', $text)) {
            throw new InvalidArgumentException('บาร์โค้ดใช้ได้เฉพาะตัวอักษรอังกฤษ ตัวเลข และสัญลักษณ์พื้นฐาน');
        }
        $len = strlen($text);
        $digitsAt = function (int $i) use ($text, $len, $autoC): int {
            if (! $autoC) {
                return 0;
            }
            $n = 0;
            while ($i + $n < $len && ctype_digit($text[$i + $n])) {
                $n++;
            }

            return $n;
        };

        // เริ่มชุด C ถ้าขึ้นต้นด้วยตัวเลข ≥ 4 ตัว (หรือเป็นตัวเลขล้วนความยาวคู่)
        $run = $digitsAt(0);
        $set = ($run >= 4 && $run % 2 === 0) || ($run === $len && $len % 2 === 0) ? 'C' : 'B';
        $codes = [$set === 'C' ? self::START_C : self::START_B];
        $i = 0;
        while ($i < $len) {
            $run = $digitsAt($i);
            if ($set === 'B' && $run >= 4) {
                // ตัวเลขเป็นเลขคี่: ส่งตัวแรกในชุด B ก่อน แล้วค่อยสลับ
                if ($run % 2 === 1) {
                    $codes[] = ord($text[$i]) - 32;
                    $i++;
                    $run--;
                }
                if ($run >= 4) {
                    $codes[] = self::CODE_C;
                    $set = 'C';
                }
            }
            if ($set === 'C') {
                if ($run >= 2) {
                    $codes[] = (int) substr($text, $i, 2);
                    $i += 2;

                    continue;
                }
                $codes[] = self::CODE_B;
                $set = 'B';
            }
            $codes[] = ord($text[$i]) - 32;
            $i++;
        }

        $sum = $codes[0];
        foreach (array_slice($codes, 1) as $pos => $v) {
            $sum += $v * ($pos + 1);
        }
        $codes[] = $sum % 103;
        $codes[] = self::STOP;

        return $codes;
    }

    /** แท่ง/ช่องว่างเป็นสตริง 1/0 ทีละ module (ไม่รวม quiet zone) */
    public static function modules(string $text, bool $autoC = true): string
    {
        $out = '';
        foreach (self::encode($text, $autoC) as $v) {
            foreach (str_split(self::PATTERNS[$v]) as $k => $w) {
                $out .= str_repeat($k % 2 === 0 ? '1' : '0', (int) $w);
            }
        }

        return $out;
    }

    /**
     * SVG ขยายเต็มกล่องที่วาง (กำหนดความกว้าง/สูงด้วย CSS) · quiet zone 10 module ซ้าย-ขวา
     */
    public static function svg(string $text, string $class = 'barcode'): string
    {
        $bits = self::modules($text);
        $quiet = 10;
        $w = strlen($bits) + $quiet * 2;
        $rects = '';
        $x = 0;
        foreach (preg_split('/(?<=1)(?=0)|(?<=0)(?=1)/', $bits) as $chunk) {
            if ($chunk[0] === '1') {
                $rects .= '<rect x="'.($x + $quiet).'" y="0" width="'.strlen($chunk).'" height="50"/>';
            }
            $x += strlen($chunk);
        }

        return '<svg class="'.e($class).'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$w.' 50" preserveAspectRatio="none" shape-rendering="crispEdges" role="img" aria-label="'.e($text).'"><g fill="#000">'.$rects.'</g></svg>';
    }
}
