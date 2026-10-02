<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
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
        $classroom->delete();

        return back()->with('success', 'ลบห้องเรียนแล้ว');
    }

    /**
     * เลื่อนชั้นทั้งโรงเรียนขึ้นปีการศึกษาใหม่ในคลิกเดียว
     * ป.1/1 ปี 2568 → ป.2/1 ปี 2569, ชั้นสุดท้าย (ป.6/ม.3/ม.6 ตามที่เลือก) → จบการศึกษา
     */
    public function promote(Request $request)
    {
        $data = $request->validate([
            'from_year' => ['required', 'integer'],
            'graduate_levels' => ['array'],
            'graduate_levels.*' => ['string'],
        ]);
        $from = (int) $data['from_year'];
        $to = $from + 1;
        $graduate = $data['graduate_levels'] ?? [];
        $levels = Classroom::LEVELS;
        $moved = 0;
        $graduated = 0;

        DB::transaction(function () use ($from, $to, $graduate, $levels, &$moved, &$graduated) {
            foreach (Classroom::where('year', $from)->get() as $old) {
                $students = Student::active()->where('classroom_id', $old->id);
                if (in_array($old->level, $graduate, true)) {
                    $graduated += $students->update(['status' => 'graduated']);

                    continue;
                }
                $idx = array_search($old->level, $levels, true);
                if ($idx === false || ! isset($levels[$idx + 1])) {
                    continue;
                }
                $new = Classroom::firstOrCreate(['year' => $to, 'level' => $levels[$idx + 1], 'room' => $old->room]);
                $moved += $students->update(['classroom_id' => $new->id]);
            }
        });

        return redirect()->route('classrooms.index', ['year' => $to])
            ->with('success', "เลื่อนชั้นแล้ว {$moved} คน, จบการศึกษา {$graduated} คน — อย่าลืมกำหนดครูประจำชั้นปี {$to}");
    }
}
