<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Term;
use App\Models\User;
use App\Services\Notifier;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * ขั้นตอนส่งผลการเรียน: ครูผู้สอนส่ง → ฝ่ายวิชาการอนุมัติ (ล็อกรายวิชา + เก็บผล) หรือตีกลับพร้อมเหตุผล
 */
class CourseApprovalController extends Controller
{
    /** ครูผู้สอนส่งผลการเรียน: หลังส่งแล้วแก้คะแนนไม่ได้จนกว่าจะถูกตีกลับ */
    public function submit(Request $request, Course $course)
    {
        abort_unless($course->canEdit($request->user()), 403, 'รายวิชานี้ไม่ได้อยู่ในความรับผิดชอบของคุณ');
        abort_if($course->locked, 422, 'รายวิชานี้อนุมัติและล็อกแล้ว');
        abort_if($course->submitted_at !== null, 422, 'ส่งผลการเรียนไปแล้ว รอฝ่ายวิชาการตรวจ');

        $course->update(['submitted_at' => now(), 'submitted_by' => $request->user()->id, 'return_note' => null]);

        return back()->with('success', 'ส่งผลการเรียนให้ฝ่ายวิชาการแล้ว');
    }

    public function index(Request $request)
    {
        $term = Term::find($request->query('term')) ?? Term::current();
        $courses = Course::with(['subject', 'classroom', 'teacher', 'assessments'])
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->get()->sortBy(fn ($c) => [$c->classroom->level_order, $c->classroom->room, $c->subject->code])->values();

        return view('courses.approvals', [
            'term' => $term,
            'terms' => Term::orderByDesc('year')->orderByDesc('term')->get(),
            'pending' => $courses->filter(fn ($c) => $c->submitted_at && ! $c->locked)->values(),
            'waiting' => $courses->filter(fn ($c) => ! $c->submitted_at && ! $c->locked)->values(),
            'approved' => $courses->where('locked', true)->values(),
        ]);
    }

    public function approve(Request $request, Course $course)
    {
        abort_if($course->locked, 422, 'รายวิชานี้อนุมัติแล้ว');
        // ล็อก = เก็บผลการเรียนของทุกคนในรายวิชา (Course::saved)
        $course->update(['locked' => true, 'approved_at' => now(), 'approved_by' => $request->user()->id]);
        Audit::log('course.approve', $course, "อนุมัติและล็อกผลการเรียน {$course->subject->name} {$course->classroom->name()}");

        return back()->with('success', "อนุมัติผลการเรียน {$course->subject->name} {$course->classroom->name()} แล้ว");
    }

    public function return(Request $request, Course $course)
    {
        abort_if($course->locked, 422, 'รายวิชานี้อนุมัติแล้ว ปลดล็อกที่หน้ารายวิชาก่อน');
        $data = $request->validate(['return_note' => ['required', 'string', 'max:255']], ['return_note.required' => 'กรุณาระบุสิ่งที่ต้องแก้ไข']);

        $course->update(['submitted_at' => null, 'submitted_by' => null, 'return_note' => $data['return_note']]);
        Audit::log('course.return', $course, "ตีกลับผลการเรียน {$course->subject->name} {$course->classroom->name()}: {$data['return_note']}");
        if ($course->teacher_id) {
            Notifier::users(User::whereKey($course->teacher_id)->get(),
                "↩️ ผลการเรียน {$course->subject->name} {$course->classroom->name()} ถูกตีกลับ: {$data['return_note']}", route('gradebook.show', $course));
        }

        return back()->with('success', 'ตีกลับให้ครูผู้สอนแก้ไขแล้ว');
    }
}
