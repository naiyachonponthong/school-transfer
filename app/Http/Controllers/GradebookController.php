<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\PeriodAttendance;
use App\Models\Score;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GradebookController extends Controller
{
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
        $students = $course->classroom->students()->get();

        $scores = Score::whereIn('assessment_id', $course->assessments->pluck('id'))->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->pluck('score', 'assessment_id'));

        $results = $course->results();
        $distribution = collect($results)->pluck('grade')->filter(fn ($g) => $g !== null)->countBy();
        // เวลาเรียนรายวิชา (จากเช็คชื่อรายคาบ) — ครูเห็นคนที่ต้องได้ มส. ขณะกรอกคะแนน
        $attendance = PeriodAttendance::summaryFor($course);

        return view('courses.gradebook', compact('course', 'students', 'scores', 'results', 'distribution', 'attendance'));
    }

    public function save(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course, true);
        $course->load('assessments');
        $max = $course->assessments->pluck('max_score', 'id');

        $request->validate(['scores' => ['array']]);
        $studentIds = $course->classroom->students()->pluck('id')->flip();

        $errors = [];
        DB::transaction(function () use ($request, $max, $studentIds, &$errors) {
            foreach ((array) $request->input('scores') as $studentId => $row) {
                if (! isset($studentIds[$studentId])) {
                    continue;
                }
                foreach ((array) $row as $assessmentId => $value) {
                    if (! isset($max[$assessmentId])) {
                        continue;
                    }
                    $value = trim((string) $value);
                    if ($value === '') {
                        Score::where(['assessment_id' => $assessmentId, 'student_id' => $studentId])->delete();

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
                }
            }
        });

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
        $assessment->delete();

        return back()->with('success', 'ลบช่องคะแนนแล้ว');
    }

    public function export(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);
        $course->load(['assessments', 'subject', 'classroom']);
        $students = $course->classroom->students()->get();
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
            fputcsv($out, array_merge($head, ['รวม ('.$course->maxTotal().')', 'เกรด']));
            foreach ($students as $s) {
                $row = [$s->number, $s->student_code, $s->fullName()];
                foreach ($course->assessments as $a) {
                    $row[] = $scores[$s->id][$a->id] ?? '';
                }
                $row[] = $results[$s->id]['total'] ?? '';
                $row[] = $results[$s->id]['grade'] ?? '';
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
