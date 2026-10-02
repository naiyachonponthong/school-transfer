<?php

namespace App\Support;

use App\Models\BookCopy;

/** เลขทะเบียนหนังสือ (ลงทะเบียนทุกเล่มที่รับเข้าห้องสมุด เรียงต่อเนื่อง) */
class AccessionNumber extends CodeSeries
{
    protected const MODEL = BookCopy::class;

    protected const COLUMN = 'accession_no';

    protected const PATTERN_KEY = 'library_accession_pattern';

    public const DEFAULT_PATTERN = '{SEQ5}';

    public const TOKENS = BookBarcode::TOKENS;

    public const PRESETS = [
        '{SEQ5}' => 'เลขเรียงต่อเนื่อง 5 หลัก (00001)',
        '{SEQ4}/{YEAR}' => 'เลขลำดับ/ปีที่รับเข้า นับใหม่ทุกปี (0001/2569)',
        '{YEAR}-{SEQ4}' => 'ปี-เลขลำดับ (2569-0001)',
    ];

    public static function categories(): array
    {
        return [];
    }
}
