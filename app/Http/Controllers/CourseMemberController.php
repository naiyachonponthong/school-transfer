<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Http\Request;

/** รายชื่อเฉพาะของรายวิชา: วิชาเลือก/ชุมนุมที่รับนักเรียนบางคน หรือข้ามห้อง */
class CourseMemberController extends Controller
{
    public function edit(Course $course)
    {
        $course->load(['subject', 'classroom', 'term']);

        return view('courses.members', [
            'course' => $course,
            'memberIds' => $course->members()->pluck('students.id')->flip(),
            'classrooms' => Classroom::where('year', $course->classroom->year)->ordered()->with('students')->get(),
        ]);
    }

    public function update(Request $request, Course $course)
    {
        abort_if($course->locked, 422, 'รายวิชาล็อกแล้ว แก้รายชื่อไม่ได้');
        $data = $request->validate(['student_ids' => ['array'], 'student_ids.*' => ['integer']]);

        // รับเฉพาะนักเรียนที่กำลังเรียนในปีการศึกษาเดียวกับรายวิชา
        $ids = Student::active()->whereIn('id', $data['student_ids'] ?? [])
            ->whereHas('classroom', fn ($q) => $q->where('year', $course->classroom->year))->pluck('id');
        $course->members()->sync($ids);

        return redirect()->route('courses.members', $course)->with('success', $ids->isEmpty()
            ? 'ไม่จำกัดรายชื่อ รายวิชานี้เรียนทั้งห้อง '.$course->classroom->name()
            : "กำหนดรายชื่อผู้เรียน {$ids->count()} คนแล้ว");
    }
}
