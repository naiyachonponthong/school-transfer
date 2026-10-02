<?php

namespace App\Support;

use App\Models\Supply;

/** รหัสวัสดุอัตโนมัติ เช่น {CAT}-{SEQ3} → OF-001 (หมวดพิมพ์เองได้ หมวดที่ยังไม่ตั้งรหัสใช้รหัสของ "วัสดุอื่น ๆ") */
class SupplyNumber extends CodeSeries
{
    protected const MODEL = Supply::class;

    protected const PATTERN_KEY = 'supply_no_pattern';

    protected const CODES_KEY = 'supply_category_codes';

    protected const OTHER = 'วัสดุอื่น ๆ';

    protected const FALLBACK = 'OT';

    public const DEFAULT_PATTERN = '{CAT}-{SEQ3}';

    public const TOKENS = [
        '{CAT}' => 'รหัสหมวดวัสดุ (ตั้งได้ด้านล่าง)',
        '{FY}' => 'ปีงบประมาณ พ.ศ. ที่เพิ่มรายการ (ต.ค.–ก.ย.)',
        '{FY2}' => 'ปีงบประมาณ 2 หลักท้าย',
        '{YEAR}' => 'ปี พ.ศ. ที่เพิ่มรายการ',
        '{SEQ}' => 'เลขลำดับ 4 หลัก (ใช้ {SEQ3} {SEQ5} {SEQ6} เปลี่ยนจำนวนหลัก)',
    ];

    public const PRESETS = [
        '{CAT}-{SEQ3}' => 'แยกตามหมวด เลขลำดับ 3 หลัก',
        '{CAT}{SEQ}' => 'ไม่มีขีด เลขลำดับ 4 หลัก',
        'M-{SEQ5}' => 'ไม่แยกหมวด เรียงทั้งคลัง',
    ];

    /** รหัสหมวดเริ่มต้น (ตัวย่อภาษาอังกฤษ 2 ตัว) — แก้ได้ */
    public const DEFAULT_CODES = [
        'วัสดุสำนักงาน' => 'OF',
        'วัสดุคอมพิวเตอร์' => 'CP',
        'วัสดุการศึกษา' => 'ED',
        'วัสดุงานบ้านงานครัว' => 'HK',
        'วัสดุไฟฟ้า' => 'EL',
        'วัสดุวิทยาศาสตร์' => 'SC',
        'วัสดุกีฬา' => 'SP',
        'วัสดุก่อสร้าง' => 'CN',
        'วัสดุเชื้อเพลิงและหล่อลื่น' => 'FU',
        'วัสดุอื่น ๆ' => 'OT',
    ];

    /** หมวดที่ใช้อยู่ + หมวดเริ่มต้น (สำหรับหน้าตั้งรหัส) */
    public static function categories(): array
    {
        return collect(array_keys(static::codes()))
            ->merge(Supply::whereNotNull('category')->where('category', '!=', '')->distinct()->pluck('category'))
            ->unique()->values()->all();
    }
}
