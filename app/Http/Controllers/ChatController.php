<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Course;
use App\Models\Message;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** แชทระหว่างครูกับผู้ปกครอง (ผูกกับนักเรียนหนึ่งคน) */
class ChatController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $conversations = Conversation::for($user)
            ->with(['participants', 'student.classroom', 'latestMessage'])
            ->orderByDesc('last_message_at')->limit(100)->get();

        $current = null;
        if ($id = $request->query('c')) {
            $current = $conversations->firstWhere('id', (int) $id);
            abort_unless($current, 404);
            $this->markRead($current, $user);
        }

        return view('chat.index', [
            'conversations' => $conversations,
            'current' => $current,
            'messages' => $current ? $current->messages()->reorder()->with('user')->latest('id')->limit(60)->get()->reverse()->values() : collect(),
            'contacts' => $this->contacts($user),
        ]);
    }

    /** คนที่เริ่มแชทด้วยได้ */
    private function contacts(User $user): array
    {
        $term = Term::current();
        if ($user->isParent()) {
            $out = [];
            foreach ($user->children()->with('classroom.homeroomTeacher', 'classroom.coTeacher')->get() as $child) {
                $teachers = collect([$child->classroom?->homeroomTeacher, $child->classroom?->coTeacher])->filter()
                    ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'role' => 'ครูประจำชั้น']);
                if ($term && $child->classroom_id) {
                    $teachers = $teachers->concat(Course::with('teacher', 'subject')->where('term_id', $term->id)->where('classroom_id', $child->classroom_id)
                        ->whereNotNull('teacher_id')->get()->map(fn ($c) => ['id' => $c->teacher_id, 'name' => $c->teacher->name, 'role' => 'ครู'.$c->subject->name]));
                }
                $out[] = ['student' => $child, 'teachers' => $teachers->unique('id')->values()];
            }

            return $out;
        }

        // ครู: นักเรียนที่ตัวเองดูแล/สอน (ผู้ดูแลเห็นทุกคน)
        $q = Student::active()->with('classroom')->whereHas('guardians');
        if (! $user->isAdmin()) {
            $classIds = $user->myClassrooms()->pluck('id')
                ->merge($term ? Course::where('term_id', $term->id)->where('teacher_id', $user->id)->pluck('classroom_id') : [])->unique();
            $q->whereIn('classroom_id', $classIds);
        }

        return ['students' => $q->orderBy('classroom_id')->orderBy('number')->get()];
    }

    public function start(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(['student_id' => ['required', 'exists:students,id'], 'teacher_id' => ['nullable', 'exists:users,id']]);
        $student = Student::findOrFail($data['student_id']);

        if ($user->isParent()) {
            abort_unless($student->isGuardedBy($user), 403);
            $allowed = collect($this->contacts($user))->firstWhere('student.id', $student->id)['teachers'] ?? collect();
            abort_unless(isset($data['teacher_id']) && $allowed->contains('id', (int) $data['teacher_id']), 403, 'เลือกครูของบุตรหลานเท่านั้น');
            $members = [$user->id, (int) $data['teacher_id']];
        } else {
            abort_unless(collect($this->contacts($user)['students'])->contains('id', $student->id), 403, 'นักเรียนคนนี้ไม่ได้อยู่ในความดูแลของคุณ');
            $members = array_merge([$user->id], $student->guardians()->pluck('users.id')->all());
        }

        // ใช้ห้องแชทเดิมถ้ามีสมาชิกชุดเดียวกันของนักเรียนคนนี้อยู่แล้ว
        $existing = Conversation::where('student_id', $student->id)->for($user)->with('participants')->get()
            ->first(fn ($c) => $c->participants->pluck('id')->sort()->values()->all() === collect($members)->unique()->sort()->values()->all());

        $conv = $existing ?? DB::transaction(function () use ($student, $user, $members) {
            $c = Conversation::create(['student_id' => $student->id, 'created_by' => $user->id, 'topic' => 'เรื่องของ'.($student->nickname ? 'น้อง'.$student->nickname : $student->fullName())]);
            $c->participants()->attach(collect($members)->unique()->mapWithKeys(fn ($id) => [$id => ['last_read_at' => now()]])->all());

            return $c;
        });

        return redirect()->route('chat.index', ['c' => $conv->id]);
    }

    /** ข้อความใหม่หลัง id ที่ระบุ (polling ทุกไม่กี่วินาที) */
    public function poll(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        $conversation->load('participants');
        abort_unless($conversation->hasParticipant($user), 403);
        $msgs = $conversation->messages()->with('user')->where('id', '>', (int) $request->query('after'))->limit(100)->get();
        if ($msgs->isNotEmpty()) {
            $this->markRead($conversation, $user);
        }

        return response()->json([
            'messages' => $msgs->map->toChatArray($user->id),
            'unread' => Conversation::unreadTotal($user),
        ]);
    }

    public function send(Request $request, Conversation $conversation)
    {
        $user = $request->user();
        $conversation->load('participants', 'student');
        abort_unless($conversation->hasParticipant($user), 403);
        $data = $request->validate([
            'body' => ['required_without:image', 'nullable', 'string', 'max:3000'],
            'image' => ['nullable', 'image', 'max:6144'],
        ]);

        $msg = $conversation->messages()->create([
            'user_id' => $user->id,
            'body' => $data['body'] ?? null,
            'attachment' => $request->hasFile('image') ? $request->file('image')->store('chat', 'public') : null,
        ]);
        $conversation->update(['last_message_at' => now()]);
        $this->markRead($conversation, $user);

        // แจ้ง LINE คนอื่นในห้อง ไม่เกินทุก 10 นาทีต่อคนต่อห้อง กันข้อความรัว
        $recipients = $conversation->others($user)->filter(fn ($u) => Cache::add("chat-line:{$conversation->id}:{$u->id}", 1, now()->addMinutes(10)));
        if ($recipients->isNotEmpty()) {
            Notifier::users($recipients, "💬 ข้อความจาก{$user->name}: ".\Illuminate\Support\Str::limit($msg->body ?? '[รูปภาพ]', 120), route('chat.index', ['c' => $conversation->id]));
        }

        return $request->wantsJson()
            ? response()->json(['message' => $msg->load('user')->toChatArray($user->id)])
            : redirect()->route('chat.index', ['c' => $conversation->id]);
    }

    private function markRead(Conversation $c, User $user): void
    {
        $c->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
    }
}
