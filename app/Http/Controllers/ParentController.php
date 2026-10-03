<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\Student;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Support\Grade;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ParentController extends Controller
{
    private function ensureChild(Request $request, Student $student): Student
    {
        abort_unless($student->isGuardedBy($request->user()), 403, 'ไม่พบข้อมูลบุตรหลานของคุณ');

        return $student;
    }

    public function home(Request $request)
    {
        $user = $request->user();
        $children = $user->children()->with(['classroom.homeroomTeacher', 'behaviorRecords'])->get();
        $ids = $children->pluck('id');

        // อัตรามาเรียนภาคเรียนนี้ของลูกแต่ละคน (แสดงเป็นวงแหวน)
        $term = Term::current();
        $rates = Attendance::whereIn('student_id', $ids)
            ->when($term?->start_date && $term?->end_date, fn ($q) => $q->whereBetween('date', [$term->start_date->toDateString(), $term->end_date->toDateString()]))
            ->selectRaw("student_id, count(*) as total, sum(case when status in ('present','late') then 1 else 0 end) as came")
            ->groupBy('student_id')->get()->keyBy('student_id')
            ->map(fn ($r) => $r->total ? round($r->came / $r->total * 100) : null);

        return view('parent.home', [
            'rates' => $rates,
            'events' => \App\Models\SchoolEvent::visibleTo($user)->where('end_date', '>=', today()->toDateString())->orderBy('start_date')->limit(4)->get(),
            'posts' => FeedController::query($user)->paginate(8, ['*'], 'page', 1),
            'children' => $children,
            'todayStatus' => Attendance::whereIn('student_id', $ids)->where('date', today()->toDateString())->get()->keyBy('student_id'),
            'unpaid' => Invoice::whereIn('student_id', $ids)->whereIn('status', ['unpaid', 'partial'])->get(),
            'announcements' => Announcement::visibleTo($user)->orderByDesc('pinned')->latest()->limit(5)->get(),
            'readIds' => $user->belongsToMany(Announcement::class, 'announcement_reads')->pluck('announcements.id')->all(),
            'leaves' => LeaveRequest::with('student')->whereIn('student_id', $ids)->latest()->limit(5)->get(),
        ]);
    }

    public function child(Request $request, Student $student): \Illuminate\Contracts\View\View
    {
        $student = $this->ensureChild($request, $student);

        return view('parent.child', self::childData($request, $student));
    }

    /**
     * ข้อมูลหน้ารายละเอียดนักเรียน ใช้ร่วมกันระหว่างผู้ปกครอง (ดูลูก) และนักเรียน (ดูของตัวเอง)
     * ผู้เรียกต้องตรวจสิทธิ์ก่อนเรียกเสมอ
     */
    public static function childData(Request $request, Student $student): array
    {
        $isStudent = $request->user()->isStudent();
        $student->load(['classroom.homeroomTeacher', 'behaviorRecords', 'invoices', 'measurements', 'healthVisits', 'bookLoans.book']);

        $month = Carbon::parse(($request->query('month') ?: today()->format('Y-m')).'-01');
        $monthAtt = $student->attendances()
            ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->get()->keyBy(fn ($a) => $a->date->toDateString());

        $term = Term::current();
        $termAtt = $student->attendances()
            ->when($term?->start_date && $term?->end_date, fn ($q) => $q->whereBetween('date', [$term->start_date->toDateString(), $term->end_date->toDateString()]))
            ->get()->countBy('status');

        // ก่อนวันประกาศผล ผู้ปกครอง/นักเรียนยังไม่เห็นผลการเรียนของภาคนี้
        $resultsHidden = $term && ! $term->resultsVisibleTo($request->user());
        $grades = $resultsHidden ? collect() : StudentController::gradesFor($student, $term);

        $slots = ($term && $student->classroom_id) ? TimetableSlot::with('course.subject', 'course.teacher')
            ->where('term_id', $term->id)->where('classroom_id', $student->classroom_id)->get()
            ->keyBy(fn ($s) => $s->day.'-'.$s->period) : collect();

        // แบบประเมินตอบได้เฉพาะผู้ปกครอง นักเรียนไม่เห็นแท็บนี้
        $surveys = $isStudent ? collect() : \App\Models\Survey::where('is_active', true)->whereIn('respondent', ['parent', 'both'])->get();

        return [
            'student' => $student,
            'isStudent' => $isStudent,
            'month' => $month,
            'monthAtt' => $monthAtt,
            'termAtt' => $termAtt,
            'term' => $term,
            'grades' => $grades,
            'resultsHidden' => $resultsHidden,
            'gpa' => Grade::gpa($grades->map(fn ($g) => ['grade' => $g['grade'], 'credit' => (float) $g['course']->subject->credit])),
            'slots' => $slots,
            'periods' => Settings::periodTimes(),
            'tab' => $request->query('tab', 'overview'),
            'surveys' => $surveys,
            'leaves' => LeaveRequest::where('student_id', $student->id)->latest()->limit(30)->get(),
            'surveyResponses' => \App\Models\SurveyResponse::where('student_id', $student->id)->where('respondent_role', 'parent')
                ->where('term_id', $term?->id)->whereIn('survey_id', $surveys->pluck('id'))->get()->keyBy('survey_id'),
            // เวลาเรียนรายวิชา (เช็คชื่อรายคาบ) ภาคนี้ — เห็นล่วงหน้าว่าวิชาไหนเสี่ยง มส.
            'periodSummary' => $grades->mapWithKeys(fn ($g) => [$g['course']->id => \App\Models\PeriodAttendance::summaryFor($g['course'])[$student->id] ?? null]),
            // ผลสอบที่ครูเปิดให้ดู (ตรวจด้วยมือถือ)
            'examResults' => \App\Models\ExamResponse::with('exam.subject')->where('student_id', $student->id)->where('status', 'ok')
                ->whereHas('exam', fn ($q) => $q->where('published', true))->latest('scanned_at')->get(),
        ];
    }

    public function leaveForm(Request $request)
    {
        return view('parent.leave', [
            'children' => $request->user()->children()->with('classroom')->get(),
            'selected' => (int) $request->query('student'),
        ]);
    }

    public function leaveStore(Request $request)
    {
        $childIds = $request->user()->children()->pluck('students.id')->all();
        $data = $request->validate([
            'student_id' => ['required', Rule::in($childIds)],
            'type' => ['required', Rule::in(array_keys(LeaveRequest::TYPES))],
            'start_date' => ['required', 'date', 'after_or_equal:'.today()->subDays(7)->toDateString()],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ], [
            'student_id.required' => 'กรุณาเลือกบุตรหลาน',
            'student_id.in' => 'กรุณาเลือกบุตรหลาน',
            'start_date.after_or_equal' => 'ย้อนหลังได้ไม่เกิน 7 วัน',
        ]);

        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment')->store('leaves', 'local');
        }

        LeaveRequest::create($data + ['requested_by' => $request->user()->id, 'status' => 'pending']);

        return redirect()->route('parent.home')->with('success', 'ส่งใบลาแล้ว ครูประจำชั้นจะเห็นทันที');
    }
}
