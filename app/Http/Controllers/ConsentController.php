<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\ConsentForm;
use App\Models\ConsentResponse;
use App\Models\Student;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** หนังสือขออนุญาตผู้ปกครอง: ครูส่งถึงห้อง → ผู้ปกครองกดอนุญาต/ไม่อนุญาต → ครูเห็นว่าใครยังไม่ตอบ */
class ConsentController extends Controller
{
    private function classroomsFor(User $user)
    {
        return $user->hasPermission('academics.manage') ? Classroom::currentYear()->ordered()->get() : $user->myClassrooms();
    }

    private function authorizeForm(Request $request, ConsentForm $form): void
    {
        $user = $request->user();
        abort_unless($user->hasPermission('academics.manage') || $form->created_by === $user->id
            || $user->myClassrooms()->pluck('id')->intersect($form->classroom_ids)->isNotEmpty(), 403);
    }

    /* ---------------- บุคลากร ---------------- */

    public function index(Request $request)
    {
        $user = $request->user();
        $mine = $user->myClassrooms()->pluck('id');
        $forms = ConsentForm::withCount(['responses', 'responses as agreed_count' => fn ($q) => $q->where('agreed', true)])->latest()->get()
            ->filter(fn ($f) => $user->hasPermission('academics.manage') || $f->created_by === $user->id || $mine->intersect($f->classroom_ids)->isNotEmpty())
            ->each(fn ($f) => $f->target_count = $f->students()->count())->values();

        return view('consents.index', ['forms' => $forms, 'classrooms' => $this->classroomsFor($user)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'classroom_ids' => ['required', 'array', 'min:1'],
            'classroom_ids.*' => [Rule::in($this->classroomsFor($request->user())->pluck('id')->all())],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
        ], ['classroom_ids.required' => 'เลือกห้องอย่างน้อย 1 ห้อง', 'classroom_ids.*.in' => 'ส่งได้เฉพาะห้องที่คุณดูแล'], ['title' => 'เรื่อง', 'body' => 'รายละเอียด']);

        $form = ConsentForm::create(['classroom_ids' => array_map('intval', $data['classroom_ids']), 'created_by' => $request->user()->id] + $data);

        foreach ($form->students()->with('guardians')->get() as $student) {
            Notifier::parents($student, "📝 ขออนุญาตผู้ปกครอง: {$form->title} (น้อง".($student->nickname ?: $student->first_name).')'
                .($form->due_date ? ' กรุณาตอบภายใน '.thai_date($form->due_date) : ''), route('parent.consents'));
        }

        return redirect()->route('consents.show', $form)->with('success', 'ส่งหนังสือขออนุญาตแล้ว');
    }

    public function show(Request $request, ConsentForm $form)
    {
        $this->authorizeForm($request, $form);

        return view('consents.show', [
            'form' => $form,
            'students' => $form->students()->with('classroom')->get()->sortBy(fn ($s) => [$s->classroom->level_order, $s->classroom->room, $s->number])->values(),
            'responses' => $form->responses()->with('user')->get()->keyBy('student_id'),
        ]);
    }

    /** ครูบันทึกแทนเมื่อผู้ปกครองตอบเป็นกระดาษ/โทรแจ้ง */
    public function record(Request $request, ConsentForm $form)
    {
        $this->authorizeForm($request, $form);
        $data = $request->validate(['student_id' => ['required', 'exists:students,id'], 'agreed' => ['required', 'boolean']]);
        abort_unless($form->includes(Student::findOrFail($data['student_id'])), 422, 'นักเรียนคนนี้ไม่ได้อยู่ในห้องที่ส่งหนังสือ');

        ConsentResponse::updateOrCreate(['consent_form_id' => $form->id, 'student_id' => $data['student_id']],
            ['agreed' => $data['agreed'], 'user_id' => $request->user()->id, 'note' => 'ครูบันทึกแทน']);

        return back()->with('success', 'บันทึกคำตอบแล้ว');
    }

    public function close(Request $request, ConsentForm $form)
    {
        $this->authorizeForm($request, $form);
        $form->update(['is_open' => ! $form->is_open]);

        return back()->with('success', $form->is_open ? 'เปิดรับคำตอบอีกครั้ง' : 'ปิดรับคำตอบแล้ว');
    }

    /* ---------------- ผู้ปกครอง ---------------- */

    public function parentIndex(Request $request)
    {
        $children = $request->user()->children()->with('classroom')->get();
        $forms = ConsentForm::latest()->get()->filter(fn ($f) => $children->contains(fn ($c) => $f->includes($c)))->values();
        $responses = ConsentResponse::whereIn('consent_form_id', $forms->pluck('id'))->whereIn('student_id', $children->pluck('id'))->get()
            ->keyBy(fn ($r) => $r->consent_form_id.'-'.$r->student_id);

        return view('consents.parent', compact('children', 'forms', 'responses'));
    }

    public function respond(Request $request, ConsentForm $form)
    {
        $data = $request->validate([
            'student_id' => ['required', Rule::in($request->user()->children()->pluck('students.id')->all())],
            'agreed' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        abort_unless($form->includes(Student::findOrFail($data['student_id'])), 403);
        abort_unless($form->acceptsResponses(), 422, 'หนังสือนี้ปิดรับคำตอบแล้ว กรุณาติดต่อครูประจำชั้น');

        ConsentResponse::updateOrCreate(['consent_form_id' => $form->id, 'student_id' => $data['student_id']],
            ['agreed' => $data['agreed'], 'user_id' => $request->user()->id, 'note' => $data['note'] ?? null]);

        return back()->with('success', $data['agreed'] ? 'บันทึกว่าอนุญาตแล้ว' : 'บันทึกว่าไม่อนุญาตแล้ว');
    }
}
