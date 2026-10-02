<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Term;
use App\Support\Grade;
use Illuminate\Http\Request;

/** สมุดรายงานผลการเรียน (พิมพ์ได้) */
class ReportCardController extends Controller
{
    public function show(Request $request, Student $student)
    {
        $user = $request->user();
        abort_unless($student->canBeViewedBy($user), 403);

        $term = Term::find($request->query('term')) ?? Term::current();
        $student->load('classroom.homeroomTeacher');
        $grades = StudentController::gradesFor($student, $term);

        $attendance = $student->attendances()
            ->when($term?->start_date && $term?->end_date, fn ($q) => $q->whereBetween('date', [$term->start_date->toDateString(), $term->end_date->toDateString()]))
            ->get()->countBy('status');

        return view('students.report-card', [
            'student' => $student,
            'term' => $term,
            'grades' => $grades,
            'gpa' => Grade::gpa($grades->map(fn ($g) => ['grade' => $g['grade'], 'credit' => (float) $g['course']->subject->credit])),
            'credits' => $grades->sum(fn ($g) => (float) $g['course']->subject->credit),
            'attendance' => $attendance,
            'behavior' => $student->behaviorScore(),
        ]);
    }
}
