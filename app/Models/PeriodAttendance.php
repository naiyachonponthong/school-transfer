<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** เช็คชื่อรายคาบ ใช้สถานะชุดเดียวกับเช็คชื่อรายวัน (Attendance::STATUSES) */
class PeriodAttendance extends Model
{
    /** เวลาเรียนขั้นต่ำที่มีสิทธิ์สอบ (ร้อยละ) ตามระเบียบการวัดผล — ต่ำกว่านี้ = มส. */
    public const MIN_PERCENT = 80;

    protected $fillable = ['course_id', 'student_id', 'date', 'period', 'status', 'note', 'recorded_by'];

    protected function casts(): array
    {
        return ['date' => DateOnly::class, 'period' => 'integer'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * สรุปเวลาเรียนรายวิชาของนักเรียนทุกคนในวิชานี้
     * นับ "ลา/ป่วย" เป็นขาดเรียนในการคิดร้อยละเวลาเรียน (ตามแนวปฏิบัติทั่วไป: มาเรียน = มา + สาย)
     *
     * @return array<int, array{total:int, came:int, absent:int, leave:int, late:int, percent:?float, ms:bool}>
     */
    public static function summaryFor(Course $course): array
    {
        $rows = self::where('course_id', $course->id)->get()->groupBy('student_id');
        $out = [];
        foreach ($rows as $studentId => $list) {
            $counts = $list->countBy('status');
            $total = $list->count();
            $came = ($counts['present'] ?? 0) + ($counts['late'] ?? 0);
            $percent = $total ? round($came / $total * 100, 1) : null;
            $out[$studentId] = [
                'total' => $total,
                'came' => $came,
                'late' => $counts['late'] ?? 0,
                'absent' => $counts['absent'] ?? 0,
                'leave' => ($counts['leave'] ?? 0) + ($counts['sick'] ?? 0),
                'percent' => $percent,
                'ms' => $percent !== null && $percent < self::MIN_PERCENT,
            ];
        }

        return $out;
    }
}
