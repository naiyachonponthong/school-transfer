<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\PeriodAttendance;
use App\Models\Score;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    /** ช่องคะแนนเริ่มต้นเมื่อเปิดรายวิชาใหม่ (70:30) แก้ไขภายหลังได้ */
    public const DEFAULT_ASSESSMENTS = [
        ['คะแนนเก็บก่อนกลางภาค', 30],
        ['สอบกลางภาค', 20],
        ['คะแนนเก็บหลังกลางภาค', 20],
        ['สอบปลายภาค', 30],
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        $terms = Term::orderByDesc('year')->orderByDesc('term')->get();
        $term = $terms->firstWhere('id', (int) $request->query('term')) ?? Term::current();
        $showAll = $user->isAdmin() && $request->query('view') !== 'mine';

        $courses = Course::with(['subject', 'classroom', 'teacher'])
            ->withCount('assessments')
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->when(! $showAll, fn ($q) => $q->where('teacher_id', $user->id))
            ->when($request->query('classroom'), fn ($q, $id) => $q->where('classroom_id', $id))
            ->get()
            ->sortBy(fn ($c) => [$c->classroom->level_order, $c->classroom->room, $c->subject->code])
            ->values();

        return view('courses.index', [
            'courses' => $courses,
            'terms' => $terms,
            'term' => $term,
            'showAll' => $showAll,
            'classrooms' => Classroom::currentYear()->ordered()->get(),
            'subjects' => Subject::orderBy('code')->get(),
            'teachers' => User::whereIn('role', ['teacher', 'admin'])->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'term_id' => ['required', 'exists:terms,id'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'subject_id' => ['required', 'exists:subjects,id', Rule::unique('courses')->where(fn ($q) => $q->where('term_id', $request->term_id)->where('classroom_id', $request->classroom_id)->where('variant', ''))],
            'teacher_id' => ['nullable', 'exists:users,id'],
        ], ['subject_id.unique' => 'ห้องนี้มีวิชานี้อยู่แล้วในภาคเรียนนี้']);

        $course = Course::create($data);
        $this->seedAssessments($course);

        return back()->with('success', 'เปิดรายวิชาแล้ว');
    }

    /** เปิดหลายวิชาให้หลายห้องพร้อมกัน */
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'term_id' => ['required', 'exists:terms,id'],
            'classroom_ids' => ['required', 'array'],
            'classroom_ids.*' => ['exists:classrooms,id'],
            'subject_ids' => ['required', 'array'],
            'subject_ids.*' => ['exists:subjects,id'],
            'teacher_id' => ['nullable', 'exists:users,id'],
        ]);

        $created = 0;
        foreach ($data['classroom_ids'] as $cid) {
            foreach ($data['subject_ids'] as $sid) {
                $course = Course::firstOrCreate(
                    ['term_id' => $data['term_id'], 'classroom_id' => $cid, 'subject_id' => $sid, 'variant' => ''],
                    ['teacher_id' => $data['teacher_id'] ?? null]
                );
                if ($course->wasRecentlyCreated) {
                    $this->seedAssessments($course);
                    $created++;
                }
            }
        }

        return back()->with('success', "เปิดรายวิชาใหม่ {$created} รายการ");
    }

    public function update(Request $request, Course $course)
    {
        $data = $request->validate([
            'teacher_id' => ['nullable', 'exists:users,id'],
            'locked' => ['nullable', 'boolean'],
        ]);
        $course->fill(['teacher_id' => $data['teacher_id'] ?? null, 'locked' => $request->boolean('locked')]);
        if ($diff = Audit::diff($course)) {
            $course->load(['subject', 'classroom', 'term']);
            $what = isset($diff['locked']) ? ($course->locked ? 'ล็อกคะแนน' : 'ปลดล็อกคะแนน') : 'เปลี่ยนครูผู้สอน';
            Audit::log('course.update', $course, "{$what} {$course->subject->code} {$course->classroom->name()} ({$course->term->shortLabel()})", $diff);
        }
        $course->save();

        return back()->with('success', 'บันทึกแล้ว');
    }

    public function destroy(Course $course)
    {
        // กันคะแนน/ผลการเรียนหายถาวร: ลบได้เฉพาะรายวิชาที่ยังไม่มีผลใด ๆ (เช่น เปิดผิด)
        $scored = Score::whereIn('assessment_id', $course->assessments()->pluck('id'))->exists()
            || $course->outcomes()->exists() || PeriodAttendance::where('course_id', $course->id)->exists();
        if ($scored) {
            return back()->withErrors(['course' => 'รายวิชานี้มีคะแนน ผลการเรียน หรือการเช็คชื่อรายคาบแล้ว ลบไม่ได้ (ถ้าเปิดผิดห้อง ให้ลบคะแนนในสมุดคะแนนออกก่อน)']);
        }
        $course->load(['subject', 'classroom', 'term']);
        Audit::log('course.delete', $course, "ลบรายวิชา {$course->subject->code} {$course->classroom->name()} ({$course->term->shortLabel()})");
        $course->delete();

        return back()->with('success', 'ลบรายวิชาแล้ว');
    }

    private function seedAssessments(Course $course): void
    {
        if ($course->subject->type === 'activity') {
            Assessment::create(['course_id' => $course->id, 'name' => 'ผลการประเมิน', 'max_score' => 100, 'sort' => 1]);

            return;
        }
        foreach (self::DEFAULT_ASSESSMENTS as $i => [$name, $max]) {
            Assessment::create(['course_id' => $course->id, 'name' => $name, 'max_score' => $max, 'sort' => $i + 1]);
        }
    }
}
