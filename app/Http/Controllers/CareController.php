<?php

namespace App\Http\Controllers;

use App\Models\CareCase;
use App\Models\Classroom;
use App\Models\HomeVisit;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\Audit;
use App\Support\HomeVisitForm;
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

    /** แบบบันทึกการเยี่ยมบ้าน 4 หน้า A4 ตามแบบ สพฐ. (ยังไม่บันทึก = แบบเปล่าไว้กรอกด้วยมือ) */
    public function printVisit(Request $request, Student $student)
    {
        $this->authorizeStudent($request, $student);
        $term = Term::current();
        abort_unless($term, 422, 'ยังไม่ได้ตั้งภาคเรียนปัจจุบัน');

        return view('care.visit-print', [
            'student' => $student->load('classroom'),
            'term' => $term,
            'visit' => HomeVisit::with('visitor')->firstOrNew(['student_id' => $student->id, 'term_id' => $term->id]),
        ]);
    }

    public function saveVisit(Request $request, Student $student)
    {
        $this->authorizeStudent($request, $student);
        $term = Term::current();
        abort_unless($term, 422, 'ยังไม่ได้ตั้งภาคเรียนปัจจุบัน');
        $data = $request->validate([
            'visited_on' => ['required', 'date', 'before_or_equal:today'],
            'form' => ['array'],
            'note' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'max:6144'],
            'photo_inside' => ['nullable', 'image', 'max:6144'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'sign_guardian' => ['nullable', 'string', 'max:400000'],
            'sign_visitor' => ['nullable', 'string', 'max:400000'],
        ], [], ['visited_on' => 'วันที่เยี่ยม', 'photo' => 'รูปที่ 1', 'photo_inside' => 'รูปที่ 2', 'sign_guardian' => 'ลายเซ็นผู้ปกครอง', 'sign_visitor' => 'ลายเซ็นผู้เยี่ยม']);

        // คำตอบตามแบบ 4 หน้า: เก็บเฉพาะช่องที่นิยามไว้ แล้วสรุปเป็นช่องที่หน้ารายห้อง/กรณีช่วยเหลือใช้
        $form = HomeVisitForm::normalize($data['form'] ?? []);
        $visit = HomeVisit::firstOrNew(['student_id' => $student->id, 'term_id' => $term->id]);
        $visit->fill([
            'visited_on' => $data['visited_on'],
            'note' => $data['note'] ?? null,
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
            'form' => $form,
            'guardian_met' => isset($form['informant']) ? HomeVisitForm::SINGLE['informant'][$form['informant']] : (trim(($form['guardian_first'] ?? '').' '.($form['guardian_last'] ?? '')) ?: null),
            'housing' => ['own' => 'own', 'rent' => 'rent', 'with_others' => 'relative'][$form['housing_type'] ?? ''] ?? null,
            'family_status' => in_array('parents_separated', $form['safety'] ?? [], true) ? 'separated' : null,
            'risks' => HomeVisitForm::risks($form),
            'visitor_id' => $request->user()->id,
        ]);
        foreach (['photo', 'photo_inside'] as $field) {
            if ($request->hasFile($field)) {
                $visit->{$field} = $request->file($field)->store('home-visits', 'local');
            }
        }
        // ลายเซ็นที่เซ็นบนหน้าจอ ส่งมาเป็น data URL ของ PNG ("clear" = ลบลายเซ็นเดิม)
        foreach (['sign_guardian', 'sign_visitor'] as $field) {
            $value = $data[$field] ?? null;
            if ($value === 'clear') {
                $visit->{$field} = null;
            } elseif ($value) {
                $png = str_starts_with($value, 'data:image/png;base64,') ? base64_decode(substr($value, 22), true) : false;
                $size = $png ? @getimagesizefromstring($png) : false;
                if (! $size || $size[2] !== IMAGETYPE_PNG || $size[0] > 1600 || $size[1] > 800) {
                    throw \Illuminate\Validation\ValidationException::withMessages([$field => 'ลายเซ็นไม่ถูกต้อง กรุณาเซ็นใหม่']);
                }
                $path = 'home-visits/sign-'.\Illuminate\Support\Str::random(40).'.png';
                \Illuminate\Support\Facades\Storage::disk('local')->put($path, $png);
                $visit->{$field} = $path;
            }
        }
        $visit->save();

        return redirect()->route('care.visits', ['classroom' => $student->classroom_id])->with('success', "บันทึกการเยี่ยมบ้าน {$student->fullName()} แล้ว");
    }
}
