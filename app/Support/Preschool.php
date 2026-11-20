<?php

namespace App\Support;

use App\Models\Classroom;

/**
 * ปฐมวัยตามหลักสูตรการศึกษาปฐมวัย พ.ศ. 2560: พัฒนาการ 4 ด้าน 12 มาตรฐาน ประเมิน 3 ระดับ
 * ชื่อมาตรฐานแก้ได้ (เก็บในตั้งค่า preschool_standards) แต่จำนวนและด้านคงที่
 */
class Preschool
{
    public const LEVELS = [3 => 'ดี', 2 => 'พอใช้', 1 => 'ควรส่งเสริม'];

    /** ด้าน => [ชื่อ, เลขมาตรฐานในด้านนั้น, ชื่อย่อ] */
    public const DOMAINS = [
        'body' => ['ด้านร่างกาย', [1, 2], 'กาย'],
        'mind' => ['ด้านอารมณ์ จิตใจ', [3, 4, 5], 'อารมณ์'],
        'social' => ['ด้านสังคม', [6, 7, 8], 'สังคม'],
        'intellect' => ['ด้านสติปัญญา', [9, 10, 11, 12], 'ปัญญา'],
    ];

    public const STANDARDS = [
        1 => 'ร่างกายเจริญเติบโตตามวัยและมีสุขนิสัยที่ดี',
        2 => 'กล้ามเนื้อใหญ่และกล้ามเนื้อเล็กแข็งแรง ใช้ได้อย่างคล่องแคล่วและประสานสัมพันธ์กัน',
        3 => 'มีสุขภาพจิตดีและมีความสุข',
        4 => 'ชื่นชมและแสดงออกทางศิลปะ ดนตรี และการเคลื่อนไหว',
        5 => 'มีคุณธรรม จริยธรรม และมีจิตใจที่ดีงาม',
        6 => 'มีทักษะชีวิตและปฏิบัติตนตามหลักปรัชญาของเศรษฐกิจพอเพียง',
        7 => 'รักธรรมชาติ สิ่งแวดล้อม วัฒนธรรม และความเป็นไทย',
        8 => 'อยู่ร่วมกับผู้อื่นได้อย่างมีความสุขและปฏิบัติตนเป็นสมาชิกที่ดีของสังคมในระบอบประชาธิปไตยอันมีพระมหากษัตริย์ทรงเป็นประมุข',
        9 => 'ใช้ภาษาสื่อสารได้เหมาะสมกับวัย',
        10 => 'มีความสามารถในการคิดที่เป็นพื้นฐานในการเรียนรู้',
        11 => 'มีจินตนาการและความคิดสร้างสรรค์',
        12 => 'มีเจตคติที่ดีต่อการเรียนรู้ และมีความสามารถในการแสวงหาความรู้ได้เหมาะสมกับวัย',
    ];

    /** บันทึกประจำวัน: คอลัมน์ => [ชื่อ, ไอคอน] */
    public const DAILY = [
        'milk' => ['ดื่มนม', 'cup-straw'],
        'meal' => ['ทานอาหารกลางวัน', 'egg-fried'],
        'brush' => ['แปรงฟัน', 'brush'],
        'nap' => ['นอนกลางวัน', 'moon-stars'],
    ];

    /** ชื่อมาตรฐานที่ใช้จริง (ที่โรงเรียนแก้ไว้ ทับค่าตั้งต้น) */
    public static function standards(): array
    {
        $custom = json_decode((string) Settings::get('preschool_standards', ''), true);
        $out = self::STANDARDS;
        foreach (is_array($custom) ? $custom : [] as $no => $name) {
            if (isset($out[(int) $no]) && trim((string) $name) !== '') {
                $out[(int) $no] = trim((string) $name);
            }
        }

        return $out;
    }

    public static function isPreschoolLevel(?string $level): bool
    {
        return $level !== null && str_starts_with($level, 'อ.');
    }

    /** มีห้องอนุบาลในปีการศึกษาปัจจุบันหรือไม่ (ใช้ตัดสินว่าจะขึ้นเมนูปฐมวัย) */
    public static function hasClassrooms(): bool
    {
        return rescue(fn () => Classroom::currentYear()->where('level', 'like', 'อ.%')->exists(), false, false);
    }

    /** สรุปหนึ่งด้าน = ค่าเฉลี่ยของมาตรฐานในด้านนั้น ปัดเป็น 3 ระดับ (null = ประเมินไม่ครบ) */
    public static function summarize(array $levels): ?int
    {
        $vals = array_map(fn ($v) => $v === null || $v === '' ? null : (int) $v, $levels);
        if (! $vals || in_array(null, $vals, true)) {
            return null;
        }
        $avg = array_sum($vals) / count($vals);

        return $avg >= 2.5 ? 3 : ($avg >= 1.5 ? 2 : 1);
    }

    /** ['body' => 3, 'mind' => null, ...] จาก levels ของนักเรียนหนึ่งคน */
    public static function domainSummary(?array $levels): array
    {
        $out = [];
        foreach (self::DOMAINS as $key => [, $nos]) {
            $out[$key] = self::summarize(array_map(fn ($no) => $levels[$no] ?? $levels[(string) $no] ?? null, $nos));
        }

        return $out;
    }

    public static function label(?int $level): string
    {
        return $level === null ? '-' : (self::LEVELS[$level] ?? '-');
    }

    public static function color(?int $level): string
    {
        return [3 => 'success', 2 => 'info', 1 => 'warning'][$level] ?? 'secondary';
    }
}
