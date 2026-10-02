<?php

namespace App\Http\Controllers;

use App\Models\FeedPost;
use App\Models\Student;
use App\Models\StudentWork;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** แฟ้มสะสมผลงานนักเรียน: ครูและผู้ปกครองเพิ่มได้ (ผู้ปกครองเพิ่มแล้วครูกดรับรอง) */
class PortfolioController extends Controller
{
    private function authorizeStudent(Request $request, Student $student): void
    {
        abort_unless($student->canBeViewedBy($request->user()), 403);
    }

    public function show(Request $request, Student $student)
    {
        $this->authorizeStudent($request, $student);
        $works = $student->works()->with('recorder')->get();

        return view('portfolio.show', [
            'student' => $student->load('classroom'),
            'works' => $works,
            'hours' => $works->where('verified', true)->sum('hours'),
            'counts' => $works->countBy('category'),
        ]);
    }

    public function store(Request $request, Student $student)
    {
        $this->authorizeStudent($request, $student);
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(StudentWork::CATEGORIES))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'level' => ['nullable', Rule::in(array_keys(StudentWork::LEVELS))],
            'date' => ['nullable', 'date'],
            'hours' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'image' => ['nullable', 'image', 'max:6144'],
        ]);
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('portfolio', 'public');
        }
        $staff = $request->user()->isStaff();
        $work = $student->works()->create($data + ['recorded_by' => $request->user()->id, 'verified' => $staff]);

        // ครูแชร์รางวัลลงฟีดได้ทันที
        if ($staff && $request->boolean('share') && $work->category === 'award') {
            FeedPost::create([
                'type' => 'achievement', 'author_id' => $request->user()->id, 'student_id' => $student->id,
                'headline' => 'ได้รับรางวัล', 'title' => $work->title, 'body' => $work->description,
                'image' => $work->image, 'icon' => 'bi-trophy-fill', 'audience' => 'all',
            ]);
        }

        return back()->with('success', $staff ? 'เพิ่มลงแฟ้มผลงานแล้ว' : 'เพิ่มผลงานแล้ว รอครูรับรอง');
    }

    public function verify(StudentWork $work)
    {
        $work->update(['verified' => true]);

        return back()->with('success', 'รับรองผลงานแล้ว');
    }

    public function destroy(Request $request, StudentWork $work)
    {
        abort_unless($request->user()->isAdmin() || $work->recorded_by === $request->user()->id, 403);
        $work->delete();

        return back()->with('success', 'ลบแล้ว');
    }
}
