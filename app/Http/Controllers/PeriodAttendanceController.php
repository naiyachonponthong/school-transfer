<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Course;
use App\Models\PeriodAttendance;
use App\Models\Substitution;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Services\Notifier;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * เช็คชื่อรายคาบ: ครูผู้สอนเช็คตามคาบในตารางสอน
 * นับเวลาเรียนรายวิชา → ต่ำกว่า 80% = มส. (ไม่มีสิทธิ์สอบ)
 */
class PeriodAttendanceController extends Controller
{
    /** ครูประจำวิชา ผู้ดูแล หรือครูที่ได้รับมอบให้สอนแทนในวันนั้น */
    private function authorizeCourse(Request $request, Course $course): void
    {
        $date = rescue(fn () => Carbon::parse($request->input('date') ?: today())->toDateString(), today()->toDateString(), false);
        abort_unless($course->canEdit($request->user()) || Substitution::covers($request->user(), $course, $date), 403, 'รายวิชานี้ไม่ได้อยู่ในความรับผิดชอบของคุณ');
    }

    /** คาบที่ต้องเช็ควันนี้ (จากตารางสอน) + เลือกวิชาอื่น/วันอื่นเองได้ */
    public function index(Request $request)
    {
        $user = $request->user();
        $term = Term::current();
        $date = Carbon::parse($request->query('date', today()->toDateString()));

        $slots = collect();
        if ($term && isset(TimetableSlot::days()[$date->dayOfWeekIso])) {
            // คาบที่รับสอนแทนในวันนั้นขึ้นในรายการของครูสอนแทนด้วย
            $covering = Substitution::where('substitute_id', $user->id)->where('date', $date->toDateString())->pluck('timetable_slot_id');
            $slots = TimetableSlot::with(['course.subject', 'classroom'])
                ->where('term_id', $term->id)->where('day', $date->dayOfWeekIso)->whereNotNull('course_id')
                ->when(! $user->isAdmin(), fn ($q) => $q->where(fn ($w) => $w->whereHas('course', fn ($c) => $c->where('teacher_id', $user->id))->orWhereIn('id', $covering)))
                ->orderBy('period')->get();
        }

        // คาบไหนเช็คแล้วบ้าง
        $done = PeriodAttendance::where('date', $date->toDateString())
            ->whereIn('course_id', $slots->pluck('course_id'))
            ->select('course_id', 'period', DB::raw('count(*) as c'))->groupBy('course_id', 'period')->get()
            ->mapWithKeys(fn ($r) => [$r->course_id.'-'.$r->period => $r->c]);

        $courses = Course::with(['subject', 'classroom'])
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->when(! $user->isAdmin(), fn ($q) => $q->where('teacher_id', $user->id))
            ->get()->sortBy(fn ($c) => [$c->classroom->level_order, $c->classroom->room, $c->subject->code])->values();

        return view('period-attendance.index', [
            'date' => $date,
            'slots' => $slots,
            'done' => $done,
            'courses' => $courses,
            'times' => Settings::periodTimes(),
        ]);
    }

    public function sheet(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);
        $data = $request->validate([
            'date' => ['nullable', 'date', 'before_or_equal:today'],
            'period' => ['required', 'integer', 'between:1,12'],
        ]);
        $date = Carbon::parse($data['date'] ?? today()->toDateString());
        $course->load('subject', 'classroom');
        $students = $course->students()->get();

        $records = PeriodAttendance::where(['course_id' => $course->id, 'date' => $date->toDateString(), 'period' => $data['period']])
            ->get()->keyBy('student_id');

        // ถ้ายังไม่ได้เช็คคาบนี้ ใช้ผลเช็คชื่อรายวันเป็นค่าตั้งต้นให้คนที่ลา/ป่วย/ขาดทั้งวัน ครูไม่ต้องกดซ้ำ
        $daily = Attendance::where('date', $date->toDateString())->whereIn('student_id', $students->pluck('id'))
            ->get()->keyBy('student_id');

        return view('period-attendance.sheet', [
            'course' => $course,
            'date' => $date,
            'period' => (int) $data['period'],
            'students' => $students,
            'records' => $records,
            'daily' => $daily,
            'summary' => PeriodAttendance::summaryFor($course),
            'time' => Settings::periodTimes()[$data['period'] - 1] ?? '',
        ]);
    }

    public function save(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'period' => ['required', 'integer', 'between:1,12'],
            'status' => ['required', 'array'],
            'status.*' => [Rule::in(array_keys(Attendance::STATUSES))],
        ]);
        $validIds = $course->students()->pluck('students.id')->flip();
        $date = $data['date'];
        $before = PeriodAttendance::where(['course_id' => $course->id, 'date' => $date, 'period' => $data['period']])->pluck('status', 'student_id');

        DB::transaction(function () use ($data, $validIds, $course, $date, $request) {
            foreach ($data['status'] as $sid => $status) {
                if (! isset($validIds[$sid])) {
                    continue;
                }
                PeriodAttendance::updateOrCreate(
                    ['course_id' => $course->id, 'student_id' => $sid, 'date' => $date, 'period' => $data['period']],
                    ['status' => $status, 'recorded_by' => $request->user()->id]
                );
            }
        });

        // มาโรงเรียน (เช็คชื่อรายวันว่ามา/สาย) แต่ไม่เข้าคาบนี้ = โดดเรียน → แจ้งผู้ปกครองทันที
        if (Carbon::parse($date)->isToday()) {
            $cutting = array_keys(array_filter($data['status'], fn ($s, $sid) => $s === 'absent' && ($before[$sid] ?? null) !== 'absent', ARRAY_FILTER_USE_BOTH));
            $presentToday = Attendance::where('date', $date)->whereIn('student_id', $cutting)->whereIn('status', ['present', 'late'])->pluck('student_id');
            $course->loadMissing('subject');
            foreach ($course->students()->whereIn('students.id', $presentToday)->get() as $s) {
                Notifier::parents($s, '⚠️ น้อง'.($s->nickname ?: $s->first_name)." มาโรงเรียนวันนี้ แต่ไม่เข้าเรียนวิชา{$course->subject->name} คาบที่ {$data['period']}");
            }
        }

        $counts = collect($data['status'])->countBy();

        return redirect()->route('period-attendance.index', ['date' => $date])
            ->with('success', "บันทึกคาบที่ {$data['period']} วิชา{$course->subject->name} ห้อง {$course->classroom->name()} แล้ว (มา ".(($counts['present'] ?? 0) + ($counts['late'] ?? 0)).' · ขาด '.($counts['absent'] ?? 0).')');
    }

    /** สรุปเวลาเรียนรายวิชา ใครเสี่ยง มส. */
    public function report(Request $request, Course $course)
    {
        $this->authorizeCourse($request, $course);
        $course->load('subject', 'classroom', 'term');

        return view('period-attendance.report', [
            'course' => $course,
            'students' => $course->students()->get(),
            'summary' => PeriodAttendance::summaryFor($course),
            // จำนวนคาบที่สอนไปแล้ว (นับคู่ วันที่+คาบ ไม่ซ้ำ) — เขียนแบบไม่พึ่ง || ซึ่ง MySQL ถือเป็น OR ไม่ใช่ต่อสตริง
            'sessions' => PeriodAttendance::where('course_id', $course->id)->select('date', 'period')->distinct()->get()->count(),
        ]);
    }
}
