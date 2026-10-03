<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\PeriodAttendance;
use App\Models\Score;
use App\Models\Student;
use App\Support\Audit;
use App\Support\Grade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GradebookController extends Controller
{
    private function courseLabel(Course $course): string
    {
        $course->loadMissing(['subject', 'classroom', 'term']);

        return "{$course->subject->code} {$course->classroom->name()} ({$course->term->shortLabel()})";
    }

    private function authorizeCourse(Request $request, Course $course, bool $write = false): void
    {
        abort_unless($course->canEdit($request->user()), 403, 'รายวิชานี้ไม่ได้อยู่ในความรับผิดชอบของคุณ');
        if ($write && $course->locked && ! $request->user()->isAdmin()) {
            abort(403, 'รายวิชานี้ถูกล็อกคะแนนแล้ว ติดต่อฝ่ายวิชาการ');
        }
    }

    public function show(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);
        $course->load(['assessments', 'subject', 'classroom', 'teacher', 'term']);
        $students = $course->classroom->roster()->get();

        $scores = Score::whereIn('assessment_id', $course->assessments->pluck('id'))->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->pluck('score', 'assessment_id'));

        $results = $course->results();
        $distribution = collect($results)->pluck('grade')->filter(fn ($g) => $g !== null)->countBy();
        // เวลาเรียนรายวิชา (จากเช็คชื่อรายคาบ) — ครูเห็นคนที่ต้องได้ มส. ขณะกรอกคะแนน
        $attendance = PeriodAttendance::summaryFor($course);
        // คนที่เวลาเรียนไม่ถึงเกณฑ์แต่ยังไม่ได้ตั้ง มส./มผ.
        $pendingMs = $students->filter(fn ($s) => ($attendance[$s->id]['ms'] ?? false) && ($results[$s->id]['special'] ?? null) === null)->count();

        $outcomes = CourseResult::where('course_id', $course->id)->get()->keyBy('student_id');

        return view('courses.gradebook', compact('course', 'students', 'scores', 'results', 'distribution', 'attendance', 'pendingMs', 'outcomes'));
    }

    /** ปพ.5 แบบบันทึกผลการพัฒนาคุณภาพผู้เรียนรายวิชา (พิมพ์เสนออนุมัติผลการเรียน) */
    public function pp5(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);
        $course->load(['assessments', 'subject', 'classroom.homeroomTeacher', 'teacher', 'term']);
        $students = $course->classroom->roster()->get();
        $scores = Score::whereIn('assessment_id', $course->assessments->pluck('id'))->get()
            ->groupBy('student_id')->map(fn ($rows) => $rows->pluck('score', 'assessment_id'));
        $results = $course->results();
        $outcomes = CourseResult::where('course_id', $course->id)->get()->keyBy('student_id');
        $attendance = PeriodAttendance::summaryFor($course);

        $activity = $course->isActivity();
        $levels = $activity ? array_keys(Grade::ACTIVITY) : array_merge(array_values(Grade::SCALE), array_keys(Grade::SPECIAL));
        $final = $students->map(fn ($s) => $results[$s->id]['grade'] ?? null);
        $distribution = collect($levels)->mapWithKeys(fn ($g) => [$g => $final->filter(fn ($v) => $v === $g)->count()]);

        return view('courses.pp5', [
            'course' => $course, 'students' => $students, 'scores' => $scores, 'results' => $results,
            'outcomes' => $outcomes, 'attendance' => $attendance, 'activity' => $activity, 'distribution' => $distribution,
            'graded' => $final->filter(fn ($g) => $g !== null)->count(),
            'passed' => $final->filter(fn ($g) => Grade::passed($g))->count(),
            'good' => $final->filter(fn ($g) => is_numeric($g) && (float) $g >= 3)->count(),
        ]);
    }

    public function save(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course, true);
        $course->load('assessments');
        $max = $course->assessments->pluck('max_score', 'id');

        $request->validate(['scores' => ['array']]);
        $studentIds = $course->classroom->roster()->pluck('students.id')->flip();

        $errors = [];
        $edits = []; // รายวิชาที่ล็อกแล้ว (ผู้ดูแลแก้): เก็บค่าเดิม → ใหม่ ลงประวัติ
        DB::transaction(function () use ($request, $max, $studentIds, $course, &$errors, &$edits) {
            foreach ((array) $request->input('scores') as $studentId => $row) {
                if (! isset($studentIds[$studentId])) {
                    continue;
                }
                foreach ((array) $row as $assessmentId => $value) {
                    if (! isset($max[$assessmentId])) {
                        continue;
                    }
                    $value = trim((string) $value);
                    $old = $course->locked ? Score::where(['assessment_id' => $assessmentId, 'student_id' => $studentId])->value('score') : null;
                    if ($value === '') {
                        Score::where(['assessment_id' => $assessmentId, 'student_id' => $studentId])->delete();
                        if ($old !== null) {
                            $edits[] = [$studentId, $assessmentId, $old, null];
                        }

                        continue;
                    }
                    if (! is_numeric($value) || $value < 0 || $value > $max[$assessmentId]) {
                        $errors[] = $value;

                        continue;
                    }
                    Score::updateOrCreate(
                        ['assessment_id' => $assessmentId, 'student_id' => $studentId],
                        ['score' => $value]
                    );
                    if ($course->locked && ($old === null || (float) $old !== (float) $value)) {
                        $edits[] = [$studentId, $assessmentId, $old, (float) $value];
                    }
                }
            }
        });
        if ($edits) {
            $codes = Student::whereIn('id', array_column($edits, 0))->pluck('student_code', 'id');
            $names = $course->assessments->pluck('name', 'id');
            Audit::log('grade.locked_edit', $course, "แก้คะแนนหลังล็อก {$this->courseLabel($course)} ".count($edits).' ช่อง', [
                'cells' => array_map(fn ($e) => ['student' => $codes[$e[0]] ?? $e[0], 'assessment' => $names[$e[1]] ?? $e[1], 'old' => $e[2], 'new' => $e[3]], $edits),
            ]);
        }

        if ($request->wantsJson()) {
            $course->unsetRelation('assessments');

            return response()->json(['ok' => true, 'errors' => $errors, 'results' => $course->results()]);
        }

        $msg = 'บันทึกคะแนนแล้ว';
        if ($errors) {
            $msg .= ' (ข้ามค่าที่ไม่ถูกต้อง '.count($errors).' ช่อง)';
        }

        return back()->with('success', $msg);
    }

    /**
     * บันทึกผลพิเศษ (ร/มส/มผ) และผลการแก้ตัวของนักเรียนหนึ่งคน
     * ผลพิเศษแก้ได้เฉพาะตอนยังไม่ล็อก (ผู้ดูแลแก้ได้เสมอ) · ผลแก้ตัวบันทึกได้แม้ล็อกแล้ว เพราะการแก้ตัวทำหลังประกาศผล
     */
    public function saveOutcome(Request $request, Course $course, Student $student)
    {
        $this->authorizeCourse($request, $course);
        $course->load(['subject', 'assessments']);
        $inCourse = $student->classroom_id === $course->classroom_id
            || Score::where('student_id', $student->id)->whereIn('assessment_id', $course->assessments->pluck('id'))->exists()
            || CourseResult::where(['course_id' => $course->id, 'student_id' => $student->id])->exists();
        abort_unless($inCourse, 404);

        $activity = $course->isActivity();
        $data = $request->validate([
            'special' => ['nullable', Rule::in(array_keys(Grade::specialOptions($activity)))],
            'remedial_grade' => ['nullable', 'string', 'max:4'],
            'remedied_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $existing = CourseResult::where(['course_id' => $course->id, 'student_id' => $student->id])->first();
        $special = $data['special'] ?? null;
        if ($course->locked && ! $request->user()->isAdmin()) {
            abort_if($request->has('special') && $special !== $existing?->special, 403, 'รายวิชานี้ล็อกแล้ว เปลี่ยน ร/มส ได้เฉพาะฝ่ายวิชาการ (บันทึกผลแก้ตัวได้)');
            $special = $existing?->special;
        }

        $remedial = $data['remedial_grade'] ?? null;
        if ($remedial !== null) {
            $original = $special ?? ($course->results()[$student->id]['computed'] ?? null);
            $allowed = Grade::remedialOptions($original);
            if (! in_array($remedial, $allowed, true)) {
                throw ValidationException::withMessages(['remedial_grade' => $allowed
                    ? 'ผล '.$original.' แก้ตัวได้เป็น '.implode(', ', $allowed).' เท่านั้น'
                    : 'ผล '.($original ?? '-').' ไม่ต้องแก้ตัว (แก้ตัวได้เฉพาะ 0, ร, มส, มผ)']);
            }
        }

        $before = ['special' => $existing?->special, 'remedial_grade' => $existing?->remedial_grade];
        $after = ['special' => $special, 'remedial_grade' => $remedial];
        if ($before !== $after) {
            Audit::log('grade.outcome', $student, "ผลพิเศษ/แก้ตัว {$student->fullName()} {$this->courseLabel($course)}: "
                .($before['special'] ?? '-').'/'.($before['remedial_grade'] ?? '-').' → '.($special ?? '-').'/'.($remedial ?? '-'),
                ['course_id' => $course->id, 'before' => $before, 'after' => $after]);
        }

        if ($special === null && $remedial === null && blank($data['note'] ?? null)) {
            $existing?->delete();
        } else {
            CourseResult::updateOrCreate(['course_id' => $course->id, 'student_id' => $student->id], [
                'special' => $special,
                'remedial_grade' => $remedial,
                'remedied_on' => $remedial !== null ? ($data['remedied_on'] ?? $existing?->remedied_on ?? today()) : null,
                'note' => $data['note'] ?? null,
                'recorded_by' => $request->user()->id,
            ]);
        }

        return back()->with('success', "บันทึกผลของ {$student->fullName()} แล้ว");
    }

    /** ตั้ง มส. (กิจกรรม = มผ.) ให้ทุกคนที่เวลาเรียนรายวิชาไม่ถึงเกณฑ์ */
    public function applyMs(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course, true);
        $course->load('subject');
        $special = $course->isActivity() ? 'มผ' : 'มส';
        $count = 0;
        foreach (PeriodAttendance::summaryFor($course) as $studentId => $row) {
            if (! $row['ms'] || ! $course->classroom->roster()->where('students.id', $studentId)->exists()) {
                continue;
            }
            $result = CourseResult::firstOrNew(['course_id' => $course->id, 'student_id' => $studentId]);
            if ($result->special !== null) {
                continue;
            }
            $result->fill(['special' => $special, 'recorded_by' => $request->user()->id])->save();
            $count++;
        }
        if ($count) {
            Audit::log('grade.apply_ms', $course, "ตั้ง {$special} ให้ {$count} คน (เวลาเรียนไม่ถึงเกณฑ์) {$this->courseLabel($course)}");
        }

        return back()->with('success', "ตั้ง {$special}. ให้ {$count} คนที่เวลาเรียนไม่ถึงร้อยละ ".PeriodAttendance::MIN_PERCENT);
    }

    public function storeAssessment(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course, true);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'max_score' => ['required', 'numeric', 'min:0.5', 'max:1000'],
        ]);
        $course->assessments()->create($data + ['sort' => ($course->assessments()->max('sort') ?? 0) + 1]);

        return back()->with('success', 'เพิ่มช่องคะแนนแล้ว');
    }

    public function updateAssessment(Request $request, Assessment $assessment)
    {
        $this->authorizeCourse($request, $assessment->course, true);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'max_score' => ['required', 'numeric', 'min:0.5', 'max:1000'],
            'sort' => ['nullable', 'integer'],
        ]);
        $assessment->update($data);

        return back()->with('success', 'แก้ไขช่องคะแนนแล้ว');
    }

    public function destroyAssessment(Request $request, Assessment $assessment)
    {
        $this->authorizeCourse($request, $assessment->course, true);
        $count = $assessment->scores()->count();
        if ($count) {
            Audit::log('grade.delete_assessment', $assessment->course, "ลบช่องคะแนน \"{$assessment->name}\" พร้อมคะแนน {$count} คน {$this->courseLabel($assessment->course)}");
        }
        $assessment->delete();

        return back()->with('success', 'ลบช่องคะแนนแล้ว');
    }

    public function export(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);
        $course->load(['assessments', 'subject', 'classroom']);
        $students = $course->classroom->roster()->get();
        $scores = Score::whereIn('assessment_id', $course->assessments->pluck('id'))->get()
            ->groupBy('student_id')->map(fn ($r) => $r->pluck('score', 'assessment_id'));
        $results = $course->results();

        $filename = "คะแนน-{$course->subject->code}-".str_replace('/', '-', $course->classroom->name()).'.csv';

        return response()->streamDownload(function () use ($course, $students, $scores, $results) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $head = ['เลขที่', 'รหัส', 'ชื่อ-สกุล'];
            foreach ($course->assessments as $a) {
                $head[] = "{$a->name} ({$a->max_score})";
            }
            fputcsv($out, array_merge($head, ['รวม ('.$course->maxTotal().')', 'ผลการเรียน', 'ผลก่อนแก้ตัว']));
            foreach ($students as $s) {
                $row = [$s->number, $s->student_code, $s->fullName()];
                foreach ($course->assessments as $a) {
                    $row[] = $scores[$s->id][$a->id] ?? '';
                }
                $r = $results[$s->id] ?? [];
                $row[] = $r['total'] ?? '';
                $row[] = $r['grade'] ?? '';
                $row[] = ($r['remedial'] ?? null) !== null ? $r['original'] : '';
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
