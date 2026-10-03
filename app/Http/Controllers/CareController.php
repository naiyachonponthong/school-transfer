<?php

namespace App\Http\Controllers;

use App\Models\CareCase;
use App\Models\Classroom;
use App\Models\HomeVisit;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\Audit;
use App\Support\RiskScan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** ระบบดูแลช่วยเหลือนักเรียน: สัญญาณเตือนกลุ่มเสี่ยง กรณีช่วยเหลือ และเยี่ยมบ้าน */
class CareController extends Controller
{
    /** ห้องที่ผู้ใช้ดูแลได้: ผู้มีสิทธิ์ care.manage เห็นทุกห้อง ครูเห็นห้องที่ตัวเองเป็นครูประจำชั้น */
    private function classroomsFor(User $user)
    {
        return $user->hasPermission('care.manage') ? Classroom::currentYear()->ordered()->get() : $user->myClassrooms();
    }

    private function authorizeStudent(Request $request, Student $student): void
    {
        abort_unless($this->classroomsFor($request->user())->contains('id', $student->classroom_id), 403, 'ดูแลได้เฉพาะนักเรียนในห้องที่คุณเป็นครูประจำชั้น');
    }

    /* ---------------- ภาพรวม + กรณีช่วยเหลือ ---------------- */

    public function index(Request $request)
    {
        $user = $request->user();
        $classrooms = $this->classroomsFor($user);
        $classroom = $classrooms->firstWhere('id', (int) $request->query('classroom'));
        $status = $request->query('status', 'active');

        $cases = CareCase::visibleTo($user)->with(['student.classroom', 'owner'])->withCount('actions')
            ->when($status === 'active', fn ($q) => $q->where('status', '!=', 'closed'))
            ->when($status === 'closed', fn ($q) => $q->where('status', 'closed'))
            ->when($classroom, fn ($q) => $q->whereHas('student', fn ($s) => $s->where('classroom_id', $classroom->id)))
            ->latest()->get();

        $risks = RiskScan::forClassrooms($classroom ? [$classroom->id] : $classrooms->pluck('id'));
        $openByStudent = CareCase::where('status', '!=', 'closed')->whereIn('student_id', $risks->pluck('student.id'))->pluck('student_id')->flip();

        return view('care.index', compact('classrooms', 'classroom', 'status', 'cases', 'risks', 'openByStudent'));
    }

    public function create(Request $request)
    {
        $student = Student::with('classroom')->findOrFail($request->query('student'));
        $this->authorizeStudent($request, $student);

        return view('care.create', [
            'student' => $student,
            'suggested' => $request->query('title'),
            'staff' => User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'category' => ['required', Rule::in(array_keys(CareCase::CATEGORIES))],
            'level' => ['required', Rule::in(array_keys(CareCase::LEVELS))],
            'title' => ['required', 'string', 'max:255'],
            'detail' => ['nullable', 'string', 'max:5000'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->whereIn('role', ['admin', 'teacher'])],
        ], [], ['title' => 'เรื่อง']);
        $this->authorizeStudent($request, Student::findOrFail($data['student_id']));

        $case = CareCase::create($data + ['opened_by' => $request->user()->id, 'owner_id' => $data['owner_id'] ?? $request->user()->id]);

        Audit::log('student.care_open', $case, "เปิดกรณีดูแลช่วยเหลือ #{$case->id} ({$case->categoryLabel()}) ของ {$case->student->fullName()}");

        return redirect()->route('care.show', $case)->with('success', 'เปิดกรณีดูแลช่วยเหลือแล้ว');
    }

    public function show(Request $request, CareCase $case)
    {
        abort_unless($case->canBeAccessedBy($request->user()), 403);
        // ข้อมูลอ่อนไหว: บันทึกว่าใครเปิดดู
        // (เก็บแค่ว่าใครเปิดกรณีไหน ไม่คัดลอกรายละเอียดของกรณีลงประวัติ)
        Audit::log('student.care_view', $case, "เปิดดูกรณีดูแลช่วยเหลือ #{$case->id} ของ {$case->student->fullName()}");

        return view('care.show', [
            'case' => $case->load(['student.classroom', 'owner', 'actions.user']),
            'visit' => HomeVisit::where('student_id', $case->student_id)->latest('visited_on')->first(),
        ]);
    }

    public function update(Request $request, CareCase $case)
    {
        abort_unless($case->canBeAccessedBy($request->user()), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(CareCase::STATUSES))],
            'level' => ['required', Rule::in(array_keys(CareCase::LEVELS))],
        ]);
        $case->update($data + ['closed_at' => $data['status'] === 'closed' ? ($case->closed_at ?? now()) : null]);

        return back()->with('success', 'บันทึกสถานะแล้ว');
    }

    public function addAction(Request $request, CareCase $case)
    {
        abort_unless($case->canBeAccessedBy($request->user()), 403);
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'action' => ['required', 'string', 'max:5000'],
            'result' => ['nullable', 'string', 'max:5000'],
            'follow_up_on' => ['nullable', 'date', 'after_or_equal:date'],
        ], [], ['action' => 'การช่วยเหลือ']);
        $case->actions()->create($data + ['user_id' => $request->user()->id]);

        return back()->with('success', 'บันทึกการช่วยเหลือแล้ว');
    }

    /* ---------------- เยี่ยมบ้าน ---------------- */

    public function visits(Request $request)
    {
        $classrooms = $this->classroomsFor($request->user());
        $classroom = $classrooms->firstWhere('id', (int) $request->query('classroom')) ?? $classrooms->first();
        $term = Term::current();
        $students = $classroom ? $classroom->students()->get() : collect();
        $visits = $term ? HomeVisit::with('visitor')->where('term_id', $term->id)->whereIn('student_id', $students->pluck('id'))->get()->keyBy('student_id') : collect();

        return view('care.visits', [
            'classrooms' => $classrooms, 'classroom' => $classroom, 'term' => $term, 'students' => $students, 'visits' => $visits,
            'riskCounts' => $visits->flatMap(fn ($v) => $v->risks ?? [])->countBy(),
        ]);
    }

    public function visitForm(Request $request, Student $student)
    {
        $this->authorizeStudent($request, $student);
        $term = Term::current();
        abort_unless($term, 422, 'ยังไม่ได้ตั้งภาคเรียนปัจจุบัน');

        return view('care.visit-form', [
            'student' => $student->load('classroom', 'guardians'),
            'term' => $term,
            'visit' => HomeVisit::firstOrNew(['student_id' => $student->id, 'term_id' => $term->id], ['visited_on' => today()]),
        ]);
    }

    public function saveVisit(Request $request, Student $student)
    {
        $this->authorizeStudent($request, $student);
        $term = Term::current();
        abort_unless($term, 422, 'ยังไม่ได้ตั้งภาคเรียนปัจจุบัน');
        $data = $request->validate([
            'visited_on' => ['required', 'date', 'before_or_equal:today'],
            'guardian_met' => ['nullable', 'string', 'max:255'],
            'housing' => ['nullable', Rule::in(array_keys(HomeVisit::HOUSING))],
            'family_status' => ['nullable', Rule::in(array_keys(HomeVisit::FAMILY))],
            'risks' => ['array'],
            'risks.*' => [Rule::in(array_keys(HomeVisit::RISKS))],
            'note' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'max:6144'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        $visit = HomeVisit::firstOrNew(['student_id' => $student->id, 'term_id' => $term->id]);
        unset($data['photo']);
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('home-visits', 'local');
        }
        $visit->fill($data + ['risks' => $data['risks'] ?? [], 'visitor_id' => $request->user()->id])->save();

        return redirect()->route('care.visits', ['classroom' => $student->classroom_id])->with('success', "บันทึกการเยี่ยมบ้าน {$student->fullName()} แล้ว");
    }
}
