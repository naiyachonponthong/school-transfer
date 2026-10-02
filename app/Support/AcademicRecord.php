<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseResult;
use App\Models\Score;
use App\Models\Student;
use App\Models\StudentEvaluation;
use App\Models\Subject;
use Illuminate\Support\Collection;

/**
 * ผลการเรียนตลอดระดับการศึกษาหนึ่งของนักเรียน (ข้อมูลของ ปพ.1 / ปพ.3)
 * รวมรายวิชาที่มีคะแนนหรือผลพิเศษ ในห้องเรียนที่อยู่ในระดับนั้น
 */
class AcademicRecord
{
    /** @var Collection<int, array{course: Course, grade: ?string, credit: float, hours: int, weight: float}> */
    public Collection $rows;

    public function __construct(public Student $student, public string $stage)
    {
        $levels = Curriculum::STAGES[$stage]['levels'];
        $courseIds = Score::where('student_id', $student->id)
            ->join('assessments', 'assessments.id', '=', 'scores.assessment_id')->distinct()->pluck('assessments.course_id')
            ->merge(CourseResult::where('student_id', $student->id)->pluck('course_id'))->unique();

        $byCredit = Curriculum::STAGES[$stage]['credits'];
        $this->rows = Course::with(['subject', 'term', 'assessments', 'classroom'])->whereIn('id', $courseIds)
            ->whereHas('classroom', fn ($q) => $q->whereIn('level', $levels))->get()
            ->sortBy(fn ($c) => [$c->term->year, $c->term->term, $c->subject->typeOrder(), $c->subject->code])
            ->map(fn (Course $c) => [
                'course' => $c,
                'grade' => $c->results()[$student->id]['grade'] ?? null,
                'credit' => (float) $c->subject->credit,
                'hours' => (int) $c->subject->hours,
                // น้ำหนักในการคิดผลการเรียนเฉลี่ย: มัธยม = หน่วยกิต · ประถม = เวลาเรียน ÷ 40
                'weight' => $byCredit ? (float) $c->subject->credit : (int) $c->subject->hours / 40,
            ])->values();
    }

    /** ระดับที่ใช้ทำ ปพ.1 ของนักเรียน: ตามห้องปัจจุบัน หรือระดับล่าสุดที่มีผลการเรียน */
    public static function defaultStage(Student $student): string
    {
        return Curriculum::stageOf($student->classroom?->level) ?? collect(array_keys(Curriculum::STAGES))
            ->last(fn ($s) => (new self($student, $s))->rows->isNotEmpty()) ?? 'b';
    }

    public function academic(): Collection
    {
        return $this->rows->reject(fn ($r) => $r['course']->isActivity())->values();
    }

    public function activities(): Collection
    {
        return $this->rows->filter(fn ($r) => $r['course']->isActivity())->values();
    }

    /**
     * ผลการเรียนแยกตามช่วงเวลา: มัธยม = รายภาคเรียน · ประถม = รายปี (ปพ.1:ป แสดงผลรายปี)
     *
     * @return Collection<string, array{label: string, rows: Collection}>
     */
    public function periods(): Collection
    {
        $primary = $this->stage === 'p';

        return $this->rows->groupBy(fn ($r) => $primary ? (string) $r['course']->term->year : (string) $r['course']->term_id)
            ->map(function ($rows) use ($primary) {
                $c = $rows->first()['course'];
                $label = $primary
                    ? "ปีการศึกษา {$c->term->year} ชั้น {$c->classroom->level}"
                    : "ปีการศึกษา {$c->term->year} ภาคเรียนที่ {$c->term->term} ({$c->classroom->level})";

                return ['label' => $label, 'rows' => $rows->values()];
            });
    }

    public function gpax(): ?float
    {
        return self::gpa($this->academic());
    }

    private static function gpa(Collection $rows): ?float
    {
        return Grade::gpa($rows->map(fn ($r) => ['grade' => $r['grade'], 'credit' => $r['weight']]));
    }

    /** หน่วยกิต (มัธยม) หรือชั่วโมง (ประถม) แยกพื้นฐาน/เพิ่มเติม: ลงทะเบียน (taken) และที่ได้ (earned) */
    public function totals(): array
    {
        $unit = Curriculum::STAGES[$this->stage]['credits'] ? 'credit' : 'hours';
        $out = [];
        foreach (['basic', 'extra'] as $type) {
            $rows = $this->academic()->filter(fn ($r) => $r['course']->subject->type === $type);
            $out[$type] = ['taken' => $rows->sum($unit), 'earned' => $rows->filter(fn ($r) => Grade::passed($r['grade']))->sum($unit)];
        }
        $out['total'] = ['taken' => $out['basic']['taken'] + $out['extra']['taken'], 'earned' => $out['basic']['earned'] + $out['extra']['earned']];

        return $out;
    }

    /** ผลการเรียนเฉลี่ยรายกลุ่มสาระการเรียนรู้ */
    public function byGroup(): Collection
    {
        $unit = Curriculum::STAGES[$this->stage]['credits'] ? 'credit' : 'hours';

        return collect(Subject::GROUPS)->reject(fn ($g) => $g === 'กิจกรรมพัฒนาผู้เรียน')
            ->mapWithKeys(function ($group) use ($unit) {
                $rows = $this->academic()->filter(fn ($r) => $r['course']->subject->group === $group);

                return [$group => ['amount' => $rows->sum($unit), 'gpa' => self::gpa($rows)]];
            });
    }

    /** กิจกรรมพัฒนาผู้เรียนแยกประเภท: ชั่วโมงรวม + ผล (ผ เมื่อผ่านทุกรายการ) */
    public function activitySummary(): Collection
    {
        return collect(Subject::ACTIVITY_KINDS)->map(function ($label, $kind) {
            $rows = $this->activities()->filter(fn ($r) => $r['course']->subject->activity_kind === $kind);

            return [
                'label' => $label,
                'hours' => $rows->sum('hours'),
                'result' => $rows->isEmpty() ? null : ($rows->every(fn ($r) => $r['grade'] === 'ผ') ? 'ผ' : 'มผ'),
            ];
        });
    }

    /** ผลประเมินล่าสุดในระดับนี้ (คุณลักษณะ / อ่านคิดเขียน) */
    public function latestEvaluation(): ?StudentEvaluation
    {
        $termIds = $this->rows->pluck('course.term_id')->unique();

        return StudentEvaluation::with('term')->where('student_id', $this->student->id)->whereIn('term_id', $termIds)->get()
            ->sortBy(fn ($e) => [$e->term->year, $e->term->term])->last();
    }

    /** รายวิชาที่ยังไม่ผ่าน (0 ร มส มผ หรือยังไม่มีผล) */
    public function pending(): Collection
    {
        return $this->rows->reject(fn ($r) => Grade::passed($r['grade']))->values();
    }

    /**
     * ตรวจเกณฑ์การจบตามหลักสูตร
     *
     * @return list<array{label: string, ok: bool}>
     */
    public function graduationChecks(): array
    {
        $stage = Curriculum::STAGES[$this->stage];
        $pending = $this->pending();
        $list = $pending->take(3)->map(fn ($r) => $r['course']->subject->code.' ('.($r['grade'] ?? 'ไม่มีผล').')')->implode(', ');
        $checks = [[
            'label' => $pending->isEmpty() ? 'ผ่านทุกรายวิชา/กิจกรรม' : 'ยังไม่ผ่าน '.($pending->count() > 3 ? $pending->count().' วิชา เช่น '.$list : $list),
            'ok' => $this->rows->isNotEmpty() && $pending->isEmpty(),
        ]];
        if ($stage['min']) {
            $t = $this->totals();
            foreach (['basic' => 'พื้นฐาน', 'extra' => 'เพิ่มเติม', 'total' => 'รวม'] as $k => $label) {
                $checks[] = ['label' => "หน่วยกิต{$label} {$t[$k]['earned']}/{$stage['min'][$k]}", 'ok' => $t[$k]['earned'] >= $stage['min'][$k]];
            }
        }
        $ev = $this->latestEvaluation();
        $traits = $ev?->traitsSummary();
        $checks[] = ['label' => 'อ่าน คิดวิเคราะห์ และเขียน: '.Evaluation::label($ev?->rtw), 'ok' => ($ev?->rtw ?? 0) >= 1];
        $checks[] = ['label' => 'คุณลักษณะอันพึงประสงค์: '.Evaluation::label($traits), 'ok' => ($traits ?? 0) >= 1];

        return $checks;
    }

    public function eligible(): bool
    {
        return collect($this->graduationChecks())->every('ok');
    }
}
