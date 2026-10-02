<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\FeedPost;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $announcements = Announcement::visibleTo($user)->with(['author', 'classroom'])
            ->withCount('readers')
            ->orderByDesc('pinned')->latest()->paginate(15);

        $readIds = $user->belongsToMany(Announcement::class, 'announcement_reads')->pluck('announcements.id')->all();

        return view('announcements.index', compact('announcements', 'readIds'));
    }

    public function show(Request $request, Announcement $announcement)
    {
        $user = $request->user();
        abort_unless(Announcement::visibleTo($user)->whereKey($announcement->id)->exists(), 404);
        $announcement->readers()->syncWithoutDetaching([$user->id => ['read_at' => now()]]);

        return view('announcements.show', [
            'announcement' => $announcement->load('author', 'classroom'),
            'readCount' => $announcement->readers()->count(),
        ]);
    }

    public function create()
    {
        return view('announcements.form', [
            'announcement' => new Announcement(['audience' => 'all']),
            'classrooms' => $this->classroomsFor(request()),
        ]);
    }

    public function store(Request $request)
    {
        $a = Announcement::create($this->validated($request) + ['author_id' => $request->user()->id]);

        // ขึ้นฟีดด้วย ให้คนเห็นบนหน้าแรก
        FeedPost::create([
            'type' => 'announcement', 'author_id' => $a->author_id, 'announcement_id' => $a->id,
            'headline' => 'ประกาศ', 'title' => $a->title, 'body' => \Illuminate\Support\Str::limit($a->body, 280),
            'audience' => $a->audience, 'classroom_id' => $a->classroom_id,
        ]);
        if ($request->boolean('send_line', true)) {
            \App\Services\Notifier::announcement($a);
        }

        return redirect()->route('announcements.show', $a)->with('success', 'เผยแพร่ประกาศแล้ว');
    }

    public function edit(Request $request, Announcement $announcement)
    {
        $this->authorizeEdit($request, $announcement);

        return view('announcements.form', [
            'announcement' => $announcement,
            'classrooms' => $this->classroomsFor($request),
        ]);
    }

    public function update(Request $request, Announcement $announcement)
    {
        $this->authorizeEdit($request, $announcement);
        $announcement->update($this->validated($request));

        return redirect()->route('announcements.show', $announcement)->with('success', 'บันทึกแล้ว');
    }

    public function destroy(Request $request, Announcement $announcement)
    {
        $this->authorizeEdit($request, $announcement);
        $announcement->delete();

        return redirect()->route('announcements.index')->with('success', 'ลบประกาศแล้ว');
    }

    private function authorizeEdit(Request $request, Announcement $a): void
    {
        abort_unless($request->user()->isAdmin() || $a->author_id === $request->user()->id, 403);
    }

    /** ครูประกาศได้เฉพาะห้องที่ตัวเองดูแล ผู้ดูแลประกาศได้ทุกกลุ่ม */
    private function classroomsFor(Request $request)
    {
        return $request->user()->isAdmin()
            ? Classroom::currentYear()->ordered()->get()
            : $request->user()->myClassrooms();
    }

    private function validated(Request $request): array
    {
        $user = $request->user();
        $audiences = $user->isAdmin() ? array_keys(Announcement::AUDIENCES) : ['classroom', 'staff'];

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'audience' => ['required', Rule::in($audiences)],
            'classroom_id' => ['nullable', 'required_if:audience,classroom', Rule::in($this->classroomsFor($request)->pluck('id')->all())],
            'pinned' => ['nullable', 'boolean'],
        ], ['classroom_id.required_if' => 'กรุณาเลือกห้องเรียน']);

        $data['pinned'] = $user->isAdmin() && $request->boolean('pinned');
        if ($data['audience'] !== 'classroom') {
            $data['classroom_id'] = null;
        }

        return $data;
    }
}
