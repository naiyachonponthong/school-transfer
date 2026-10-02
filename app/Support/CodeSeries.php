<?php

namespace App\Support;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * ออกเลข/รหัสอัตโนมัติตามรูปแบบที่ตั้งได้ เช่น {CAT}-{FY}-{SEQ} → 7440-2570-0001
 * เลขลำดับนับต่อจากเลขสูงสุดที่มีอยู่แล้วใน "ชุด" เดียวกัน (หมวด/ปีเดียวกัน) · ใช้ร่วมกันระหว่างครุภัณฑ์และวัสดุ
 */
abstract class CodeSeries
{
    /** โมเดล + คอลัมน์ที่เก็บเลข */
    protected const MODEL = '';

    protected const COLUMN = 'code';

    protected const PATTERN_KEY = '';

    protected const CODES_KEY = '';

    public const DEFAULT_PATTERN = '{CAT}-{SEQ}';

    public const TOKENS = [];

    public const PRESETS = [];

    public const DEFAULT_CODES = [];

    /** หมวดที่ใช้รหัสแทนหมวดที่ไม่ได้ตั้งรหัสไว้ */
    protected const OTHER = '';

    protected const FALLBACK = '9999';

    private const SEQ_REGEX = '/\{SEQ([3-9])?\}/';

    public static function pattern(): string
    {
        return Settings::get(static::PATTERN_KEY) ?: static::DEFAULT_PATTERN;
    }

    /** รหัสประเภททั้งหมด (ค่าเริ่มต้น + ที่ตั้งเอง) */
    public static function codes(): array
    {
        $stored = json_decode((string) Settings::get(static::CODES_KEY, ''), true);

        return array_merge(static::DEFAULT_CODES, is_array($stored) ? $stored : []);
    }

    public static function codeFor(?string $category): string
    {
        $codes = static::codes();

        return $codes[$category] ?? $codes[static::OTHER] ?? static::FALLBACK;
    }

    /** หมวดทั้งหมดที่ตั้งรหัสได้ */
    public static function categories(): array
    {
        return array_keys(static::DEFAULT_CODES);
    }

    /** บันทึกรูปแบบ + รหัสหมวด (คืนค่าเดิม/ใหม่ของที่เปลี่ยน) */
    public static function save(string $pattern, array $codes): array
    {
        $old = [static::PATTERN_KEY => static::pattern()];
        $new = [static::PATTERN_KEY => $pattern];
        if (static::CODES_KEY !== '') {
            $old[static::CODES_KEY] = json_encode(static::codes(), JSON_UNESCAPED_UNICODE);
            $new[static::CODES_KEY] = json_encode(array_merge(static::codes(), $codes), JSON_UNESCAPED_UNICODE);
        }
        Settings::set($new);

        return collect($new)->filter(fn ($v, $k) => $old[$k] !== $v)->map(fn ($v, $k) => [$old[$k], $v])->all();
    }

    /** ข้อความผิดพลาดของรูปแบบ (null = ใช้ได้) */
    public static function problem(string $pattern): ?string
    {
        $seq = preg_match_all(self::SEQ_REGEX, $pattern);
        if ($seq !== 1) {
            return 'รูปแบบต้องมีเลขลำดับ {SEQ} หนึ่งตำแหน่ง';
        }
        $unknown = collect(preg_match_all('/\{[^}]*\}/', $pattern, $m) ? $m[0] : [])
            ->reject(fn ($t) => isset(static::TOKENS[$t]) || preg_match(self::SEQ_REGEX, $t))->unique();
        if ($unknown->isNotEmpty()) {
            return 'ไม่รู้จัก '.$unknown->implode(' ').' — ใช้ได้เฉพาะ '.implode(' ', array_keys(static::TOKENS));
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
    protected static function series(string $pattern, ?string $category, ?Carbon $date): array
    {
        if ($problem = static::problem($pattern)) {
            throw new InvalidArgumentException($problem);
        }
        $date ??= today();
        $fy = static::fiscalYear($date);
        $filled = strtr($pattern, [
            '{CAT}' => static::codeFor($category),
            '{FY}' => (string) $fy,
            '{FY2}' => substr((string) $fy, -2),
            '{YEAR}' => (string) ($date->year + 543),
        ]);
        preg_match(self::SEQ_REGEX, $filled, $m, PREG_OFFSET_CAPTURE);

        return [substr($filled, 0, $m[0][1]), substr($filled, $m[0][1] + strlen($m[0][0])), (int) (($m[1][0] ?? '') ?: 4)];
    }

    /** เลขลำดับสูงสุดที่ใช้ไปแล้วในชุดนี้ */
    protected static function lastSequence(string $prefix, string $suffix, int $digits = 1): int
    {
        $like = fn ($s) => str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
        // นับเฉพาะเลขที่มีจำนวนหลักตามรูปแบบขึ้นไป (B00001 แบบเก่าไม่ปนกับชุด B00000001)
        $regex = '/^'.preg_quote($prefix, '/').'(\d{'.$digits.',})'.preg_quote($suffix, '/').'$/u';

        return (int) (static::MODEL)::where(static::COLUMN, 'like', $like($prefix).'%'.$like($suffix))->pluck(static::COLUMN)
            ->map(fn ($c) => preg_match($regex, $c, $m) ? (int) $m[1] : 0)->max();
    }

    /**
     * เลขถัดไป (ยังไม่จอง) — ใช้แสดงตัวอย่าง · ระบุ $count เพื่อขอหลายเลขเรียงกัน
     *
     * @return list<string>
     */
    public static function next(?string $category = null, ?Carbon $date = null, int $count = 1, ?string $pattern = null): array
    {
        [$prefix, $suffix, $digits] = static::series($pattern ?? static::pattern(), $category, $date);
        $last = static::lastSequence($prefix, $suffix, $digits);

        return array_map(fn ($i) => $prefix.str_pad((string) ($last + $i), $digits, '0', STR_PAD_LEFT).$suffix, range(1, max(1, $count)));
    }

    /**
     * ออกเลขแล้วบันทึกทันที (ใน transaction เดียวกับการเพิ่มรายการ) กันสองคนได้เลขซ้ำ
     *
     * @param  callable(string): mixed  $create  รับเลขแล้วสร้างรายการ
     */
    public static function assign(?string $category, ?Carbon $date, callable $create, int $count = 1): array
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn () => array_map($create, static::next($category, $date, $count)));
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 3) {
                    throw $e;
                }
            }
        }
    }
}
