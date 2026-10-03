<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->isParent()) {
            return redirect()->route('parent.home');
        }
        if ($user->isStudent()) {
            return redirect()->route('student.home');
        }

        $today = today()->toDateString();
        $term = Term::current();

        $studentCount = Student::active()->count();
        $classrooms = Classroom::currentYear()->ordered()->withCount('students')->get();

        $todayCounts = Attendance::where('date', $today)
            ->select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status');
        $checkedClassroomIds = Attendance::where('date', $today)->distinct()->pluck('classroom_id')->filter()->all();
        $notChecked = $classrooms->filter(fn ($c) => $c->students_count > 0 && ! in_array($c->id, $checkedClassroomIds));


        $myClassrooms = $user->myClassrooms();
        $leaveQuery = LeaveRequest::with('student.classroom')->where('status', 'pending');
        if (! $user->isAdmin()) {
            $leaveQuery->whereHas('student', fn ($q) => $q->whereIn('classroom_id', $myClassrooms->pluck('id')));
        }
        $pendingLeaves = $leaveQuery->latest()->limit(5)->get();
        $pendingLeaveCount = (clone $leaveQuery)->count();

        $todaySlots = collect();
        if ($term && today()->isWeekday()) {
            $todaySlots = TimetableSlot::with(['course.subject', 'classroom'])
                ->where('term_id', $term->id)
                ->where('day', today()->dayOfWeekIso)
                ->whereHas('course', fn ($q) => $q->where('teacher_id', $user->id))
                ->orderBy('period')->get();
        }

        // นักเรียนที่คะแนนความประพฤติต่ำกว่า 70
        $lowBehavior = Student::active()->with('classroom')
            ->withSum('behaviorRecords as points_sum', 'points')
            ->whereRaw('(select coalesce(sum(points), 0) from behavior_records where behavior_records.student_id = students.id) < ?', [70 - Student::BASE_BEHAVIOR])
            ->orderBy('points_sum')->limit(5)->get();

        $finance = null;
        $staffToday = collect();
        if ($user->hasPermission('finance.view')) {
            $open = Invoice::whereIn('status', ['unpaid', 'partial']);
            $finance = [
                'outstanding' => (float) (clone $open)->sum(DB::raw('total - discount - paid')),
                'count' => (clone $open)->count(),
                'collected_month' => (float) DB::table('payments')->whereNull('voided_at')->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
            ];

            // การลงเวลาของครู/บุคลากรวันนี้ — คนที่ยังไม่ลงเวลาขึ้นก่อน จะได้เห็นแล้วตามตัวได้ทัน
            // (เติมเนื้อหาคอลัมน์ซ้ายของแอดมินที่ไม่มีห้องประจำชั้น ไม่ให้ว่างเปล่า)
            $checkins = StaffAttendance::where('date', $today)->get()->keyBy('user_id');
            $staffToday = User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->where('id', '!=', $user->id)
                ->orderBy('name')->get(['id', 'name', 'avatar', 'position'])
                ->map(fn ($s) => ['user' => $s, 'checkin' => $checkins[$s->id] ?? null])
                ->sortBy(fn ($r) => $r['checkin']?->check_in ?? '0')->values();
        }

        // สถิติการมาทำงานของฉันเดือนนี้
        $myMonth = StaffAttendance::where('user_id', $user->id)
            ->where('date', '>=', today()->startOfMonth()->toDateString())
            ->get()->countBy('status');

        // ห้องของฉันวันนี้: ใครขาด/สาย/ลา (แสดงรูปแบบทีมงานเหมือนแอป HR)
        $myRoomsToday = $myClassrooms->map(function ($c) use ($today) {
            $rows = Attendance::with('student')->where('classroom_id', $c->id)->where('date', $today)->get();

            return [
                'classroom' => $c,
                'total' => $c->students()->count(),
                'checked' => $rows->count(),
                'groups' => [
                    'absent' => $rows->where('status', 'absent')->pluck('student'),
                    'late' => $rows->where('status', 'late')->pluck('student'),
                    'leave' => $rows->whereIn('status', ['leave', 'sick'])->pluck('student'),
                ],
            ];
        });

        // คาบปัจจุบัน
        $nowPeriod = null;
        foreach (\App\Support\Settings::periodTimes() as $i => $range) {
            [$from, $to] = array_pad(explode('-', str_replace('.', ':', $range)), 2, null);
            if ($from && $to && now()->format('H:i') >= trim($from) && now()->format('H:i') < trim($to)) {
                $nowPeriod = $i + 1;
            }
        }

        return view('dashboard.index', [
            'events' => \App\Models\SchoolEvent::visibleTo($user)->where('end_date', '>=', $today)->orderBy('start_date')->limit(4)->get(),
            'myMonth' => $myMonth,
            'staffToday' => $staffToday,
            'myRoomsToday' => $myRoomsToday,
            'nowPeriod' => $nowPeriod,
            'posts' => FeedController::query($user)->paginate(8, ['*'], 'page', 1)->withPath(route('feed.index')),
            'highlight' => Announcement::visibleTo($user)->orderByDesc('pinned')->latest()->first(),
            'term' => $term,
            'studentCount' => $studentCount,
            'teacherCount' => User::where('role', 'teacher')->where('is_active', true)->count(),
            'classroomCount' => $classrooms->count(),
            'todayCounts' => $todayCounts,
            'todayTotal' => $todayCounts->sum(),
            'notChecked' => $notChecked,
            'myClassrooms' => $myClassrooms,
            'pendingLeaves' => $pendingLeaves,
            'pendingLeaveCount' => $pendingLeaveCount,
            'todaySlots' => $todaySlots,
            'lowBehavior' => $lowBehavior,
            'finance' => $finance,
            'announcements' => Announcement::visibleTo($user)->with('author')->orderByDesc('pinned')->latest()->limit(4)->get(),
            'myCheckin' => StaffAttendance::where('user_id', $user->id)->where('date', $today)->first(),
        ]);
    }
}
