<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\StudentEvaluation;
use App\Models\Term;
use App\Support\Grade;
use Illuminate\Http\Request;

/** แบบรายงานการพัฒนาคุณภาพผู้เรียนรายบุคคล (ปพ.6) — พิมพ์ทีละคนหรือทั้งห้อง */
class ReportCardController extends Controller
{
    public function show(Request $request, Student $student)
    {
        abort_unless($student->canBeViewedBy($request->user()), 403);
        $term = Term::find($request->query('term')) ?? Term::current();
        abort_if($term && ! $term->resultsVisibleTo($request->user()), 403, 'โรงเรียนยังไม่ประกาศผลการเรียนของภาคเรียนนี้');

        return view('students.report-card', ['term' => $term, 'cards' => [self::data($student, $term)], 'back' => url()->previous()]);
    }

    /** พิมพ์ทั้งห้องในครั้งเดียว (หนึ่งคนต่อหนึ่งหน้า) */
    public function classroom(Request $request)
    {
        $term = Term::find($request->query('term')) ?? Term::current();
        $classroom = Classroom::findOrFail($request->query('classroom'));
        $students = $classroom->students()->with('classroom.homeroomTeacher')->get();

        return view('students.report-card', [
            'term' => $term,
            'cards' => $students->map(fn ($s) => self::data($s, $term))->all(),
            'back' => route('reports.index'),
            'classroom' => $classroom,
        ]);
    }

    public static function data(Student $student, ?Term $term): array
    {
        $student->loadMissing('classroom.homeroomTeacher');
        // ปพ.6 ของปีเก่า: หัวกระดาษต้องเป็นห้อง/เลขที่/ครูประจำชั้นของปีนั้น ไม่ใช่ห้องปัจจุบัน
        if ($term && ($past = $student->enrollments()->where('year', $term->year)->with('classroom.homeroomTeacher')->first())) {
            $student->setRelation('classroom', $past->classroom);
            $student->number = $past->number ?? $student->number;
        }
        $grades = StudentController::gradesFor($student, $term);
        [$activities, $academic] = $grades->partition(fn ($g) => $g['course']->isActivity());
        $credit = fn ($g) => (float) $g['course']->subject->credit;

        $inTerm = fn ($q, string $col) => $q->when($term?->start_date && $term?->end_date,
            fn ($w) => $w->whereBetween($col, [$term->start_date->toDateString(), $term->end_date->toDateString()]));

        $attendance = $inTerm($student->attendances(), 'date')->get()->countBy('status');
        $days = $attendance->sum();
        $came = ($attendance['present'] ?? 0) + ($attendance['late'] ?? 0);

        return [
            'student' => $student,
            'academic' => $academic->values(),
            'activities' => $activities->values(),
            'gpa' => Grade::gpa($academic->map(fn ($g) => ['grade' => $g['grade'], 'credit' => $credit($g)])),
            'credits' => $academic->sum($credit),
            'earned' => $academic->filter(fn ($g) => Grade::passed($g['grade']))->sum($credit),
            'attendance' => $attendance,
            'days' => $days,
            'attendancePercent' => $days ? round($came / $days * 100, 1) : null,
            'measurement' => $inTerm($student->measurements(), 'measured_on')->first(),
            'behavior' => $student->behaviorScore(),
            'evaluation' => $term ? StudentEvaluation::where(['term_id' => $term->id, 'student_id' => $student->id])->first() : null,
        ];
    }
}
