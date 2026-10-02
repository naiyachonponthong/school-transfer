<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Course;
use App\Models\Student;
use App\Support\Grade;
use Illuminate\Http\Request;

/** เอกสารทางการ: ปพ.1 (ระเบียนแสดงผลการเรียน) และไฟล์ส่งออกสำหรับ DMC */
class ReportController extends Controller
{
    public function transcript(Request $request, Student $student)
    {
        $user = $request->user();
        abort_unless($student->canBeViewedBy($user), 403);
        $student->load('classroom');

        // ทุกรายวิชาที่นักเรียนเคยเรียน (มีคะแนน หรือมีผลพิเศษ เช่น มส. ที่ไม่มีคะแนนเลย)
        $courseIds = \App\Models\Score::where('student_id', $student->id)
            ->join('assessments', 'assessments.id', '=', 'scores.assessment_id')->distinct()->pluck('assessments.course_id')
            ->merge(\App\Models\CourseResult::where('student_id', $student->id)->pluck('course_id'));
        $courses = Course::with(['subject', 'term', 'assessments'])->whereIn('id', $courseIds->unique())->get()
            ->sortBy(fn ($c) => [$c->term->year, $c->term->term, $c->subject->typeOrder(), $c->subject->code]);

        $terms = $courses->groupBy('term_id')->map(function ($list) use ($student) {
            $rows = $list->map(function (Course $c) use ($student) {
                $r = $c->results()[$student->id] ?? null;

                return ['course' => $c, 'grade' => $r['grade'] ?? null, 'credit' => (float) $c->subject->credit];
            })->values();

            return ['term' => $list->first()->term, 'rows' => $rows, 'gpa' => Grade::gpa($rows), 'credits' => $rows->sum('credit')];
        })->values();

        $all = $terms->flatMap(fn ($t) => $t['rows']);

        return view('reports.transcript', [
            'student' => $student,
            'terms' => $terms,
            'gpax' => Grade::gpa($all),
            // หน่วยกิตที่ได้ = เฉพาะวิชาที่ผ่าน (1 ขึ้นไป) ไม่นับ 0 ร มส
            'credits' => $all->filter(fn ($r) => Grade::passed($r['grade']))->sum('credit'),
        ]);
    }

    /** CSV ข้อมูลนักเรียนรายบุคคลเตรียมนำเข้า DMC (ตรวจสอบรูปแบบคอลัมน์กับ DMC ปีปัจจุบันก่อนนำเข้า) */
    public function dmc(Request $request)
    {
        $students = Student::active()->with(['classroom', 'guardians'])
            ->when($request->query('classroom'), fn ($q, $id) => $q->where('classroom_id', $id))
            ->leftJoin('classrooms', 'classrooms.id', '=', 'students.classroom_id')
            ->orderBy('classrooms.level_order')->orderBy('classrooms.room')->orderBy('students.number')->select('students.*')->get();

        return response()->streamDownload(function () use ($students) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['เลขประจำตัวประชาชน', 'รหัสนักเรียน', 'ชั้น', 'ห้อง', 'เลขที่', 'คำนำหน้า', 'ชื่อ', 'นามสกุล', 'เพศ', 'วันเดือนปีเกิด (พ.ศ.)', 'กรุ๊ปเลือด', 'ชื่อผู้ปกครอง', 'ความสัมพันธ์', 'โทรศัพท์ผู้ปกครอง', 'ที่อยู่']);
            foreach ($students as $s) {
                $g = $s->guardians->first();
                fputcsv($out, [
                    $s->citizen_id, $s->student_code, $s->classroom?->level, $s->classroom?->room, $s->number,
                    $s->prefix, $s->first_name, $s->last_name, ['M' => 'ชาย', 'F' => 'หญิง'][$s->gender] ?? '',
                    $s->birthdate ? $s->birthdate->format('d/m/').($s->birthdate->year + 543) : '', $s->blood_type,
                    $g?->name, $g?->pivot->relation, $g?->phone, $s->address,
                ]);
            }
            fclose($out);
        }, 'DMC-นักเรียน-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** หน้ารวมรายงาน */
    public function index()
    {
        return view('reports.index', ['classrooms' => Classroom::currentYear()->ordered()->get()]);
    }
}
