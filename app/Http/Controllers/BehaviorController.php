<?php

namespace App\Http\Controllers;

use App\Models\BehaviorRecord;
use App\Models\BehaviorRule;
use App\Models\Classroom;
use App\Models\FeedPost;
use App\Models\Student;
use Illuminate\Http\Request;

class BehaviorController extends Controller
{
    public function index(Request $request)
    {
        $classrooms = Classroom::currentYear()->ordered()->get();

        $records = BehaviorRecord::with(['student.classroom', 'recorder'])
            ->when($request->query('classroom'), fn ($q, $id) => $q->whereHas('student', fn ($s) => $s->where('classroom_id', $id)))
            ->when($request->query('type') === 'good', fn ($q) => $q->where('points', '>', 0))
            ->when($request->query('type') === 'bad', fn ($q) => $q->where('points', '<', 0))
            ->latest('date')->latest('id')->paginate(30)->withQueryString();

        // ห้องที่เลือก: แสดงคะแนนคงเหลือรายคน
        $roster = collect();
        if ($id = $request->query('classroom')) {
            $roster = Student::active()->where('classroom_id', $id)
                ->withSum('behaviorRecords as points_sum', 'points')
                ->orderBy('number')->get();
        }

        return view('behavior.index', [
            'records' => $records,
            'classrooms' => $classrooms,
            'roster' => $roster,
            'rules' => BehaviorRule::where('is_active', true)->orderByDesc('points')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['exists:students,id'],
            'behavior_rule_id' => ['nullable', 'exists:behavior_rules,id'],
            'title' => ['required_without:behavior_rule_id', 'nullable', 'string', 'max:255'],
            'points' => ['required_without:behavior_rule_id', 'nullable', 'integer', 'between:-100,100'],
            'note' => ['nullable', 'string', 'max:1000'],
            'date' => ['nullable', 'date'],
        ], ['student_ids.required' => 'กรุณาเลือกนักเรียนอย่างน้อย 1 คน']);

        $rule = isset($data['behavior_rule_id']) ? BehaviorRule::find($data['behavior_rule_id']) : null;

        foreach (array_unique($data['student_ids']) as $sid) {
            $pts = $rule?->points ?? (int) $data['points'];
            if (count(array_unique($data['student_ids'])) <= 5 && ($s = Student::find($sid))) {
                \App\Services\Notifier::parents($s, ($pts > 0 ? '🌟 น้อง'.($s->nickname ?: $s->first_name).' ได้รับคำชม: ' : '⚠️ น้อง'.($s->nickname ?: $s->first_name).' ถูกหักคะแนนความประพฤติ: ')
                    .($rule?->name ?? $data['title']).' ('.($pts > 0 ? '+' : '').$pts.')'.(! empty($data['note']) ? "\n".$data['note'] : ''));
            }
            BehaviorRecord::create([
                'student_id' => $sid,
                'behavior_rule_id' => $rule?->id,
                'title' => $rule?->name ?? $data['title'],
                'points' => $rule?->points ?? (int) $data['points'],
                'note' => $data['note'] ?? null,
                'date' => $data['date'] ?? today(),
                'recorded_by' => $request->user()->id,
            ]);
        }

        $n = count(array_unique($data['student_ids']));

        // ความดีเด่น (+10 ขึ้นไป) ขึ้นฟีดของห้องให้ผู้ปกครองในห้องเห็น — เฉพาะรายบุคคล ไม่สแปมทั้งห้อง
        $points = $rule?->points ?? (int) ($data['points'] ?? 0);
        if ($points >= 10 && $n <= 3) {
            foreach (Student::whereIn('id', $data['student_ids'])->get() as $s) {
                FeedPost::create([
                    'type' => 'achievement', 'author_id' => $request->user()->id, 'student_id' => $s->id,
                    'headline' => 'ได้รับคำชมเชย', 'title' => $rule?->name ?? $data['title'], 'body' => $data['note'] ?? null,
                    'icon' => 'bi-star-fill', 'audience' => 'classroom', 'classroom_id' => $s->classroom_id,
                ]);
            }
        }

        return back()->with('success', "บันทึกพฤติกรรม {$n} คนแล้ว");
    }

    public function destroy(Request $request, BehaviorRecord $record)
    {
        abort_unless($request->user()->isAdmin() || $record->recorded_by === $request->user()->id, 403);
        $record->delete();

        return back()->with('success', 'ลบรายการแล้ว');
    }
}
