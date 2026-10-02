<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\HealthMeasurement;
use App\Models\HealthVisit;
use App\Models\Student;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** ห้องพยาบาล: บันทึกการมารับบริการ + ชั่งน้ำหนักวัดส่วนสูงทั้งห้อง */
class HealthController extends Controller
{
    public function index(Request $request)
    {
        $visits = HealthVisit::with(['student.classroom', 'recorder'])
            ->when($request->query('q'), fn ($q, $t) => $q->whereHas('student', fn ($s) => $s->search($t)))
            ->latest('visited_at')->paginate(25)->withQueryString();

        $today = HealthVisit::whereDate('visited_at', today())->get();

        return view('health.index', [
            'visits' => $visits,
            'todayCount' => $today->count(),
            'sentHome' => $today->where('action', 'sent_home')->count(),
            'monthCount' => HealthVisit::where('visited_at', '>=', now()->startOfMonth())->count(),
            'topSymptoms' => HealthVisit::where('visited_at', '>=', now()->subDays(30))
                ->select('symptom', DB::raw('count(*) as c'))->groupBy('symptom')->orderByDesc('c')->limit(5)->pluck('c', 'symptom'),
            'students' => Student::active()->with('classroom')->orderBy('student_code')->get(['id', 'student_code', 'prefix', 'first_name', 'last_name', 'nickname', 'classroom_id']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_code' => ['required', 'string'],
            'symptom' => ['required', 'string', 'max:255'],
            'temperature' => ['nullable', 'numeric', 'between:30,45'],
            'treatment' => ['nullable', 'string', 'max:255'],
            'medicine' => ['nullable', 'string', 'max:255'],
            'action' => ['required', Rule::in(array_keys(HealthVisit::ACTIONS))],
        ]);
        $code = trim(explode(' ', trim($data['student_code']))[0]);
        $student = Student::where('student_code', $code)->orWhere('qr_token', $code)->first();
        if (! $student) {
            return back()->withInput()->withErrors(['student_code' => "ไม่พบนักเรียนรหัส {$code}"]);
        }

        $visit = HealthVisit::create([
            'student_id' => $student->id,
            'visited_at' => now(),
            'recorded_by' => $request->user()->id,
        ] + collect($data)->except('student_code')->all());

        $nick = 'น้อง'.($student->nickname ?: $student->first_name);
        $msg = "🩺 {$nick} มาห้องพยาบาล เวลา ".now()->format('H:i')." น.\nอาการ: {$visit->symptom}"
            .($visit->temperature ? " (อุณหภูมิ {$visit->temperature}°C)" : '')
            .($visit->treatment ? "\nการดูแล: {$visit->treatment}" : '')
            ."\nผล: {$visit->actionLabel()}"
            .(in_array($visit->action, ['sent_home', 'hospital'], true) ? "\nกรุณาติดต่อห้องพยาบาล ".(school('school_phone') ?: '') : '');
        Notifier::parents($student, $msg);

        return back()->with('success', "บันทึกการมาห้องพยาบาลของ {$student->fullName()} แล้ว และแจ้งผู้ปกครองแล้ว");
    }

    public function update(Request $request, HealthVisit $visit)
    {
        $visit->update($request->validate(['action' => ['required', Rule::in(array_keys(HealthVisit::ACTIONS))]]));

        return back()->with('success', 'อัปเดตสถานะแล้ว');
    }

    /** กรอกน้ำหนัก/ส่วนสูงทั้งห้องในหน้าเดียว */
    public function measure(Request $request)
    {
        $classrooms = Classroom::currentYear()->ordered()->get();
        $classroom = $classrooms->firstWhere('id', (int) $request->query('classroom')) ?? $classrooms->first();
        $students = $classroom ? $classroom->students()->with(['measurements' => fn ($q) => $q->limit(2)])->get() : collect();

        return view('health.measure', compact('classrooms', 'classroom', 'students'));
    }

    public function saveMeasure(Request $request)
    {
        $data = $request->validate([
            'measured_on' => ['required', 'date'],
            'rows' => ['array'],
            'rows.*.weight' => ['nullable', 'numeric', 'between:5,200'],
            'rows.*.height' => ['nullable', 'numeric', 'between:50,230'],
        ]);
        $n = 0;
        foreach ($data['rows'] ?? [] as $studentId => $row) {
            if (blank($row['weight'] ?? null) && blank($row['height'] ?? null)) {
                continue;
            }
            HealthMeasurement::updateOrCreate(
                ['student_id' => $studentId, 'measured_on' => $data['measured_on']],
                ['weight' => $row['weight'] ?: null, 'height' => $row['height'] ?: null, 'recorded_by' => $request->user()->id]
            );
            $n++;
        }

        return back()->with('success', "บันทึกน้ำหนัก/ส่วนสูง {$n} คนแล้ว");
    }
}
