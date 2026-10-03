<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Course;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimetableController extends Controller
{
    public function index(Request $request)
    {
        $term = Term::current();
        $classrooms = Classroom::currentYear()->ordered()->get();
        $classroom = $classrooms->firstWhere('id', (int) $request->query('classroom'))
            ?? $request->user()->myClassrooms()->first()
            ?? $classrooms->first();

        $slots = collect();
        $courses = collect();
        if ($term && $classroom) {
            $slots = TimetableSlot::with('course.subject', 'course.teacher')
                ->where('term_id', $term->id)->where('classroom_id', $classroom->id)->get()
                ->keyBy(fn ($s) => $s->day.'-'.$s->period);
            $courses = Course::with('subject', 'teacher')->where('term_id', $term->id)->where('classroom_id', $classroom->id)->get()
                ->sortBy('subject.code');
        }

        return view('timetable.index', [
            'term' => $term,
            'classrooms' => $classrooms,
            'classroom' => $classroom,
            'slots' => $slots,
            'courses' => $courses,
            'periods' => Settings::periodTimes(),
            'editing' => $request->user()->hasPermission('academics.manage') && $request->boolean('edit'),
        ]);
    }

    public function mine(Request $request)
    {
        $term = Term::current();
        $slots = $term ? TimetableSlot::with('course.subject', 'classroom')
            ->where('term_id', $term->id)
            ->whereHas('course', fn ($q) => $q->where('teacher_id', $request->user()->id))
            ->get()->keyBy(fn ($s) => $s->day.'-'.$s->period) : collect();

        return view('timetable.mine', [
            'term' => $term,
            'slots' => $slots,
            'periods' => Settings::periodTimes(),
            'totalPeriods' => $slots->count(),
        ]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'slots' => ['array'],
            'slots.*.*' => ['nullable', 'string', 'max:100'],
        ]);
        $term = Term::current();
        abort_unless($term, 422, 'ยังไม่ได้ตั้งภาคเรียนปัจจุบัน');

        $validCourses = Course::where('term_id', $term->id)->where('classroom_id', $data['classroom_id'])->pluck('id')->flip();
        $conflicts = [];

        DB::transaction(function () use ($data, $term, $validCourses, &$conflicts) {
            TimetableSlot::where('term_id', $term->id)->where('classroom_id', $data['classroom_id'])->delete();
            foreach ($data['slots'] ?? [] as $day => $periods) {
                foreach ($periods as $period => $value) {
                    $value = trim((string) $value);
                    if ($value === '') {
                        continue;
                    }
                    // ค่าที่เป็น "c:<id>" = รายวิชา, อย่างอื่น = ข้อความอิสระ (เช่น ลูกเสือ ชุมนุม)
                    $courseId = str_starts_with($value, 'c:') ? (int) substr($value, 2) : null;
                    if ($courseId && ! isset($validCourses[$courseId])) {
                        continue;
                    }
                    if ($courseId) {
                        $teacherId = Course::whereKey($courseId)->value('teacher_id');
                        $clash = $teacherId ? TimetableSlot::with('classroom')
                            ->where('term_id', $term->id)->where('day', $day)->where('period', $period)
                            ->whereHas('course', fn ($q) => $q->where('teacher_id', $teacherId))->first() : null;
                        if ($clash) {
                            $conflicts[] = TimetableSlot::DAYS[$day]." คาบ {$period} (ครูสอนห้อง {$clash->classroom->name()} อยู่แล้ว)";
                        }
                    }
                    TimetableSlot::create([
                        'term_id' => $term->id,
                        'classroom_id' => $data['classroom_id'],
                        'day' => $day,
                        'period' => $period,
                        'course_id' => $courseId,
                        'label' => $courseId ? null : $value,
                    ]);
                }
            }
        });

        $redirect = redirect()->route('timetable.index', ['classroom' => $data['classroom_id']])->with('success', 'บันทึกตารางสอนแล้ว');

        return $conflicts ? $redirect->with('warning', 'ครูสอนชนกัน: '.implode(', ', $conflicts)) : $redirect;
    }
}
