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

    public static function fromPercent(float $percent): string
    {
        foreach (self::SCALE as $min => $grade) {
            if ($percent >= $min) {
                return $grade;
            }
        }

        return '0';
    }

    public static function color(?string $grade): string
    {
        return match (true) {
            $grade === null => 'secondary',
            (float) $grade >= 3.5 => 'success',
            (float) $grade >= 2.5 => 'info',
            (float) $grade >= 1 => 'warning',
            default => 'danger',
        };
    }

    /**
     * @param  iterable<array{grade: string|null, credit: float}>  $rows
     */
    public static function gpa(iterable $rows): ?float
    {
        $points = 0.0;
        $credits = 0.0;
        foreach ($rows as $row) {
            if ($row['grade'] === null || ! is_numeric($row['grade']) || $row['credit'] <= 0) {
                continue;
            }
            $points += (float) $row['grade'] * $row['credit'];
            $credits += $row['credit'];
        }

        return $credits > 0 ? round($points / $credits, 2) : null;
    }
}
