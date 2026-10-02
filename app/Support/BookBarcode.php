<?php

namespace App\Support;

use App\Models\BookCopy;

/** บาร์โค้ดตัวเล่ม (ติดปกนอก/ปกใน ใช้ยืม-คืน) เช่น B{SEQ8} → B00000001 */
class BookBarcode extends CodeSeries
{
    protected const MODEL = BookCopy::class;

    protected const COLUMN = 'barcode';

    protected const PATTERN_KEY = 'library_barcode_pattern';

    public const DEFAULT_PATTERN = 'B{SEQ8}';

    public const TOKENS = [
        '{YEAR}' => 'ปี พ.ศ. ที่ลงทะเบียน',
        '{FY}' => 'ปีงบประมาณ พ.ศ.',
        '{FY2}' => 'ปีงบประมาณ 2 หลักท้าย',
        '{SEQ}' => 'เลขลำดับ 4 หลัก (ใช้ {SEQ5}–{SEQ9} เปลี่ยนจำนวนหลัก)',
    ];

    public const PRESETS = [
        'B{SEQ8}' => 'อักษร B + เลข 8 หลัก (B00000001)',
        '{SEQ9}' => 'ตัวเลขล้วน 9 หลัก',
        'LIB{SEQ6}' => 'อักษรย่อห้องสมุด + เลข 6 หลัก',
    ];

    public static function categories(): array
    {
        return [];
    }
}
