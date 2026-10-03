<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\PeriodAttendance;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * สัญญาณเตือนนักเรียนกลุ่มเสี่ยง คำนวณจากข้อมูลที่มีอยู่แล้วในระบบ
 * เป็นเพียงตัวชี้ให้ครูประจำชั้นเข้าไปดู ไม่ได้ตัดสินแทนครู
 */
class RiskScan
{
    public const ABSENT_STREAK = 3;      // ขาดติดกันกี่วัน (จากวันที่มีการเช็คชื่อล่าสุด)

    public const BEHAVIOR_MIN = 70;      // คะแนนความประพฤติต่ำกว่า

    public const SCORE_MIN_PERCENT = 50; // คะแนนที่กรอกแล้วเฉลี่ยต่ำกว่าร้อยละ

    public const WEAK_COURSES = 2;       // ในกี่รายวิชาขึ้นไป

    public const SIGNALS = [
        'absent' => ['ขาดเรียนติดกัน', 'bi-person-x', 'danger'],
        'period' => ['เวลาเรียนรายวิชาต่ำกว่า 80%', 'bi-clock-history', 'warning'],
        'score' => ['คะแนนต่ำหลายวิชา', 'bi-graph-down', 'warning'],
        'behavior' => ['ความประพฤติต่ำกว่าเกณฑ์', 'bi-exclamation-diamond', 'danger'],
    ];

    /**
     * @param  iterable<int>  $classroomIds
     * @return Collection<int, array{student: Student, signals: array<string, string>}> เรียงจากสัญญาณมากไปน้อย
     */
    public static function forClassrooms(iterable $classroomIds): Collection
    {
        $students = Student::active()->with('classroom')->whereIn('classroom_id', collect($classroomIds))->get()->keyBy('id');
        if ($students->isEmpty()) {
            return collect();
        }
        $ids = $students->keys();
        $term = Term::current();
        $signals = [];

        // 1) ขาดติดกัน: ดูการเช็คชื่อล่าสุดของแต่ละคน (30 วัน) นับจากวันล่าสุดย้อนไป
        $recent = Attendance::whereIn('student_id', $ids)->where('date', '>=', today()->subDays(30)->toDateString())
            ->orderByDesc('date')->get(['student_id', 'status'])->groupBy('student_id');
        foreach ($recent as $studentId => $rows) {
            $streak = $rows->takeWhile(fn ($r) => $r->status === 'absent')->count();
            if ($streak >= self::ABSENT_STREAK) {
                $signals[$studentId]['absent'] = "ขาดติดกัน {$streak} วัน";
            }
        }

        // 2) เวลาเรียนรายวิชา (เช็คชื่อรายคาบ) ต่ำกว่า 80% ในภาคเรียนนี้
        if ($term) {
            $rows = PeriodAttendance::whereIn('student_id', $ids)->whereHas('course', fn ($q) => $q->where('term_id', $term->id))
                ->select('student_id', 'course_id',
                    DB::raw("sum(case when status in ('present','late') then 1 else 0 end) as came"), DB::raw('count(*) as total'))
                ->groupBy('student_id', 'course_id')->get();
            foreach ($rows->groupBy('student_id') as $studentId => $courses) {
                $low = $courses->filter(fn ($c) => $c->total > 0 && $c->came / $c->total * 100 < PeriodAttendance::MIN_PERCENT)->count();
                if ($low) {
                    $signals[$studentId]['period'] = "{$low} วิชา";
                }
            }

            // 3) คะแนนที่กรอกแล้วต่ำกว่าครึ่งในหลายวิชา
            $rows = DB::table('scores')
                ->join('assessments', 'assessments.id', '=', 'scores.assessment_id')
                ->join('courses', 'courses.id', '=', 'assessments.course_id')
                ->where('courses.term_id', $term->id)->whereIn('scores.student_id', $ids)->whereNotNull('scores.score')
                ->select('scores.student_id', 'courses.id as course_id', DB::raw('sum(scores.score) as got'), DB::raw('sum(assessments.max_score) as full'))
                ->groupBy('scores.student_id', 'courses.id')->get();
            foreach ($rows->groupBy('student_id') as $studentId => $courses) {
                $weak = $courses->filter(fn ($c) => $c->full > 0 && $c->got / $c->full * 100 < self::SCORE_MIN_PERCENT)->count();
                if ($weak >= self::WEAK_COURSES) {
                    $signals[$studentId]['score'] = "{$weak} วิชา";
                }
            }
        }

        // 4) คะแนนความประพฤติ
        $points = DB::table('behavior_records')->whereIn('student_id', $ids)->select('student_id', DB::raw('sum(points) as p'))->groupBy('student_id')->pluck('p', 'student_id');
        foreach ($points as $studentId => $p) {
            $score = Student::BASE_BEHAVIOR + (int) $p;
            if ($score < self::BEHAVIOR_MIN) {
                $signals[$studentId]['behavior'] = "{$score} คะแนน";
            }
        }

        return collect($signals)
            ->map(fn ($s, $id) => ['student' => $students[$id], 'signals' => $s])
            ->sortByDesc(fn ($r) => count($r['signals']))->values();
    }
}
