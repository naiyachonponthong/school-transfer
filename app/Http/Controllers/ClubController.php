<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Classroom;
use App\Models\Club;
use App\Models\Course;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** ชุมนุม: ฝ่ายวิชาการตั้งชุมนุมและช่วงเปิดรับ · ครูที่ปรึกษาดูแลสมาชิก · นักเรียนเลือกชุมนุมเอง */
class ClubController extends Controller
{
    private function term(): Term
    {
        $term = Term::current();
        abort_unless($term, 422, 'ยังไม่ได้ตั้งภาคเรียนปัจจุบัน');

        return $term;
    }

    /** นักเรียนที่กำลังเรียนในปีการศึกษาของภาคเรียนนี้ */
    private function activeStudents(Term $term)
    {
        return Student::active()->whereHas('classroom', fn ($q) => $q->where('year', $term->year));
    }

    /* ---------------- ครู / ฝ่ายวิชาการ ---------------- */

    public function index(Request $request)
    {
        $term = $this->term();
        $clubs = Club::forTerm($term)->with('teacher')->withCount('students')->orderBy('name')->get();
        $memberIds = DB::table('club_members')->where('term_id', $term->id)->pluck('student_id');
        $without = $this->activeStudents($term)->whereNotIn('id', $memberIds)->with('classroom')->get()
            ->sortBy(fn ($s) => [$s->classroom->level_order, $s->classroom->room, $s->number])->values();

        return view('clubs.index', [
            'term' => $term,
            'clubs' => $clubs,
            'without' => $without,
            'total' => $this->activeStudents($term)->count(),
            'window' => Club::signupWindow(),
            'open' => Club::signupOpen(),
            'teachers' => User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'levels' => Classroom::where('year', $term->year)->ordered()->pluck('level')->unique()->values(),
            'canManage' => $request->user()->hasPermission('academics.manage'),
        ]);
    }

    private function rules(Term $term, ?Club $club = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('clubs')->where('term_id', $term->id)->ignore($club?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'teacher_id' => ['nullable', Rule::exists('users', 'id')->whereIn('role', ['admin', 'teacher'])],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'levels' => ['nullable', 'array'],
            'levels.*' => ['string', 'max:20'],
            'location' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function store(Request $request)
    {
        $term = $this->term();
        $data = $request->validate($this->rules($term), ['name.unique' => 'มีชุมนุมชื่อนี้ในภาคเรียนนี้แล้ว'], ['name' => 'ชื่อชุมนุม', 'capacity' => 'จำนวนรับ']);
        Club::create(['term_id' => $term->id, 'levels' => $data['levels'] ?? null] + $data);

        return back()->with('success', "เพิ่มชุมนุม {$data['name']} แล้ว");
    }

    public function update(Request $request, Club $club)
    {
        $data = $request->validate($this->rules($club->term, $club), ['name.unique' => 'มีชุมนุมชื่อนี้ในภาคเรียนนี้แล้ว'], ['name' => 'ชื่อชุมนุม', 'capacity' => 'จำนวนรับ']);
        $club->update(['levels' => $data['levels'] ?? null, 'capacity' => $data['capacity'] ?? null] + $data);
        $club->course?->update(['title' => $club->name, 'teacher_id' => $club->teacher_id]);

        return back()->with('success', 'บันทึกชุมนุมแล้ว');
    }

    public function destroy(Club $club)
    {
        abort_if($club->course_id, 422, 'ชุมนุมนี้มีรายวิชาสำหรับประเมินผลแล้ว ลบไม่ได้');
        abort_if($club->students()->exists(), 422, 'ชุมนุมนี้มีสมาชิกอยู่ ย้ายสมาชิกออกก่อนจึงจะลบได้');
        Audit::log('course.delete', $club, "ลบชุมนุม {$club->name}");
        $club->delete();

        return redirect()->route('clubs.index')->with('success', 'ลบชุมนุมแล้ว');
    }

    /** ช่วงวันที่ให้นักเรียนเลือกชุมนุมเอง */
    public function window(Request $request)
    {
        $data = $request->validate([
            'club_signup_from' => ['nullable', 'date', 'required_with:club_signup_until'],
            'club_signup_until' => ['nullable', 'date', 'after:club_signup_from', 'required_with:club_signup_from'],
        ], [], ['club_signup_from' => 'วันเปิดรับ', 'club_signup_until' => 'วันปิดรับ']);
        Settings::set(['club_signup_from' => $data['club_signup_from'] ?? '', 'club_signup_until' => $data['club_signup_until'] ?? '']);

        return back()->with('success', filled($data['club_signup_from'] ?? null) ? 'บันทึกช่วงเปิดรับสมัครชุมนุมแล้ว' : 'ปิดการเลือกชุมนุมของนักเรียนแล้ว');
    }

    public function show(Request $request, Club $club)
    {
        $club->load(['teacher', 'course.classroom', 'students.classroom', 'term']);
        $taken = DB::table('club_members')->where('term_id', $club->term_id)->pluck('student_id');

        return view('clubs.show', [
            'club' => $club,
            'members' => $club->students->sortBy(fn ($s) => [$s->classroom?->level_order, $s->classroom?->room, $s->number])->values(),
            'candidates' => $this->activeStudents($club->term)->whereNotIn('id', $taken)->with('classroom')->get()
                ->filter(fn ($s) => $club->accepts($s->classroom->level))
                ->sortBy(fn ($s) => [$s->classroom->level_order, $s->classroom->room, $s->number])->values(),
            'canManage' => $club->canBeManagedBy($request->user()),
            'canAdmin' => $request->user()->hasPermission('academics.manage'),
        ]);
    }

    /** ครูเพิ่มสมาชิกเอง (เกินจำนวนรับได้ เพราะเป็นการตัดสินใจของครู) */
    public function addMember(Request $request, Club $club)
    {
        abort_unless($club->canBeManagedBy($request->user()), 403);
        $data = $request->validate(['student_id' => ['required', 'integer']], [], ['student_id' => 'นักเรียน']);
        $student = $this->activeStudents($club->term)->findOrFail($data['student_id']);
        $this->enroll($club, $student, $request->user(), enforceLimits: false);

        return back()->with('success', "เพิ่ม {$student->fullName()} เข้าชุมนุมแล้ว");
    }

    public function removeMember(Request $request, Club $club, Student $student)
    {
        abort_unless($club->canBeManagedBy($request->user()), 403);
        $club->students()->detach($student->id);
        $club->syncCourseMembers();

        return back()->with('success', "นำ {$student->fullName()} ออกจากชุมนุมแล้ว");
    }

    /** สร้างรายวิชาของชุมนุม (เช็คชื่อรายคาบ + ประเมินผล ผ/มผ) จากรายชื่อสมาชิกปัจจุบัน */
    public function createCourse(Club $club)
    {
        abort_if($club->course_id, 422, 'ชุมนุมนี้มีรายวิชาแล้ว');
        $club->load('students.classroom');
        if ($club->students->isEmpty()) {
            throw ValidationException::withMessages(['course' => 'ยังไม่มีสมาชิก สร้างรายวิชาเมื่อมีสมาชิกอย่างน้อย 1 คน']);
        }

        // รายวิชาต้องผูกกับห้องและวิชาในหลักสูตร: ใช้ห้องของสมาชิกส่วนใหญ่ และวิชาชุมนุมที่ห้องนั้นเปิดอยู่ (หรือวิชาชุมนุมวิชาแรก)
        $classroom = $club->students->groupBy('classroom_id')->sortByDesc->count()->first()->first()->classroom;
        $subject = Subject::where('activity_kind', 'club')
            ->whereHas('courses', fn ($q) => $q->where('classroom_id', $classroom->id)->where('term_id', $club->term_id))->first()
            ?? Subject::where('activity_kind', 'club')->orderBy('code')->first();
        if (! $subject) {
            throw ValidationException::withMessages(['course' => 'ยังไม่มีวิชาประเภทกิจกรรม "ชุมนุม/ชมรม" ในหลักสูตร เพิ่มที่หน้ารายวิชาในหลักสูตรก่อน']);
        }

        DB::transaction(function () use ($club, $classroom, $subject) {
            $course = Course::create([
                'term_id' => $club->term_id, 'classroom_id' => $classroom->id, 'subject_id' => $subject->id,
                'teacher_id' => $club->teacher_id, 'variant' => 'club-'.$club->id, 'title' => $club->name,
            ]);
            Assessment::create(['course_id' => $course->id, 'name' => 'ผลการประเมิน', 'max_score' => 100, 'sort' => 1]);
            $club->update(['course_id' => $course->id]);
            $club->setRelation('course', $course)->syncCourseMembers();
        });

        return back()->with('success', 'สร้างรายวิชาของชุมนุมแล้ว ครูที่ปรึกษาเช็คชื่อรายคาบและประเมินผลได้ที่เมนูคะแนน');
    }

    /* ---------------- นักเรียน ---------------- */

    public function studentIndex(Request $request)
    {
        $me = $request->user()->studentProfile?->load('classroom');
        abort_unless($me, 403, 'บัญชีนี้ยังไม่ได้ผูกกับข้อมูลนักเรียน กรุณาติดต่อครูประจำชั้น');
        $term = $this->term();
        $clubs = Club::forTerm($term)->with('teacher')->withCount('students')->orderBy('name')->get()
            ->filter(fn ($c) => $c->accepts($me->classroom?->level))->values();

        return view('clubs.student', [
            'me' => $me,
            'term' => $term,
            'clubs' => $clubs,
            'mine' => Club::forTerm($term)->whereHas('students', fn ($q) => $q->where('students.id', $me->id))->first(),
            'open' => Club::signupOpen(),
            'window' => Club::signupWindow(),
        ]);
    }

    public function join(Request $request, Club $club)
    {
        $me = $request->user()->studentProfile?->load('classroom');
        abort_unless($me, 403);
        abort_unless($club->term_id === Term::current()?->id, 404);
        if (! Club::signupOpen()) {
            throw ValidationException::withMessages(['club' => 'ยังไม่อยู่ในช่วงเปิดรับสมัครชุมนุม']);
        }
        $this->enroll($club, $me, $request->user(), enforceLimits: true);

        return back()->with('success', "เลือก {$club->name} แล้ว");
    }

    public function leave(Request $request)
    {
        $me = $request->user()->studentProfile;
        abort_unless($me, 403);
        if (! Club::signupOpen()) {
            throw ValidationException::withMessages(['club' => 'ปิดรับสมัครแล้ว ถ้าต้องการเปลี่ยนชุมนุมกรุณาติดต่อครูที่ปรึกษา']);
        }
        $club = Club::forTerm(Term::current())->whereHas('students', fn ($q) => $q->where('students.id', $me->id))->first();
        if ($club) {
            $club->students()->detach($me->id);
            $club->syncCourseMembers();
        }

        return back()->with('success', 'ยกเลิกการเลือกชุมนุมแล้ว เลือกชุมนุมใหม่ได้');
    }

    /**
     * ลงชื่อเข้าชุมนุม: ล็อกแถวของชุมนุมก่อนนับจำนวน กันสองคนกดที่นั่งสุดท้ายพร้อมกัน
     * และมี unique (ภาคเรียน, นักเรียน) กันคนเดียวอยู่สองชุมนุม
     */
    private function enroll(Club $club, Student $student, User $by, bool $enforceLimits): void
    {
        try {
            DB::transaction(function () use ($club, $student, $by, $enforceLimits) {
                $locked = Club::whereKey($club->id)->lockForUpdate()->first();
                if (DB::table('club_members')->where('term_id', $locked->term_id)->where('student_id', $student->id)->exists()) {
                    throw ValidationException::withMessages(['club' => $enforceLimits ? 'เลือกชุมนุมไว้แล้ว ยกเลิกชุมนุมเดิมก่อนจึงจะเลือกใหม่ได้' : 'นักเรียนคนนี้อยู่ชุมนุมอื่นแล้ว']);
                }
                if ($enforceLimits) {
                    if (! $locked->accepts($student->classroom?->level)) {
                        throw ValidationException::withMessages(['club' => 'ชุมนุมนี้ไม่รับระดับชั้นของคุณ']);
                    }
                    if ($locked->isFull()) {
                        throw ValidationException::withMessages(['club' => 'ชุมนุมนี้เต็มแล้ว กรุณาเลือกชุมนุมอื่น']);
                    }
                }
                $locked->students()->attach($student->id, ['term_id' => $locked->term_id, 'added_by' => $by->id]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['club' => 'เลือกชุมนุมไว้แล้ว']);
        }
        $club->syncCourseMembers();
    }
}
