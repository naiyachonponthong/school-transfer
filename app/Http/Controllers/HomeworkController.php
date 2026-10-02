<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Score;
use App\Models\Student;
use App\Models\Submission;
use App\Models\Term;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** การบ้าน/งาน: ครูสั่งงาน → ผู้ปกครองส่งงานแทนลูก (หรือครูบันทึกว่าส่งกระดาษ) → ครูตรวจให้คะแนน → ส่งเข้าสมุดคะแนน */
class HomeworkController extends Controller
{
    private function courses(User $user)
    {
        $term = Term::current();

        return Course::with(['subject', 'classroom', 'assessments'])
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->when(! $user->isAdmin(), fn ($q) => $q->where('teacher_id', $user->id))
            ->get()->sortBy(fn ($c) => [$c->classroom->level_order, $c->classroom->room, $c->subject->code])->values();
    }

    public function index(Request $request)
    {
        $courses = $this->courses($request->user());
        $assignments = Assignment::with(['course.subject', 'course.classroom'])
            ->withCount(['submissions as submitted_count' => fn ($q) => $q->whereNotNull('submitted_at'), 'submissions as graded_count' => fn ($q) => $q->whereNotNull('score')])
            ->whereIn('course_id', $courses->pluck('id'))
            ->latest()->paginate(30);

        return view('homework.index', compact('courses', 'assignments'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'],
            'max_score' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'assessment_id' => ['nullable', 'exists:assessments,id'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);
        $course = Course::findOrFail($data['course_id']);
        abort_unless($course->canEdit($request->user()), 403);
        if (! empty($data['assessment_id'])) {
            abort_unless($course->assessments()->whereKey($data['assessment_id'])->exists(), 422);
        }
        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment')->store('assignments', 'public');
        }
        $a = Assignment::create($data + ['created_by' => $request->user()->id]);

        if ($request->boolean('notify', true)) {
            foreach ($course->classroom->students()->get() as $s) {
                Notifier::parents($s, "📘 งานใหม่วิชา{$course->subject->name}: {$a->title}".($a->due_at ? ' ส่งภายใน '.thai_datetime($a->due_at) : ''), route('parent.homework'));
            }
        }

        return redirect()->route('homework.show', $a)->with('success', 'สั่งงานแล้ว');
    }

    public function show(Request $request, Assignment $assignment)
    {
        $assignment->load('course.classroom', 'course.subject', 'assessment');
        abort_unless($assignment->course->canEdit($request->user()), 403);
        $students = $assignment->course->classroom->students()->get();
        $subs = $assignment->submissions()->get()->keyBy('student_id');

        return view('homework.show', compact('assignment', 'students', 'subs'));
    }

    public function destroy(Request $request, Assignment $assignment)
    {
        abort_unless($assignment->course->canEdit($request->user()), 403);
        $assignment->delete();

        return redirect()->route('homework.index')->with('success', 'ลบงานแล้ว');
    }

    /** บันทึกคะแนน/คอมเมนต์/ส่งกระดาษ ทั้งห้องครั้งเดียว */
    public function grade(Request $request, Assignment $assignment)
    {
        abort_unless($assignment->course->canEdit($request->user()), 403);
        $max = $assignment->max_score;
        $data = $request->validate([
            'rows' => ['array'],
            'rows.*.score' => array_merge(['nullable', 'numeric', 'min:0'], $max ? ['max:'.$max] : []),
            'rows.*.feedback' => ['nullable', 'string', 'max:255'],
            'rows.*.paper' => ['nullable', 'boolean'],
        ]);
        $valid = $assignment->course->classroom->students()->pluck('id')->flip();

        DB::transaction(function () use ($data, $assignment, $valid, $request) {
            foreach ($data['rows'] ?? [] as $sid => $row) {
                if (! isset($valid[$sid])) {
                    continue;
                }
                $sub = Submission::firstOrNew(['assignment_id' => $assignment->id, 'student_id' => $sid]);
                if (! empty($row['paper']) && ! $sub->submitted_at) {
                    $sub->fill(['submitted_at' => now(), 'channel' => 'paper', 'submitted_by' => $request->user()->id]);
                }
                $score = ($row['score'] ?? '') === '' ? null : (float) $row['score'];
                if ($score !== $sub->score || ($row['feedback'] ?? null) !== $sub->feedback) {
                    $sub->fill(['score' => $score, 'feedback' => $row['feedback'] ?? null, 'graded_at' => $score !== null ? now() : null]);
                }
                if ($sub->isDirty() && ($sub->exists || $sub->submitted_at || $score !== null)) {
                    $sub->save();
                }
            }
        });

        return back()->with('success', 'บันทึกการตรวจแล้ว');
    }

    /** ส่งคะแนนงานเข้าช่องคะแนนในสมุดคะแนน (แปลงสัดส่วนตามคะแนนเต็ม) */
    public function sync(Request $request, Assignment $assignment)
    {
        abort_unless($assignment->course->canEdit($request->user()), 403);
        $assessment = $assignment->assessment;
        abort_unless($assessment && $assignment->max_score, 422, 'งานนี้ยังไม่ได้ผูกกับช่องคะแนน หรือไม่ได้กำหนดคะแนนเต็ม');
        abort_if($assignment->course->locked && ! $request->user()->isAdmin(), 403, 'รายวิชาถูกล็อกคะแนนแล้ว');

        $n = 0;
        foreach ($assignment->submissions()->whereNotNull('score')->get() as $sub) {
            Score::updateOrCreate(
                ['assessment_id' => $assessment->id, 'student_id' => $sub->student_id],
                ['score' => round($sub->score / $assignment->max_score * $assessment->max_score, 2)]
            );
            $n++;
        }

        return back()->with('success', "ส่งคะแนน {$n} คนเข้าช่อง \"{$assessment->name}\" แล้ว");
    }

    /* ---------- ผู้ปกครอง / นักเรียน ---------- */

    public function parentIndex(Request $request)
    {
        $user = $request->user();
        $term = Term::current();
        // นักเรียนเห็นเฉพาะของตัวเอง ผู้ปกครองเห็นของลูกทุกคน
        $children = $user->isStudent()
            ? collect([$user->studentProfile?->load('classroom')])->filter()
            : $user->children()->with('classroom')->get();
        $data = $children->map(function (Student $child) use ($term) {
            $assignments = Assignment::with('course.subject')
                ->whereHas('course', fn ($q) => $q->where('classroom_id', $child->classroom_id)->when($term, fn ($t) => $t->where('term_id', $term->id)))
                ->latest()->limit(40)->get();
            $subs = Submission::where('student_id', $child->id)->whereIn('assignment_id', $assignments->pluck('id'))->get()->keyBy('assignment_id');

            return ['child' => $child, 'assignments' => $assignments, 'subs' => $subs];
        });

        return view('homework.parent', ['items' => $data]);
    }

    public function submit(Request $request, Assignment $assignment)
    {
        $user = $request->user();
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'text' => ['required_without:file', 'nullable', 'string', 'max:5000'],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx,ppt,pptx,xls,xlsx,mp4,mov', 'max:20480'],
        ], ['text.required_without' => 'พิมพ์คำตอบหรือแนบไฟล์อย่างน้อยหนึ่งอย่าง']);
        $student = Student::findOrFail($data['student_id']);
        abort_unless(($student->isOwnedBy($user) || $student->isGuardedBy($user)) && $student->classroom_id === $assignment->course->classroom_id, 403);

        $sub = Submission::firstOrNew(['assignment_id' => $assignment->id, 'student_id' => $student->id]);
        abort_if($sub->score !== null, 422, 'ครูตรวจงานนี้แล้ว ส่งใหม่ไม่ได้');
        $sub->fill([
            'text' => $data['text'] ?? $sub->text,
            'file' => $request->hasFile('file') ? $request->file('file')->store('submissions', 'public') : $sub->file,
            'submitted_at' => now(),
            'channel' => 'online',
            'submitted_by' => $user->id,
        ])->save();

        return back()->with('success', 'ส่งงาน "'.$assignment->title.'" แล้ว'.($sub->isLate() ? ' (เลยกำหนด)' : ''));
    }
}
