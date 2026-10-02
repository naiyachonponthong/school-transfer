<?php

namespace App\Support;

/**
 * ระดับการศึกษาตามหลักสูตรแกนกลางการศึกษาขั้นพื้นฐาน พ.ศ. 2551
 * ปพ.1 มี 3 แบบ: ป (ประถมศึกษา) · บ (มัธยมศึกษาตอนต้น) · พ (มัธยมศึกษาตอนปลาย)
 * เกณฑ์หน่วยกิตขั้นต่ำของการจบมัธยม: ม.ต้น พื้นฐาน 66 + เพิ่มเติม ≥ 11 รวม ≥ 77 · ม.ปลาย พื้นฐาน 41 + เพิ่มเติม ≥ 36 รวม ≥ 77
 */
class Curriculum
{
    public const NAME = 'หลักสูตรแกนกลางการศึกษาขั้นพื้นฐาน พุทธศักราช 2551';

    public const STAGES = [
        'p' => ['name' => 'ประถมศึกษา', 'code' => 'ปพ.1 : ป', 'levels' => ['ป.1', 'ป.2', 'ป.3', 'ป.4', 'ป.5', 'ป.6'], 'final' => 'ป.6', 'credits' => false,
            'min' => null, 'graduate' => 'จบการศึกษาระดับประถมศึกษา'],
        'b' => ['name' => 'มัธยมศึกษาตอนต้น', 'code' => 'ปพ.1 : บ', 'levels' => ['ม.1', 'ม.2', 'ม.3'], 'final' => 'ม.3', 'credits' => true,
            'min' => ['basic' => 66, 'extra' => 11, 'total' => 77], 'graduate' => 'จบการศึกษาภาคบังคับ'],
        'w' => ['name' => 'มัธยมศึกษาตอนปลาย', 'code' => 'ปพ.1 : พ', 'levels' => ['ม.4', 'ม.5', 'ม.6'], 'final' => 'ม.6', 'credits' => true,
            'min' => ['basic' => 41, 'extra' => 36, 'total' => 77], 'graduate' => 'จบการศึกษาขั้นพื้นฐาน'],
    ];

    public static function stageOf(?string $level): ?string
    {
        foreach (self::STAGES as $key => $s) {
            if (in_array($level, $s['levels'], true)) {
                return $key;
            }
        }

        return null;
    }

    /** ชั้นสุดท้ายของแต่ละระดับ → ระดับ (ใช้กับ ปพ.3) */
    public static function stageOfFinal(string $level): ?string
    {
        foreach (self::STAGES as $key => $s) {
            if ($s['final'] === $level) {
                return $key;
            }
        }

        return null;
    }
}
