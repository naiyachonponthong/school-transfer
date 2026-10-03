<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\CareCase;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\StaffAttendance;
use App\Models\StaffLeave;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\Grade;
use App\Support\RiskScan;
use Illuminate\Support\Facades\DB;

/** แดชบอร์ดผู้บริหาร: ภาพรวมการมาเรียน การเงิน ผลการเรียน บุคลากร และนักเรียนกลุ่มเสี่ยง ในหน้าเดียว */
class ExecutiveController extends Controller
{
    public const WEEKS = 8;

    public function index()
    {
        $term = Term::current();
        $rooms = Classroom::currentYear()->ordered()->get();

        return view('executive.index', [
            'term' => $term,
            'students' => Student::active()->whereIn('classroom_id', $rooms->pluck('id'))->count(),
            'weeks' => $this->attendanceByWeek(),
            'levels' => $this->attendanceByLevel($rooms),
            'finance' => $this->finance($term),
            'grades' => $this->grades($term),
            'staff' => $this->staff(),
            'care' => [
                'risk' => RiskScan::forClassrooms($rooms->pluck('id'))->count(),
                'cases' => CareCase::where('status', '!=', 'closed')->count(),
                'problem' => CareCase::where('status', '!=', 'closed')->where('level', 'problem')->count(),
            ],
        ]);
    }

    /** อัตรามาเรียนรายสัปดาห์ (มา + สาย ต่อจำนวนที่เช็คชื่อ) ย้อนหลัง */
    private function attendanceByWeek(): array
    {
        $start = today()->startOfWeek()->subWeeks(self::WEEKS - 1);
        $rows = Attendance::where('date', '>=', $start->toDateString())->get(['date', 'status'])
            ->groupBy(fn ($a) => $a->date->copy()->startOfWeek()->toDateString());

        $out = [];
        for ($w = 0; $w < self::WEEKS; $w++) {
            $monday = $start->copy()->addWeeks($w);
            $list = $rows[$monday->toDateString()] ?? collect();
            $came = $list->whereIn('status', ['present', 'late'])->count();
            $out[] = [
                'label' => $monday->format('j').'–'.thai_date($monday->copy()->addDays(4)),
                'total' => $list->count(),
                'absent' => $list->where('status', 'absent')->count(),
                'percent' => $list->count() ? round($came / $list->count() * 100, 1) : null,
            ];
        }

        return $out;
    }

    /** การมาเรียน 30 วันล่าสุดแยกระดับชั้น */
    private function attendanceByLevel($rooms): array
    {
        $rows = Attendance::whereIn('classroom_id', $rooms->pluck('id'))->where('date', '>=', today()->subDays(30)->toDateString())
            ->select('classroom_id', DB::raw("sum(case when status in ('present','late') then 1 else 0 end) as came"),
                DB::raw("sum(case when status = 'absent' then 1 else 0 end) as absent"), DB::raw('count(*) as total'))
            ->groupBy('classroom_id')->get()->keyBy('classroom_id');

        return $rooms->groupBy('level')->map(function ($list, $level) use ($rows) {
            $total = $list->sum(fn ($c) => $rows[$c->id]->total ?? 0);
            $came = $list->sum(fn ($c) => $rows[$c->id]->came ?? 0);

            return ['level' => $level, 'rooms' => $list->count(), 'absent' => $list->sum(fn ($c) => $rows[$c->id]->absent ?? 0),
                'percent' => $total ? round($came / $total * 100, 1) : null];
        })->values()->all();
    }

    private function finance(?Term $term): array
    {
        $invoices = Invoice::where('status', '!=', 'void')->when($term, fn ($q) => $q->where('term_id', $term->id));
        $billed = (float) (clone $invoices)->sum(DB::raw('total - discount'));
        $collected = (float) (clone $invoices)->sum('paid');
        $open = Invoice::whereIn('status', ['unpaid', 'partial']);

        return [
            'billed' => $billed,
            'collected' => $collected,
            'percent' => $billed > 0 ? round($collected / $billed * 100, 1) : null,
            'outstanding' => (float) (clone $open)->sum(DB::raw('total - discount - paid')),
            'overdue' => (clone $open)->whereDate('due_date', '<', today())->count(),
            'month' => (float) Payment::valid()->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
        ];
    }

    /** การกระจายผลการเรียนของภาคเรียน (รายวิชาที่ล็อกแล้วใช้ผลที่เก็บไว้ ที่ยังไม่ล็อกคำนวณจากคะแนนปัจจุบัน) */
    private function grades(?Term $term): array
    {
        $courses = $term ? Course::with(['assessments', 'subject'])->where('term_id', $term->id)->get() : collect();
        $all = $courses->flatMap(fn (Course $c) => collect($c->results())->pluck('grade'))->filter(fn ($g) => $g !== null);
        $numeric = $all->filter(fn ($g) => is_numeric($g));
        $order = array_merge(array_values(Grade::SCALE), ['ร', 'มส', 'ผ', 'มผ']);
        $counts = $all->countBy();

        return [
            'courses' => $courses->count(),
            'locked' => $courses->where('locked', true)->count(),
            'submitted' => $courses->filter->isSubmitted()->count(),
            'average' => $numeric->count() ? round($numeric->avg(fn ($g) => (float) $g), 2) : null,
            'failing' => $all->filter(fn ($g) => ! Grade::passed($g))->count(),
            'total' => $all->count(),
            'distribution' => collect($order)->filter(fn ($g) => isset($counts[$g]))->map(fn ($g) => ['grade' => $g, 'count' => $counts[$g]])->values()->all(),
        ];
    }

    private function staff(): array
    {
        $staff = User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->pluck('id');
        $today = StaffAttendance::whereIn('user_id', $staff)->where('date', today()->toDateString())->get();

        return [
            'total' => $staff->count(),
            'checked_in' => $today->whereNotNull('check_in')->count(),
            'late' => $today->where('status', 'late')->count(),
            'leave' => $today->whereIn('status', ['leave', 'duty'])->count(),
            'pending_leaves' => StaffLeave::where('status', 'pending')->count(),
        ];
    }
}
