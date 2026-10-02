<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\FeedPost;
use App\Models\FeedReaction;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeedController extends Controller
{
    public static function query(\App\Models\User $user)
    {
        return FeedPost::visibleTo($user)
            ->with(['author', 'student.classroom', 'reactions', 'announcement', 'classroom'])
            ->latest()->latest('id');
    }

    public function index(Request $request)
    {
        $posts = self::query($request->user())->paginate(10);

        if ($request->ajax()) {
            return view('feed.list', ['posts' => $posts]);
        }

        return view('feed.index', [
            'posts' => $posts,
            'classrooms' => $request->user()->isStaff() ? Classroom::currentYear()->ordered()->get() : collect(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isStaff(), 403);
        $data = $request->validate([
            'type' => ['required', Rule::in(['post', 'achievement'])],
            'body' => ['required_without:image', 'nullable', 'string', 'max:5000'],
            'title' => ['nullable', 'string', 'max:150'],
            'student_code' => ['nullable', 'string', 'max:40'],
            'audience' => ['required', Rule::in(['all', 'parents', 'staff', 'classroom'])],
            'classroom_id' => ['nullable', 'required_if:audience,classroom', 'exists:classrooms,id'],
            'image' => ['nullable', 'image', 'max:6144'],
        ], ['body.required_without' => 'พิมพ์ข้อความหรือแนบรูปอย่างน้อยหนึ่งอย่าง']);

        $student = null;
        if (filled($data['student_code'] ?? null)) {
            $code = trim(explode(' ', trim($data['student_code']))[0]);
            $student = Student::where('student_code', $code)->first();
            if (! $student) {
                return back()->withInput()->withErrors(['student_code' => "ไม่พบรหัสนักเรียน {$code}"]);
            }
        }

        FeedPost::create([
            'type' => $data['type'],
            'author_id' => $request->user()->id,
            'student_id' => $student?->id,
            'headline' => $data['type'] === 'achievement' ? 'ได้รับประกาศเกียรติคุณ' : null,
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
            'image' => $request->hasFile('image') ? $request->file('image')->store('feed', 'public') : null,
            'icon' => $data['type'] === 'achievement' ? 'bi-trophy-fill' : null,
            'audience' => $data['audience'],
            'classroom_id' => $data['audience'] === 'classroom' ? $data['classroom_id'] : null,
        ]);

        return back()->with('success', 'โพสต์แล้ว');
    }

    public function destroy(Request $request, FeedPost $post)
    {
        abort_unless($post->canDelete($request->user()), 403);
        $post->delete();

        return back()->with('success', 'ลบโพสต์แล้ว');
    }

    /** กด/ยกเลิกอีโมจิ คืนสรุปใหม่เป็น JSON */
    public function react(Request $request, FeedPost $post)
    {
        abort_unless(FeedPost::visibleTo($request->user())->whereKey($post->id)->exists(), 404);
        $emoji = $request->validate(['emoji' => ['required', Rule::in(FeedPost::REACTIONS)]])['emoji'];

        $existing = FeedReaction::where(['feed_post_id' => $post->id, 'user_id' => $request->user()->id, 'emoji' => $emoji])->first();
        $existing ? $existing->delete() : FeedReaction::create(['feed_post_id' => $post->id, 'user_id' => $request->user()->id, 'emoji' => $emoji]);

        $post->load('reactions');

        return response()->json(['reactions' => $post->reactionSummary($request->user()->id)]);
    }
}
