<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\PeriodAttendance;
use App\Models\SchoolEvent;
use App\Models\Student;
use App\Models\Submission;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Support\Settings;
use Illuminate\Http\Request;

/** หน้าของนักเรียน: เห็นเฉพาะข้อมูลของตัวเอง */
class StudentPortalController extends Controller
{
    private function me(Request $request): Student
    {
        $student = $request->user()->studentProfile;
        abort_unless($student, 403, 'บัญชีนี้ยังไม่ได้ผูกกับข้อมูลนักเรียน กรุณาติดต่อครูประจำชั้น');

        return $student;
    }

    public function home(Request $request)
    {
        $student = $this->me($request)->load('classroom.homeroomTeacher', 'behaviorRecords');
        $term = Term::current();
        $today = today()->toDateString();

        $slots = ($term && $student->classroom_id && isset(TimetableSlot::days()[today()->dayOfWeekIso]))
            ? TimetableSlot::with('course.subject', 'course.teacher')->where('term_id', $term->id)
                ->where('classroom_id', $student->classroom_id)->where('day', today()->dayOfWeekIso)->orderBy('period')->get()
            : collect();
        $periodStatus = PeriodAttendance::where('student_id', $student->id)->where('date', $today)->get()
            ->keyBy(fn ($p) => $p->course_id.'-'.$p->period);

        $assignments = Assignment::with('course.subject')
            ->whereHas('course', fn ($q) => $q->forStudent($student, $student->classroom_id)->when($term, fn ($t) => $t->where('term_id', $term->id)))
            ->latest()->limit(30)->get();
        $subs = Submission::where('student_id', $student->id)->whereIn('assignment_id', $assignments->pluck('id'))->get()->keyBy('assignment_id');
        $pending = $assignments->filter(fn ($a) => ! $subs->get($a->id)?->submitted_at)->sortBy(fn ($a) => $a->due_at ?? now()->addYears(5))->values();

        return view('student.home', [
            'student' => $student,
            'todayAtt' => Attendance::where('student_id', $student->id)->where('date', $today)->first(),
            'slots' => $slots,
            'periodStatus' => $periodStatus,
            'times' => Settings::periodTimes(),
            'pending' => $pending,
            'graded' => $subs->whereNotNull('score')->sortByDesc('graded_at')->take(3)->map(fn ($s) => $s->setRelation('assignment', $assignments->firstWhere('id', $s->assignment_id))),
            'events' => SchoolEvent::visibleTo($request->user())->where('end_date', '>=', $today)->orderBy('start_date')->limit(4)->get(),
            'posts' => FeedController::query($request->user())->paginate(6, ['*'], 'page', 1),
        ]);
    }

    /** ข้อมูลเต็ม: การมาเรียน ผลการเรียน ตารางเรียน ความประพฤติ สุขภาพ (ใช้หน้าเดียวกับผู้ปกครอง) */
    public function info(Request $request)
    {
        return view('parent.child', ParentController::childData($request, $this->me($request)));
    }
}
