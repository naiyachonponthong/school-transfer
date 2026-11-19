<?php

namespace App\Support;

use App\Http\Controllers\ReportController;
use App\Models\Attendance;
use App\Models\BehaviorRecord;
use App\Models\Club;
use App\Models\FeedPost;
use App\Models\HealthMeasurement;
use App\Models\HomeVisit;
use App\Models\Student;
use App\Models\StudentEvaluation;
use App\Models\Term;

/**
 * ระเบียนสะสม (ปพ.8) ของนักเรียน 1 คน รวบรวมจากข้อมูลที่มีในระบบทั้งหมด ไม่ต้องกรอกเพิ่ม
 * แยกตามภาคเรียน: ผลการเรียน เวลามาเรียน ความประพฤติ สุขภาพ คุณลักษณะ/อ่านคิดวิเคราะห์เขียน กิจกรรม เยี่ยมบ้าน
 */
class CumulativeRecord
{
    public static function for(Student $student): array
    {
        $student->loadMissing(['classroom', 'guardians']);
        $academic = ReportController::academicRecord($student);
        $byTerm = $academic['terms']->keyBy(fn ($t) => $t['term']->id);

        $attendance = Attendance::where('student_id', $student->id)->get(['date', 'status']);
        $behavior = BehaviorRecord::where('student_id', $student->id)->orderBy('date')->get(['date', 'points', 'title']);
        $health = HealthMeasurement::where('student_id', $student->id)->orderBy('measured_on')->get();
        $evaluations = StudentEvaluation::where('student_id', $student->id)->get()->keyBy('term_id');
        $visits = HomeVisit::with('visitor:id,name')->where('student_id', $student->id)->orderBy('visited_on')->get();
        $clubs = Club::whereHas('students', fn ($q) => $q->whereKey($student->id))->get(['id', 'name', 'term_id'])->groupBy('term_id');

        // วันที่ > ภาคเรียน: ภาคเรียนล่าสุดที่เริ่มก่อนหรือในวันนั้น (ช่วงปิดภาคเรียนนับรวมกับภาคก่อนหน้า)
        $allTerms = Term::whereNotNull('start_date')->orderBy('start_date')->get();
        $termOf = function ($date) use ($allTerms): ?int {
            if (! $date) {
                return null;
            }
            $d = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : substr((string) $date, 0, 10);

            return $allTerms->last(fn (Term $t) => $t->start_date->toDateString() <= $d)?->id;
        };
        $attByTerm = $attendance->groupBy(fn ($a) => $termOf($a->date));
        $behByTerm = $behavior->groupBy(fn ($b) => $termOf($b->date));
        $healthByTerm = $health->groupBy(fn ($m) => $termOf($m->measured_on));

        // ภาคเรียนที่นักเรียนมีข้อมูลอย่างใดอย่างหนึ่ง
        $terms = Term::orderBy('year')->orderBy('term')->get()->filter(fn (Term $t) => $byTerm->has($t->id)
            || $evaluations->has($t->id) || $clubs->has($t->id) || $attByTerm->has($t->id) || $behByTerm->has($t->id) || $healthByTerm->has($t->id))->values();

        // คะแนนที่ลงไว้ก่อนภาคเรียนแรกที่มีข้อมูล นับรวมเป็นยอดตั้งต้น
        $running = Student::BASE_BEHAVIOR + (int) ($behByTerm[''] ?? collect())->sum('points');
        $rows = $terms->map(function (Term $t) use ($byTerm, $attByTerm, $behByTerm, $healthByTerm, $evaluations, $clubs, $visits, &$running) {
            $att = ($attByTerm[$t->id] ?? collect())->countBy('status');
            $points = (int) ($behByTerm[$t->id] ?? collect())->sum('points');
            $running += $points;
            $h = ($healthByTerm[$t->id] ?? collect())->last();
            $ev = $evaluations[$t->id] ?? null;

            return [
                'term' => $t,
                'gpa' => $byTerm[$t->id]['gpa'] ?? null,
                'credits' => $byTerm[$t->id]['credits'] ?? null,
                'attendance' => ['present' => $att['present'] ?? 0, 'late' => $att['late'] ?? 0, 'absent' => $att['absent'] ?? 0,
                    'leave' => ($att['leave'] ?? 0) + ($att['sick'] ?? 0)],
                'behavior_change' => $points,
                'behavior_total' => $running,
                'weight' => $h?->weight, 'height' => $h?->height,
                'traits' => $ev ? Evaluation::label($ev->traitsSummary()) : null,
                'rtw' => $ev && $ev->rtw !== null ? Evaluation::label($ev->rtw) : null,
                'clubs' => ($clubs[$t->id] ?? collect())->pluck('name')->all(),
                'visits' => $visits->where('term_id', $t->id)->values(),
            ];
        });

        return [
            'student' => $student,
            'rows' => $rows,
            'gpax' => $academic['gpax'],
            'credits' => $academic['credits'],
            'behavior_now' => $student->behaviorScore(),
            'awards' => FeedPost::where('student_id', $student->id)->where('type', 'achievement')->latest()->limit(20)->get(['title', 'headline', 'created_at']),
            'visits' => $visits,
            'latest_health' => $health->last(),
        ];
    }
}
