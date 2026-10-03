<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BehaviorRule;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\DocumentIssue;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\Audit;
use App\Support\Grade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    /** ข้อมูลเข้าระบบของบัญชีผู้ปกครองที่เพิ่งสร้าง (แสดงให้ครูแจ้งผู้ปกครอง) */
    private ?array $newCredential = null;

    public function index(Request $request)
    {
        $classrooms = Classroom::currentYear()->ordered()->get();
        $status = $request->query('status', 'active');

        $students = Student::with('classroom')
            ->search($request->query('q'))
            ->when($request->query('classroom'), fn ($q, $id) => $q->where('classroom_id', $id))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->leftJoin('classrooms', 'classrooms.id', '=', 'students.classroom_id')
            ->orderBy('classrooms.level_order')->orderBy('classrooms.room')->orderBy('students.number')->orderBy('students.student_code')
            ->select('students.*')
            ->paginate(50)->withQueryString();

        return view('students.index', compact('students', 'classrooms', 'status'));
    }

    public function create(Request $request)
    {
        $student = new Student(['classroom_id' => $request->query('classroom'), 'status' => 'active']);

        return view('students.form', [
            'student' => $student,
            'classrooms' => Classroom::currentYear()->ordered()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['photo'] = $this->storePhoto($request);
        $student = Student::create($data);

        if ($request->filled('guardian_phone')) {
            $this->attachGuardian($student, $request);
        }

        $redirect = $request->boolean('another')
            ? redirect()->route('students.create', ['classroom' => $student->classroom_id])->with('success', "เพิ่ม {$student->fullName()} แล้ว เพิ่มคนถัดไปได้เลย")
            : redirect()->route('students.show', $student)->with('success', 'เพิ่มนักเรียนแล้ว');

        return $redirect->with('credential', $this->newCredential);
    }

    public function show(Request $request, Student $student)
    {
        $student->load(['classroom.homeroomTeacher', 'guardians', 'behaviorRecords.recorder', 'leaveRequests', 'invoices', 'measurements', 'healthVisits', 'bookLoans.book']);

        $term = Term::find((int) $request->query('term', Term::current()?->id));
        $terms = Term::orderByDesc('year')->orderByDesc('term')->get();

        // สถิติเช็คชื่อในภาคเรียน
        $attQuery = $student->attendances();
        if ($term?->start_date && $term?->end_date) {
            $attQuery->whereBetween('date', [$term->start_date->toDateString(), $term->end_date->toDateString()]);
        }
        $attendance = $attQuery->orderByDesc('date')->get();
        $attCounts = $attendance->countBy('status');

        $grades = self::gradesFor($student, $term);

        return view('students.show', [
            'student' => $student,
            'term' => $term,
            'terms' => $terms,
            'attendance' => $attendance,
            'attCounts' => $attCounts,
            'grades' => $grades,
            'gpa' => Grade::gpa($grades->map(fn ($g) => ['grade' => $g['grade'], 'credit' => (float) $g['course']->subject->credit])),
            'rules' => BehaviorRule::where('is_active', true)->orderByDesc('points')->get(),
        ]);
    }

    /** ผลการเรียนทุกวิชาของนักเรียนในภาคเรียนที่ระบุ */
    public static function gradesFor(Student $student, ?Term $term)
    {
        // ห้องของปีการศึกษานั้น — ดูภาคเรียนเก่าหลังเลื่อนชั้นแล้วต้องได้รายวิชาของห้องเดิม
        $classroomId = $term ? $student->classroomIdForTerm($term) : null;
        if (! $classroomId) {
            return collect();
        }

        return Course::with(['subject', 'teacher', 'assessments'])
            ->where('term_id', $term->id)
            ->forStudent($student, $classroomId)
            ->get()
            ->sortBy(fn ($c) => [$c->subject->typeOrder(), $c->subject->code])
            ->map(function (Course $course) use ($student) {
                $r = $course->results()[$student->id] ?? null;

                return [
                    'course' => $course,
                    'total' => $r['total'] ?? null,
                    'max' => $course->maxTotal(),
                    'grade' => $r['grade'] ?? null,
                    'original' => $r['original'] ?? null,
                ];
            })->values();
    }

    public function edit(Student $student)
    {
        return view('students.form', [
            'student' => $student,
            'classrooms' => Classroom::currentYear()->ordered()->get(),
        ]);
    }

    public function update(Request $request, Student $student)
    {
        $data = $this->validated($request, $student);
        unset($data['photo']);
        if ($photo = $this->storePhoto($request)) {
            if ($student->photo) {
                Storage::disk('public')->delete($student->photo);
            }
            $data['photo'] = $photo;
        }
        $student->fill($data);
        if ($diff = Audit::diff($student)) {
            Audit::log('student.update', $student, "แก้ข้อมูลนักเรียน {$student->student_code} {$student->fullName()} (".implode(', ', array_map([AuditLog::class, 'fieldLabel'], array_keys($diff))).')', $diff);
        }
        $student->save();

        return redirect()->route('students.show', $student)->with('success', 'บันทึกข้อมูลแล้ว');
    }

    public function destroy(Student $student)
    {
        abort_unless(request()->user()->isAdmin(), 403);
        // กันประวัติการเรียน/การเงินหาย: นักเรียนที่มีข้อมูลแล้วให้เปลี่ยนสถานะแทนการลบ
        $records = array_filter([
            'คะแนน' => $student->scores()->exists() || CourseResult::where('student_id', $student->id)->exists(),
            'การมาเรียน' => $student->attendances()->exists() || $student->periodAttendances()->exists(),
            'ใบแจ้งหนี้/ใบเสร็จ' => $student->invoices()->exists(),
            'เอกสาร ปพ. ที่ออกแล้ว' => DocumentIssue::where('student_id', $student->id)->exists(),
        ]);
        if ($records) {
            return back()->withErrors(['student' => 'ลบไม่ได้ เพราะมีข้อมูล '.implode(', ', array_keys($records)).' แล้ว — ถ้านักเรียนย้ายหรือลาออก ให้แก้ไขข้อมูลแล้วเปลี่ยนสถานะเป็น "ย้ายโรงเรียน" หรือ "พ้นสภาพ" แทน']);
        }
        $name = $student->fullName();
        Audit::log('student.delete', $student, "ลบนักเรียน {$student->student_code} {$name}");
        $student->delete();

        return redirect()->route('students.index')->with('success', "ลบ {$name} แล้ว");
    }

    public function addGuardian(Request $request, Student $student)
    {
        $request->validate([
            'guardian_name' => ['required', 'string', 'max:255'],
            'guardian_phone' => ['required', 'string', 'max:20'],
            'relation' => ['nullable', 'string', 'max:30'],
        ], [], ['guardian_name' => 'ชื่อผู้ปกครอง', 'guardian_phone' => 'เบอร์โทร']);

        $user = $this->attachGuardian($student, $request);

        return back()->with('success', "เพิ่มผู้ปกครอง {$user->name} แล้ว")->with('credential', $this->newCredential);
    }

    public function removeGuardian(Student $student, User $user)
    {
        $student->guardians()->detach($user->id);

        return back()->with('success', 'นำผู้ปกครองออกแล้ว');
    }

    /** สร้าง/ผูกบัญชีผู้ปกครอง (เบอร์ซ้ำ = ใช้บัญชีเดิม) รหัสผ่านเริ่มต้น = 6 หลักท้ายเบอร์โทร */
    private function attachGuardian(Student $student, Request $request): User
    {
        $phone = preg_replace('/\D/', '', (string) $request->input('guardian_phone'));
        $user = User::where('role', 'parent')->where('phone', $phone)->first();

        if (! $user) {
            $password = strlen($phone) >= 6 ? substr($phone, -6) : Str::lower(Str::random(6));
            $username = User::where('username', $phone)->exists() || $phone === '' ? 'p'.Str::lower(Str::random(7)) : $phone;
            $user = User::create([
                'name' => $request->input('guardian_name') ?: 'ผู้ปกครอง '.$student->first_name,
                'username' => $username,
                'phone' => $phone ?: null,
                'role' => 'parent',
                'password' => Hash::make($password),
                'must_change_password' => true,
            ]);
            $this->newCredential = ['username' => $username, 'password' => $password];
        }
        $student->guardians()->syncWithoutDetaching([$user->id => ['relation' => $request->input('relation')]]);

        return $user;
    }

    private function validated(Request $request, ?Student $student = null): array
    {
        return $request->validate([
            'student_code' => ['required', 'string', 'max:20', Rule::unique('students')->ignore($student?->id)],
            'citizen_id' => ['nullable', 'digits:13'],
            'prefix' => ['nullable', 'string', 'max:20'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'in:M,F'],
            'birthdate' => ['nullable', 'date'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
            'number' => ['nullable', 'integer', 'min:1', 'max:999'],
            'status' => ['required', Rule::in(array_keys(Student::STATUSES))],
            'blood_type' => ['nullable', 'string', 'max:5'],
            'medical_note' => ['nullable', 'string', 'max:1000'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'photo' => ['nullable', 'image', 'max:4096'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'ethnicity' => ['nullable', 'string', 'max:50'],
            'religion' => ['nullable', 'string', 'max:50'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'admitted_on' => ['nullable', 'date'],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'previous_school_province' => ['nullable', 'string', 'max:100'],
            'previous_level' => ['nullable', 'string', 'max:20'],
            'left_on' => ['nullable', 'date', 'after_or_equal:admitted_on'],
            'leave_reason' => ['nullable', 'string', 'max:255'],
        ], [], [
            'left_on' => 'วันที่จบ/ออก',
            'student_code' => 'รหัสนักเรียน',
            'first_name' => 'ชื่อ',
            'last_name' => 'นามสกุล',
            'citizen_id' => 'เลขประจำตัวประชาชน',
        ]);
    }

    private function storePhoto(Request $request): ?string
    {
        return $request->hasFile('photo') ? $request->file('photo')->store('students', 'public') : null;
    }
}
