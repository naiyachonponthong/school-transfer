<?php

namespace App\Support;

use App\Models\Asset;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * ออกเลขครุภัณฑ์อัตโนมัติตามรูปแบบที่งานพัสดุตั้งไว้ เช่น {CAT}-{FY}-{SEQ} → 7440-2570-0001
 * เลขลำดับนับต่อจากเลขสูงสุดที่มีอยู่แล้วใน "ชุด" เดียวกัน (ประเภท/ปีเดียวกัน) จึงนับใหม่ทุกปีงบได้เอง
 */
class AssetNumber
{
    public const DEFAULT_PATTERN = '{CAT}-{FY}-{SEQ}';

    /** ตัวแปรที่ใช้ในรูปแบบได้ → คำอธิบาย */
    public const TOKENS = [
        '{CAT}' => 'รหัสประเภทครุภัณฑ์ (ตั้งได้ด้านล่าง)',
        '{FY}' => 'ปีงบประมาณ พ.ศ. ที่ได้มา (ต.ค.–ก.ย.)',
        '{FY2}' => 'ปีงบประมาณ 2 หลักท้าย',
        '{YEAR}' => 'ปี พ.ศ. ที่ได้มา (ม.ค.–ธ.ค.)',
        '{SEQ}' => 'เลขลำดับ 4 หลัก (ใช้ {SEQ3} {SEQ5} {SEQ6} เปลี่ยนจำนวนหลัก)',
    ];

    /** รูปแบบสำเร็จรูปให้เลือก */
    public const PRESETS = [
        '{CAT}-{FY}-{SEQ}' => 'แยกตามประเภท นับใหม่ทุกปีงบประมาณ',
        '{CAT}-{SEQ}/{FY}' => 'ปีงบประมาณไว้ท้าย',
        'สธ.{CAT}-{FY}-{SEQ}' => 'มีอักษรย่อโรงเรียนนำหน้า (แก้ "สธ." เป็นของโรงเรียน)',
        '{FY}-{SEQ5}' => 'ไม่แยกประเภท เรียงทั้งโรงเรียน นับใหม่ทุกปีงบ',
    ];

    /**
     * รหัสประเภทเริ่มต้น (ตัวอย่างตามหมวดรหัสพัสดุ 4 หลัก) — ควรแก้ให้ตรงกับทะเบียนเดิมของโรงเรียน
     */
    public const DEFAULT_CODES = [
        'ครุภัณฑ์คอมพิวเตอร์' => '7440',
        'ครุภัณฑ์สำนักงาน' => '7110',
        'ครุภัณฑ์การศึกษา' => '6910',
        'ครุภัณฑ์ไฟฟ้าและวิทยุ' => '5820',
        'ครุภัณฑ์โฆษณาและเผยแพร่' => '6730',
        'ครุภัณฑ์วิทยาศาสตร์' => '6640',
        'ครุภัณฑ์กีฬา' => '7810',
        'ครุภัณฑ์ดนตรีและนาฏศิลป์' => '7710',
        'ครุภัณฑ์งานบ้านงานครัว' => '7310',
        'ครุภัณฑ์การเกษตร' => '3750',
        'ครุภัณฑ์ยานพาหนะและขนส่ง' => '2310',
        'ครุภัณฑ์อื่น ๆ' => '9999',
    ];

    private const OTHER = 'ครุภัณฑ์อื่น ๆ';

    private const SEQ_REGEX = '/\{SEQ([3-6])?\}/';

    public static function pattern(): string
    {
        return Settings::get('asset_no_pattern') ?: self::DEFAULT_PATTERN;
    }

    /** รหัสประเภททั้งหมด (ค่าเริ่มต้น + ที่ตั้งเอง) */
    public static function codes(): array
    {
        $stored = json_decode((string) Settings::get('asset_category_codes', ''), true);

        return array_merge(self::DEFAULT_CODES, is_array($stored) ? $stored : []);
    }

    public static function codeFor(?string $category): string
    {
        $codes = self::codes();

        return $codes[$category] ?? $codes[self::OTHER] ?? '9999';
    }

    /** ข้อความผิดพลาดของรูปแบบ (null = ใช้ได้) */
    public static function problem(string $pattern): ?string
    {
        $seq = preg_match_all(self::SEQ_REGEX, $pattern);
        if ($seq !== 1) {
            return 'รูปแบบต้องมีเลขลำดับ {SEQ} หนึ่งตำแหน่ง';
        }
        $unknown = collect(preg_match_all('/\{[^}]*\}/', $pattern, $m) ? $m[0] : [])
            ->reject(fn ($t) => isset(self::TOKENS[$t]) || preg_match(self::SEQ_REGEX, $t))->unique();
        if ($unknown->isNotEmpty()) {
            return 'ไม่รู้จัก '.$unknown->implode(' ').' — ใช้ได้เฉพาะ '.implode(' ', array_keys(self::TOKENS));
        }

        return null;
    }

    /** ปีงบประมาณ (พ.ศ.) ของวันที่ — เริ่ม 1 ตุลาคม */
    public static function fiscalYear(Carbon $date): int
    {
        return $date->year + ($date->month >= 10 ? 1 : 0) + 543;
    }

    /**
     * แทนค่าทุกตัวแปรยกเว้นเลขลำดับ → [ส่วนหน้า, ส่วนท้าย, จำนวนหลัก]
     *
     * @return array{0: string, 1: string, 2: int}
     */
    private static function series(string $pattern, ?string $category, ?Carbon $date): array
    {
        if ($problem = self::problem($pattern)) {
            throw new InvalidArgumentException($problem);
        }
        $date ??= today();
        $fy = self::fiscalYear($date);
        $filled = strtr($pattern, [
            '{CAT}' => self::codeFor($category),
            '{FY}' => (string) $fy,
            '{FY2}' => substr((string) $fy, -2),
            '{YEAR}' => (string) ($date->year + 543),
        ]);
        preg_match(self::SEQ_REGEX, $filled, $m, PREG_OFFSET_CAPTURE);

        return [substr($filled, 0, $m[0][1]), substr($filled, $m[0][1] + strlen($m[0][0])), (int) (($m[1][0] ?? '') ?: 4)];
    }

    /** เลขลำดับสูงสุดที่ใช้ไปแล้วในชุดนี้ */
    private static function lastSequence(string $prefix, string $suffix): int
    {
        $like = fn ($s) => str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
        $regex = '/^'.preg_quote($prefix, '/').'(\d+)'.preg_quote($suffix, '/').'$/u';

        return (int) Asset::where('code', 'like', $like($prefix).'%'.$like($suffix))->pluck('code')
            ->map(fn ($c) => preg_match($regex, $c, $m) ? (int) $m[1] : 0)->max();
    }

    /**
     * เลขถัดไป (ยังไม่จอง) — ใช้แสดงตัวอย่าง · ระบุ $count เพื่อขอหลายเลขเรียงกัน
     *
     * @return list<string>
     */
    public static function next(?string $category = null, ?Carbon $date = null, int $count = 1, ?string $pattern = null): array
    {
        [$prefix, $suffix, $digits] = self::series($pattern ?? self::pattern(), $category, $date);
        $last = self::lastSequence($prefix, $suffix);

        return array_map(fn ($i) => $prefix.str_pad((string) ($last + $i), $digits, '0', STR_PAD_LEFT).$suffix, range(1, max(1, $count)));
    }

    /**
     * ออกเลขแล้วบันทึกทันที (ใน transaction เดียวกับการเพิ่มครุภัณฑ์) กันสองคนได้เลขซ้ำ
     *
     * @param  callable(string): mixed  $create  รับเลขแล้วสร้างรายการ
     */
    public static function assign(?string $category, ?Carbon $date, callable $create, int $count = 1): array
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn () => array_map($create, self::next($category, $date, $count)));
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 3) {
                    throw $e;
                }
            }
        }
    }
}
