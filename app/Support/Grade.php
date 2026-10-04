<?php

namespace App\Support;

class Grade
{
    /** เกณฑ์ตัดเกรด 8 ระดับ ตามหลักสูตรแกนกลางฯ */
    public const SCALE = [
        80 => '4',
        75 => '3.5',
        70 => '3',
        65 => '2.5',
        60 => '2',
        55 => '1.5',
        50 => '1',
        0 => '0',
    ];

    /** ผลการเรียนพิเศษของรายวิชาทั่วไป (ครู/ฝ่ายวัดผลกำหนดเอง ไม่ได้มาจากคะแนน) */
    public const SPECIAL = [
        'ร' => 'รอการตัดสิน',
        'มส' => 'ไม่มีสิทธิ์สอบ',
    ];

    /** ผลการประเมินกิจกรรมพัฒนาผู้เรียน */
    public const ACTIVITY = [
        'ผ' => 'ผ่าน',
        'มผ' => 'ไม่ผ่าน',
    ];

    /** กิจกรรมพัฒนาผู้เรียน: ได้คะแนนประเมินตั้งแต่ร้อยละนี้ = ผ */
    public const ACTIVITY_PASS_PERCENT = 50;

    /**
     * เกณฑ์ที่ใช้จริง: [คะแนนขั้นต่ำ (%) => เกรด] เรียงจากสูงไปต่ำ
     * ตั้งค่า `grade_scale` เก็บคะแนนขั้นต่ำของเกรด 4, 3.5, 3, 2.5, 2, 1.5, 1 คั่นด้วยจุลภาค (ไม่ได้ตั้ง = เกณฑ์มาตรฐาน)
     *
     * @return array<int, string>
     */
    /** $frozen = เกณฑ์ที่เก็บไว้กับรายวิชาตอนอนุมัติผล (null = ใช้ค่าตั้งปัจจุบัน) */
    public static function scale(?string $frozen = null): array
    {
        $mins = self::parseScale((string) ($frozen ?? Settings::get('grade_scale')));
        if ($mins === null) {
            return self::SCALE;
        }

        return array_combine($mins, array_values(array_diff(self::SCALE, ['0']))) + [0 => '0'];
    }

    /** @return list<int>|null null = รูปแบบไม่ถูกต้อง (ต้องเป็นจำนวนเต็ม 7 ค่า ลดหลั่นลง อยู่ในช่วง 1–100) */
    public static function parseScale(string $value): ?array
    {
        $parts = array_map('trim', explode(',', $value));
        if (count($parts) !== 7) {
            return null;
        }
        $prev = 101;
        $out = [];
        foreach ($parts as $part) {
            if (! ctype_digit($part) || (int) $part >= $prev || (int) $part < 1) {
                return null;
            }
            $prev = (int) $part;
            $out[] = (int) $part;
        }

        return $out;
    }

    /** เกณฑ์ปัจจุบันในรูปแบบข้อความ "80,75,70,65,60,55,50" (ใช้เก็บกับรายวิชาตอนอนุมัติผล) */
    public static function scaleString(): string
    {
        return implode(',', array_keys(array_diff(self::scale(), ['0'])));
    }

    public static function fromPercent(float $percent, ?string $frozen = null): string
    {
        foreach (self::scale($frozen) as $min => $grade) {
            if ($percent >= $min) {
                return $grade;
            }
        }

        return '0';
    }

    public static function activityFromPercent(float $percent): string
    {
        return $percent >= self::ACTIVITY_PASS_PERCENT ? 'ผ' : 'มผ';
    }

    /** ผลที่ครูกำหนดเองได้ตามประเภทวิชา */
    public static function specialOptions(bool $activity): array
    {
        return $activity ? ['มผ' => self::ACTIVITY['มผ']] : self::SPECIAL;
    }

    /**
     * ผลที่บันทึกเป็น "ผลการแก้ตัว" ได้ ตามผลเดิม
     * - 0 → สอบแก้ตัวได้ไม่เกิน 1
     * - มส → เรียนเพิ่มจนเวลาครบแล้วสอบ ได้ไม่เกิน 1
     * - ร → ทำงานที่ค้างครบแล้วได้ผลตามจริง (0–4)
     * - มผ → ทำกิจกรรมซ่อมจนผ่าน = ผ
     *
     * @return list<string>
     */
    public static function remedialOptions(?string $original): array
    {
        return match ($original) {
            '0' => ['1'],
            'มส' => ['0', '1'],
            'ร' => array_reverse(array_values(self::SCALE)),
            'มผ' => ['ผ'],
            default => [],
        };
    }

    /** ผ่านรายวิชา (ได้หน่วยกิต) */
    public static function passed(?string $grade): bool
    {
        return $grade === 'ผ' || (is_numeric($grade) && (float) $grade >= 1);
    }

    public static function color(?string $grade): string
    {
        return match (true) {
            $grade === null => 'secondary',
            $grade === 'ผ' => 'success',
            ! is_numeric($grade) => 'danger',
            (float) $grade >= 3.5 => 'success',
            (float) $grade >= 2.5 => 'info',
            (float) $grade >= 1 => 'warning',
            default => 'danger',
        };
    }

    /**
     * ผลการเรียนเฉลี่ย: ร และ มส นับเป็นค่าระดับ 0 (หน่วยกิตยังอยู่ในตัวหาร)
     * ผ/มผ ของกิจกรรมไม่นำมาคิด
     *
     * @param  iterable<array{grade: string|null, credit: float}>  $rows
     */
    public static function gpa(iterable $rows): ?float
    {
        $points = 0.0;
        $credits = 0.0;
        foreach ($rows as $row) {
            $grade = $row['grade'];
            if ($grade === null || $row['credit'] <= 0) {
                continue;
            }
            if (isset(self::SPECIAL[$grade])) {
                $grade = '0';
            }
            if (! is_numeric($grade)) {
                continue;
            }
            $points += (float) $grade * $row['credit'];
            $credits += $row['credit'];
        }

        return $credits > 0 ? round($points / $credits, 2) : null;
    }
}
