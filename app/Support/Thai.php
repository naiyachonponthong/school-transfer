<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class Thai
{
    public const MONTHS = [1 => 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];

    public const MONTHS_SHORT = [1 => 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

    public const DAYS = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];

    public const DAYS_SHORT = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];

    private static function carbon(CarbonInterface|string|null $date): ?CarbonInterface
    {
        if ($date === null || $date === '') {
            return null;
        }

        return $date instanceof CarbonInterface ? $date : Carbon::parse($date);
    }

    /** 24 ก.ย. 2569 */
    public static function date(CarbonInterface|string|null $date, bool $long = false): string
    {
        $d = self::carbon($date);
        if (! $d) {
            return '-';
        }
        $month = $long ? self::MONTHS[$d->month] : self::MONTHS_SHORT[$d->month];

        return $d->day.' '.$month.' '.($d->year + 543);
    }

    /** วันพุธที่ 24 กันยายน 2569 */
    public static function fullDate(CarbonInterface|string|null $date): string
    {
        $d = self::carbon($date);

        return $d ? 'วัน'.self::DAYS[$d->dayOfWeek].'ที่ '.self::date($d, true) : '-';
    }

    public static function dateTime(CarbonInterface|string|null $date): string
    {
        $d = self::carbon($date);

        return $d ? self::date($d).' '.$d->format('H:i').' น.' : '-';
    }

    public static function monthYear(int $month, int $year): string
    {
        return self::MONTHS[$month].' '.($year + 543);
    }

    public static function money(float|int|string|null $amount, int $decimals = 2): string
    {
        return number_format((float) $amount, $decimals);
    }

    /** "3 นาทีที่แล้ว" แบบภาษาไทย */
    public static function ago(CarbonInterface|string|null $date): string
    {
        $d = self::carbon($date);

        return $d ? $d->locale('th')->diffForHumans() : '-';
    }

    /** อ่านจำนวนเงินเป็นตัวอักษรไทย สำหรับใบเสร็จ */
    public static function bahtText(float $amount): string
    {
        $amount = round($amount, 2);
        $baht = (int) floor($amount);
        $satang = (int) round(($amount - $baht) * 100);

        $text = ($baht > 0 ? self::readNumber($baht).'บาท' : ($satang > 0 ? '' : 'ศูนย์บาท'));

        return $text.($satang > 0 ? self::readNumber($satang).'สตางค์' : 'ถ้วน');
    }

    private static function readNumber(int $n): string
    {
        if ($n >= 1000000) {
            return self::readNumber(intdiv($n, 1000000)).'ล้าน'.($n % 1000000 ? self::readNumber($n % 1000000) : '');
        }

        $digits = ['', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];
        $units = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน'];
        $s = strrev((string) $n);
        $out = '';
        for ($i = strlen($s) - 1; $i >= 0; $i--) {
            $d = (int) $s[$i];
            if ($d === 0) {
                continue;
            }
            if ($i === 1 && $d === 1) {
                $out .= 'สิบ';
            } elseif ($i === 1 && $d === 2) {
                $out .= 'ยี่สิบ';
            } elseif ($i === 0 && $d === 1 && strlen($s) > 1) {
                $out .= 'เอ็ด';
            } else {
                $out .= $digits[$d].$units[$i];
            }
        }

        return $out;
    }
}
