<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\LeaveRequest;
use App\Models\Student;
use App\Services\Notifier;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    private function classroomsFor(Request $request)
    {
        $all = Classroom::currentYear()->ordered()->with('homeroomTeacher')->get();
        $mine = $request->user()->myClassrooms()->pluck('id')->all();

        // ห้องที่ตัวเองเป็นครูประจำชั้นขึ้นก่อน
        return $all->sortBy(fn ($c) => in_array($c->id, $mine) ? 0 : 1)->values();
    }

    public function index(Request $request)
    {
        $classrooms = $this->classroomsFor($request);
        $classroom = $classrooms->firstWhere('id', (int) $request->query('classroom')) ?? $classrooms->first();
        $date = Carbon::parse($request->query('date', today()->toDateString()));

        $students = collect();
        $records = collect();
        $pendingLeaves = collect();
        if ($classroom) {
            $students = $classroom->students()->get();
            $records = Attendance::where('date', $date->toDateString())
                ->whereIn('student_id', $students->pluck('id'))
                ->get()->keyBy('student_id');
            // ใบลาที่ยังไม่อนุมัติซึ่งครอบคลุมวันนี้ — กันครูเผลอเช็ค "ขาด"
            $pendingLeaves = LeaveRequest::where('status', 'pending')
                ->whereIn('student_id', $students->pluck('id'))
                ->where('start_date', '<=', $date->toDateString())
                ->where('end_date', '>=', $date->toDateString())
                ->get()->keyBy('student_id');
        }

        return view('attendance.index', compact('classrooms', 'classroom', 'date', 'students', 'records', 'pendingLeaves'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', 'array'],
            'status.*' => [Rule::in(array_keys(Attendance::STATUSES))],
            'note' => ['array'],
            'note.*' => ['nullable', 'string', 'max:255'],
        ]);

        $classroom = Classroom::findOrFail($data['classroom_id']);
        $validIds = $classroom->students()->pluck('id')->all();
        $isToday = Carbon::parse($data['date'])->isToday();
        $before = Attendance::where('date', $data['date'])->whereIn('student_id', $validIds)->pluck('status', 'student_id')->all();

        DB::transaction(function () use ($data, $validIds, $classroom, $isToday, $request) {
            foreach ($data['status'] as $studentId => $status) {
                if (! in_array((int) $studentId, $validIds, true)) {
                    continue;
                }
                $existing = Attendance::where('student_id', $studentId)->where('date', $data['date'])->first();
                Attendance::updateOrCreate(
                    ['student_id' => $studentId, 'date' => $data['date']],
                    [
                        'classroom_id' => $classroom->id,
                        'status' => $status,
                        'note' => $data['note'][$studentId] ?? $existing?->note,
                        'checked_at' => $existing?->checked_at ?? ($isToday ? now()->format('H:i:s') : null),
                        'recorded_by' => $request->user()->id,
                    ]
                );
            }
        });

        // แจ้งผู้ปกครองทาง LINE เฉพาะวันนี้ และเฉพาะคนที่ขาด/สาย (ไม่ส่งซ้ำถ้าสถานะเดิมไม่เปลี่ยน)
        if ($isToday && \App\Support\Settings::get('line_notify_absent')) {
            foreach (Student::whereIn('id', array_keys(array_filter($data['status'], fn ($s) => in_array($s, ['absent', 'late'], true))))->get() as $s) {
                if (($before[$s->id] ?? null) === $data['status'][$s->id]) {
                    continue;
                }
                $nick = 'น้อง'.($s->nickname ?: $s->first_name);
                Notifier::parents($s, $data['status'][$s->id] === 'absent'
                    ? "❗ วันนี้{$nick}ไม่ได้มาเรียน (ห้อง {$classroom->name()}) หากลาป่วย/ลากิจ ส่งใบลาได้ในระบบ"
                    : "⏰ วันนี้{$nick}มาสาย", route('parent.leave'));
            }
        }

        $counts = collect($data['status'])->countBy();
        $summary = collect(Attendance::STATUSES)->map(fn ($s, $k) => $counts[$k] ?? 0)
            ->filter()->map(fn ($n, $k) => Attendance::label($k).' '.$n)->implode(' · ');

        return redirect()->route('attendance.index', ['classroom' => $classroom->id, 'date' => $data['date']])
            ->with('success', "บันทึกเช็คชื่อห้อง {$classroom->name()} แล้ว ({$summary})");
    }

    /** ภาพรวมทั้งโรงเรียนวันนี้ */
    public function today(Request $request)
    {
        $date = Carbon::parse($request->query('date', today()->toDateString()));
        $classrooms = Classroom::currentYear()->ordered()->withCount('students')->with('homeroomTeacher')->get();

        $counts = Attendance::where('date', $date->toDateString())
            ->select('classroom_id', 'status', DB::raw('count(*) as c'))
            ->groupBy('classroom_id', 'status')->get()
            ->groupBy('classroom_id')
            ->map(fn ($rows) => $rows->pluck('c', 'status'));

        $absentees = Attendance::with('student.classroom')
            ->where('date', $date->toDateString())
            ->whereIn('status', ['absent', 'late', 'leave', 'sick'])
            ->get()->sortBy(fn ($a) => [$a->status, $a->student->classroom?->level_order, $a->student->classroom?->room, $a->student->number]);

        return view('attendance.today', compact('date', 'classrooms', 'counts', 'absentees'));
    }

    /** รายงานประจำเดือน แบบตารางวัน × นักเรียน */
    public function report(Request $request)
    {
        $classrooms = $this->classroomsFor($request);
        $classroom = $classrooms->firstWhere('id', (int) $request->query('classroom')) ?? $classrooms->first();
        $month = Carbon::parse(($request->query('month') ?: today()->format('Y-m')).'-01');

        $days = collect(CarbonPeriod::create($month->copy()->startOfMonth(), $month->copy()->endOfMonth()))
            ->filter(fn ($d) => ! $d->isWeekend())->values();

        $students = $classroom ? $classroom->students()->get() : collect();
        $grid = Attendance::whereIn('student_id', $students->pluck('id'))
            ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->keyBy(fn ($r) => $r->date->toDateString()));

        if ($request->query('export') === 'csv' && $classroom) {
            return $this->exportCsv($classroom, $month, $days, $students, $grid);
        }

        return view('attendance.report', compact('classrooms', 'classroom', 'month', 'days', 'students', 'grid'));
    }

    private function exportCsv(Classroom $classroom, Carbon $month, $days, $students, $grid)
    {
        $filename = 'attendance-'.str_replace('/', '-', $classroom->name()).'-'.$month->format('Y-m').'.csv';

        return response()->streamDownload(function () use ($days, $students, $grid) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // ให้ Excel อ่านภาษาไทยได้
            $head = ['เลขที่', 'รหัส', 'ชื่อ-สกุล'];
            foreach ($days as $d) {
                $head[] = $d->day;
            }
            foreach (Attendance::STATUSES as $s) {
                $head[] = $s[0];
            }
            fputcsv($out, $head);
            foreach ($students as $st) {
                $row = [$st->number, $st->student_code, $st->fullName()];
                $mine = $grid[$st->id] ?? collect();
                foreach ($days as $d) {
                    $rec = $mine[$d->toDateString()] ?? null;
                    $row[] = $rec ? Attendance::short($rec->status) : '';
                }
                $counts = $mine->countBy('status');
                foreach (array_keys(Attendance::STATUSES) as $k) {
                    $row[] = $counts[$k] ?? 0;
                }
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
