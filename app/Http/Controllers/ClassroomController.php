<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Score;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClassroomController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->query('year', Term::current()?->year ?? now()->year + 543);
        $classrooms = Classroom::where('year', $year)->ordered()
            ->with('homeroomTeacher', 'coTeacher')
            ->withCount([
                'students',
                'students as boys_count' => fn ($q) => $q->where('gender', 'M'),
                'students as girls_count' => fn ($q) => $q->where('gender', 'F'),
            ])->get();

        return view('classrooms.index', [
            'year' => $year,
            'years' => Classroom::distinct()->orderByDesc('year')->pluck('year')->push($year)->unique()->sortDesc()->values(),
            'classrooms' => $classrooms,
            'teachers' => User::whereIn('role', ['teacher', 'admin'])->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2500', 'max:2700'],
            'level' => ['required', 'string', 'max:20'],
            'rooms' => ['required', 'integer', 'min:1', 'max:30'],
            'homeroom_teacher_id' => ['nullable', 'exists:users,id'],
        ]);

        // สร้างห้อง 1..N ของชั้นนั้นทีเดียว ข้ามห้องที่มีอยู่แล้ว
        $created = 0;
        for ($r = 1; $r <= $data['rooms']; $r++) {
            $c = Classroom::firstOrCreate(
                ['year' => $data['year'], 'level' => $data['level'], 'room' => $r],
                ['homeroom_teacher_id' => $data['rooms'] == 1 ? ($data['homeroom_teacher_id'] ?? null) : null]
            );
            $created += $c->wasRecentlyCreated ? 1 : 0;
        }

        return back()->with('success', "เพิ่มห้องเรียน {$data['level']} จำนวน {$created} ห้อง");
    }

    public function update(Request $request, Classroom $classroom)
    {
        $data = $request->validate([
            'level' => ['required', 'string', 'max:20'],
            'room' => ['required', 'integer', 'min:1', Rule::unique('classrooms')->where(fn ($q) => $q->where('year', $classroom->year)->where('level', $request->level))->ignore($classroom->id)],
            'homeroom_teacher_id' => ['nullable', 'exists:users,id'],
            'co_teacher_id' => ['nullable', 'exists:users,id'],
        ]);
        $classroom->update($data);

        return back()->with('success', "บันทึกห้อง {$classroom->name()} แล้ว");
    }

    public function destroy(Classroom $classroom)
    {
        abort_if($classroom->allStudents()->exists(), 422, 'ห้องนี้ยังมีนักเรียนอยู่ ย้ายนักเรียนออกก่อน');
        abort_if($classroom->enrollments()->exists(), 422, 'ห้องนี้มีประวัติชั้นเรียนของนักเรียนแล้ว ลบไม่ได้');
        $classroom->delete();

        return back()->with('success', 'ลบห้องเรียนแล้ว');
    }

    /** ชั้นถัดไปของห้องนี้เมื่อเลื่อนชั้น (null = ไม่มีชั้นถัดไป) */
    private function nextLevel(string $level): ?string
    {
        $idx = array_search($level, Classroom::LEVELS, true);

        return $idx === false ? null : (Classroom::LEVELS[$idx + 1] ?? null);
    }

    /** หน้าตรวจสอบก่อนเลื่อนชั้น: เห็นว่าแต่ละห้องไปไหน และติ๊กคนที่ซ้ำชั้นได้ */
    public function promoteForm(Request $request)
    {
        $from = (int) $request->query('from_year', Term::current()?->year ?? now()->year + 543);
        $classrooms = Classroom::where('year', $from)->ordered()->with('students')->get();

        return view('classrooms.promote', [
            'from' => $from,
            'to' => $from + 1,
            'classrooms' => $classrooms,
            'next' => $classrooms->mapWithKeys(fn ($c) => [$c->id => $this->nextLevel($c->level)]),
            'undoBlocker' => $this->undoBlocker($from + 1),
            'promoted' => Enrollment::where('year', $from)->whereIn('status', ['promoted', 'retained', 'graduated'])->count(),
        ]);
    }

    /**
     * เลื่อนชั้นทั้งโรงเรียนขึ้นปีการศึกษาใหม่
     * ป.1/1 ปี 2568 → ป.2/1 ปี 2569, ชั้นที่เลือก (ป.6/ม.3/ม.6) → จบการศึกษา, คนที่ติ๊กซ้ำชั้น → ชั้นเดิมของปีใหม่
     * ห้องของปีเก่าเก็บไว้ใน enrollments สมุดพก/สมุดคะแนนของปีเก่าจึงยังเปิดดูได้
     */
    public function promote(Request $request)
    {
        $data = $request->validate([
            'from_year' => ['required', 'integer'],
            'graduate_levels' => ['array'],
            'graduate_levels.*' => ['string'],
            'retain' => ['array'],
            'retain.*' => ['integer'],
        ]);
        $from = (int) $data['from_year'];
        $to = $from + 1;
        $graduate = $data['graduate_levels'] ?? [];
        $retain = array_flip($data['retain'] ?? []);
        $count = ['promoted' => 0, 'retained' => 0, 'graduated' => 0];

        DB::transaction(function () use ($from, $to, $graduate, $retain, &$count) {
            foreach (Classroom::where('year', $from)->get() as $old) {
                $next = $this->nextLevel($old->level);
                foreach (Student::active()->where('classroom_id', $old->id)->get() as $student) {
                    $outcome = match (true) {
                        isset($retain[$student->id]) => 'retained',
                        in_array($old->level, $graduate, true) => 'graduated',
                        $next !== null => 'promoted',
                        default => null, // ชั้นสูงสุดที่ไม่ได้เลือกให้จบ: คงไว้ที่เดิม
                    };
                    if ($outcome === null) {
                        continue;
                    }
                    Enrollment::updateOrCreate(
                        ['student_id' => $student->id, 'year' => $from],
                        ['classroom_id' => $old->id, 'number' => $student->number, 'status' => $outcome],
                    );
                    if ($outcome === 'graduated') {
                        // ห้องปัจจุบันคงเป็นห้องสุดท้ายที่เรียน จึงปรับสถานะตรง ๆ ไม่ให้ประวัติของปีที่จบถูกเขียนทับ
                        Student::whereKey($student->id)->update(['status' => 'graduated']);
                    } else {
                        $new = Classroom::firstOrCreate(['year' => $to, 'level' => $outcome === 'retained' ? $old->level : $next, 'room' => $old->room]);
                        $student->update(['classroom_id' => $new->id]); // สร้างประวัติของปีใหม่ให้เอง (Student::saved)
                    }
                    $count[$outcome]++;
                }
            }
        });

        Audit::log('setting.promote', null, "เลื่อนชั้นปี {$from} → {$to}: เลื่อน {$count['promoted']} คน ซ้ำชั้น {$count['retained']} คน จบการศึกษา {$count['graduated']} คน");

        return redirect()->route('classrooms.index', ['year' => $to])
            ->with('success', "เลื่อนชั้นแล้ว {$count['promoted']} คน, ซ้ำชั้น {$count['retained']} คน, จบการศึกษา {$count['graduated']} คน — อย่าลืมกำหนดครูประจำชั้นปี {$to}");
    }

    /** เหตุผลที่ยกเลิกการเลื่อนชั้นขึ้นปี $to ไม่ได้ (null = ยกเลิกได้) */
    private function undoBlocker(int $to): ?string
    {
        $rooms = Classroom::where('year', $to)->pluck('id');

        return match (true) {
            ! Enrollment::where('year', $to - 1)->whereIn('status', ['promoted', 'retained', 'graduated'])->exists() => "ยังไม่มีการเลื่อนชั้นขึ้นปี {$to}",
            Enrollment::where('year', '>', $to)->exists() => 'มีการเลื่อนชั้นปีถัดไปแล้ว ต้องยกเลิกปีล่าสุดก่อน',
            Attendance::whereIn('classroom_id', $rooms)->exists() => "ปี {$to} เริ่มเช็คชื่อแล้ว ยกเลิกการเลื่อนชั้นไม่ได้",
            Score::whereHas('assessment.course', fn ($q) => $q->whereIn('classroom_id', $rooms))->exists() => "ปี {$to} มีคะแนนแล้ว ยกเลิกการเลื่อนชั้นไม่ได้",
            default => null,
        };
    }

    /** ยกเลิกการเลื่อนชั้นครั้งล่าสุด: ทุกคนกลับห้องเดิมของปีก่อน (ทำได้จนกว่าปีใหม่จะเริ่มมีเช็คชื่อ/คะแนน) */
    public function undoPromote(Request $request)
    {
        $to = (int) $request->validate(['to_year' => ['required', 'integer']])['to_year'];
        $reason = $this->undoBlocker($to);
        abort_if($reason !== null, 422, (string) $reason);

        $restored = DB::transaction(function () use ($to) {
            $previous = Enrollment::where('year', $to - 1)->whereIn('status', ['promoted', 'retained', 'graduated'])->get();
            Enrollment::where('year', $to)->whereIn('student_id', $previous->pluck('student_id'))->delete();
            foreach ($previous as $e) {
                Student::whereKey($e->student_id)->update(['classroom_id' => $e->classroom_id, 'number' => $e->number, 'status' => 'active']);
            }
            Enrollment::whereIn('id', $previous->pluck('id'))->update(['status' => 'studying']);

            return $previous->count();
        });

        Audit::log('setting.promote', null, "ยกเลิกการเลื่อนชั้นขึ้นปี {$to}: นักเรียน {$restored} คนกลับห้องเดิม");

        return redirect()->route('classrooms.index', ['year' => $to - 1])
            ->with('success', "ยกเลิกการเลื่อนชั้นแล้ว นักเรียน {$restored} คนกลับห้องเดิมของปี ".($to - 1));
    }
}
