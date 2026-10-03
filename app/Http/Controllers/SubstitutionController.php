<?php

namespace App\Http\Controllers;

use App\Models\StaffLeave;
use App\Models\Substitution;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** จัดสอนแทน: คาบของครูที่ลา/ไปราชการในวันนั้น + ครูที่ว่างคาบเดียวกัน */
class SubstitutionController extends Controller
{
    /** ครูที่มีใบลาอนุมัติแล้วครอบคลุมวันนั้น */
    private function absentTeacherIds(Carbon $date): array
    {
        return StaffLeave::where('status', 'approved')
            ->where('start_date', '<=', $date->toDateString())->where('end_date', '>=', $date->toDateString())
            ->pluck('user_id')->unique()->all();
    }

    public function index(Request $request)
    {
        $date = Carbon::parse($request->query('date', today()->toDateString()));
        $term = Term::current();
        $absent = $this->absentTeacherIds($date);

        $daySlots = $term && isset(TimetableSlot::days()[$date->dayOfWeekIso])
            ? TimetableSlot::with(['course.subject', 'course.teacher', 'classroom'])
                ->where('term_id', $term->id)->where('day', $date->dayOfWeekIso)->whereNotNull('course_id')->orderBy('period')->get()
            : collect();

        $assigned = Substitution::with('substitute')->where('date', $date->toDateString())->get()->keyBy('timetable_slot_id');
        $busy = $daySlots->groupBy('period')->map(fn ($slots) => $slots->pluck('course.teacher_id')->filter()->all());
        $teachers = User::where('role', 'teacher')->where('is_active', true)->whereNotIn('id', $absent)->orderBy('name')->get(['id', 'name']);

        return view('substitutions.index', [
            'date' => $date,
            'needs' => $daySlots->filter(fn ($s) => in_array($s->course->teacher_id, $absent, true))->values(),
            'assigned' => $assigned,
            // ครูที่ว่างในคาบนั้น: ไม่ได้ลา ไม่มีสอนคาบเดียวกัน และยังไม่ได้รับสอนแทนคาบเดียวกัน
            'free' => fn (TimetableSlot $slot) => $teachers->reject(fn ($t) => in_array($t->id, $busy[$slot->period] ?? [], true)
                || $assigned->contains(fn ($a) => $a->period === $slot->period && $a->substitute_id === $t->id && $a->timetable_slot_id !== $slot->id)),
            'periods' => \App\Support\Settings::periodTimes(),
            'monthCounts' => Substitution::with('substitute')->whereBetween('date', [$date->copy()->startOfMonth()->toDateString(), $date->copy()->endOfMonth()->toDateString()])
                ->get()->groupBy('substitute_id')->map(fn ($rows) => ['name' => $rows->first()->substitute?->name, 'count' => $rows->count()])->sortByDesc('count')->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'timetable_slot_id' => ['required', 'exists:timetable_slots,id'],
            'substitute_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)->whereIn('role', ['teacher', 'admin'])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $date = Carbon::parse($data['date']);
        $slot = TimetableSlot::with(['course.subject', 'classroom'])->findOrFail($data['timetable_slot_id']);
        abort_unless($slot->course_id && $slot->day === $date->dayOfWeekIso, 422, 'คาบนี้ไม่ได้อยู่ในตารางสอนของวันที่เลือก');
        $key = ['date' => $date->toDateString(), 'timetable_slot_id' => $slot->id];

        if (empty($data['substitute_id'])) {
            Substitution::where($key)->delete();

            return back()->with('success', 'ยกเลิกการสอนแทนคาบนี้แล้ว');
        }
        abort_if((int) $data['substitute_id'] === $slot->course->teacher_id, 422, 'เลือกครูคนอื่นที่ไม่ใช่ครูประจำวิชา');

        // คนเดียวสอนแทนสองห้องในคาบเดียวกันไม่ได้
        $clash = Substitution::where('date', $key['date'])->where('period', $slot->period)->where('substitute_id', $data['substitute_id'])
            ->where('timetable_slot_id', '!=', $slot->id)->exists()
            || TimetableSlot::where('term_id', $slot->term_id)->where('day', $slot->day)->where('period', $slot->period)
                ->whereHas('course', fn ($q) => $q->where('teacher_id', $data['substitute_id']))->exists();
        if ($clash) {
            return back()->withErrors(['substitute_id' => 'ครูคนนี้มีสอนในคาบเดียวกันอยู่แล้ว']);
        }

        Substitution::updateOrCreate($key, [
            'course_id' => $slot->course_id, 'period' => $slot->period, 'absent_teacher_id' => $slot->course->teacher_id,
            'substitute_id' => $data['substitute_id'], 'note' => $data['note'] ?? null, 'created_by' => $request->user()->id,
        ]);
        Notifier::users(User::whereKey($data['substitute_id'])->get(),
            "📋 สอนแทน: {$slot->course->subject->name} ห้อง {$slot->classroom->name()} คาบ {$slot->period} วันที่ ".thai_date($date)
            .(! empty($data['note']) ? " ({$data['note']})" : ''), route('period-attendance.index', ['date' => $date->toDateString()]));

        return back()->with('success', 'บันทึกการสอนแทนแล้ว');
    }
}
