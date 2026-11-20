<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\PreschoolAssessment;
use App\Models\PreschoolDailyLog;
use App\Models\Term;
use App\Support\Audit;
use App\Support\Preschool;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** ปฐมวัย: ประเมินพัฒนาการ 12 มาตรฐาน (ปฐ.02) และบันทึกประจำวัน (ครูประจำชั้นห้อง อ. / ผู้ดูแล) */
class PreschoolController extends Controller
{
    /** ห้องอนุบาลของปีนั้นที่ผู้ใช้ดูแล (ผู้ดูแลระบบเห็นทุกห้อง) */
    private function classrooms(Request $request, ?int $year)
    {
        $user = $request->user();

        return ! $year ? collect() : Classroom::where('year', $year)->where('level', 'like', 'อ.%')
            ->when(! $user->isAdmin(), fn ($q) => $q->where(fn ($w) => $w->where('homeroom_teacher_id', $user->id)->orWhere('co_teacher_id', $user->id)))
            ->ordered()->get();
    }

    public function index(Request $request)
    {
        $term = Term::find($request->query('term')) ?? Term::current();
        $classrooms = $this->classrooms($request, $term?->year);
        $classroom = $classrooms->firstWhere('id', (int) $request->query('classroom')) ?? $classrooms->first();
        $students = $classroom ? $classroom->students()->get() : collect();

        return view('preschool.index', [
            'term' => $term,
            'terms' => Term::orderByDesc('year')->orderByDesc('term')->get(),
            'classrooms' => $classrooms,
            'classroom' => $classroom,
            'students' => $students,
            'assessments' => $term && $classroom
                ? PreschoolAssessment::where('term_id', $term->id)->whereIn('student_id', $students->pluck('id'))->get()->keyBy('student_id')
                : collect(),
            'standards' => Preschool::standards(),
        ]);
    }

    public function save(Request $request, Classroom $classroom)
    {
        abort_unless(Preschool::isPreschoolLevel($classroom->level), 404);
        abort_unless($classroom->isManagedBy($request->user()), 403, 'บันทึกได้เฉพาะครูประจำชั้นของห้องนี้');
        $data = $request->validate([
            'term_id' => ['required', 'exists:terms,id'],
            'ps' => ['array'],
            'ps.*.l' => ['array'],
            'ps.*.l.*' => ['nullable', 'in:'.implode(',', array_keys(Preschool::LEVELS))],
            'ps.*.comment' => ['nullable', 'string', 'max:1000'],
        ]);
        $term = Term::findOrFail($data['term_id']);
        abort_unless((int) $term->year === (int) $classroom->year, 422, 'ภาคเรียนไม่ตรงกับปีการศึกษาของห้อง');

        $input = $data['ps'] ?? [];
        $saved = 0;
        foreach ($classroom->students()->pluck('id') as $studentId) {
            if (! array_key_exists($studentId, $input)) {
                continue;
            }
            $levels = [];
            foreach (array_keys(Preschool::STANDARDS) as $no) {
                $v = $input[$studentId]['l'][$no] ?? null;
                $levels[$no] = $v === null || $v === '' ? null : (int) $v;
            }
            $comment = trim((string) ($input[$studentId]['comment'] ?? '')) ?: null;
            $key = ['term_id' => $term->id, 'student_id' => $studentId];
            if ($comment === null && ! array_filter($levels, fn ($v) => $v !== null)) {
                PreschoolAssessment::where($key)->delete();

                continue;
            }
            PreschoolAssessment::updateOrCreate($key, ['levels' => $levels, 'comment' => $comment, 'recorded_by' => $request->user()->id]);
            $saved++;
        }

        return redirect()->route('preschool.index', ['term' => $term->id, 'classroom' => $classroom->id])
            ->with('success', "บันทึกผลประเมินพัฒนาการแล้ว {$saved} คน");
    }

    /** พิมพ์ ปฐ.03 ทั้งห้อง (หนึ่งคนต่อหนึ่งหน้า) */
    public function report(Request $request, Classroom $classroom)
    {
        abort_unless(Preschool::isPreschoolLevel($classroom->level), 404);
        abort_unless($classroom->isManagedBy($request->user()) || $request->user()->hasPermission('reports.view'), 403);
        $term = Term::find($request->query('term')) ?? Term::current();
        $students = $classroom->students()->with('classroom.homeroomTeacher')->get();

        return view('students.report-card', [
            'term' => $term,
            'cards' => $students->map(fn ($s) => ReportCardController::data($s, $term))->all(),
            'back' => route('preschool.index', ['term' => $term?->id, 'classroom' => $classroom->id]),
            'classroom' => $classroom,
        ]);
    }

    /** แก้ชื่อมาตรฐานให้ตรงกับหลักสูตรสถานศึกษา (ผู้ดูแลงานวิชาการ) */
    public function saveStandards(Request $request)
    {
        abort_unless($request->user()->hasPermission('academics.manage'), 403);
        $data = $request->validate(['standards' => ['array'], 'standards.*' => ['nullable', 'string', 'max:255']]);
        $custom = [];
        foreach (Preschool::STANDARDS as $no => $default) {
            $v = trim((string) ($data['standards'][$no] ?? ''));
            if ($v !== '' && $v !== $default) {
                $custom[$no] = $v;
            }
        }
        $custom ? Settings::set(['preschool_standards' => json_encode($custom, JSON_UNESCAPED_UNICODE)]) : Settings::forget(['preschool_standards']);
        Audit::log('preschool.standards', null, 'แก้ชื่อมาตรฐานปฐมวัย');

        return back()->with('success', 'บันทึกชื่อมาตรฐานแล้ว');
    }

    public function daily(Request $request)
    {
        $date = rescue(fn () => Carbon::parse((string) $request->query('date', today()->toDateString())), today(), false)->startOfDay();
        $term = Term::current();
        $classrooms = $this->classrooms($request, $term?->year);
        $classroom = $classrooms->firstWhere('id', (int) $request->query('classroom')) ?? $classrooms->first();
        $students = $classroom ? $classroom->students()->get() : collect();

        return view('preschool.daily', [
            'date' => $date,
            'classrooms' => $classrooms,
            'classroom' => $classroom,
            'students' => $students,
            'logs' => PreschoolDailyLog::where('date', $date->toDateString())->whereIn('student_id', $students->pluck('id'))->get()->keyBy('student_id'),
        ]);
    }

    public function saveDaily(Request $request, Classroom $classroom)
    {
        abort_unless(Preschool::isPreschoolLevel($classroom->level), 404);
        abort_unless($classroom->isManagedBy($request->user()), 403, 'บันทึกได้เฉพาะครูประจำชั้นของห้องนี้');
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'log' => ['array'],
            'log.*.note' => ['nullable', 'string', 'max:255'],
        ]);
        $date = Carbon::parse($data['date'])->toDateString();
        $input = $request->input('log', []);
        foreach ($classroom->students()->pluck('id') as $studentId) {
            $row = $input[$studentId] ?? [];
            $values = ['note' => trim((string) ($row['note'] ?? '')) ?: null, 'recorded_by' => $request->user()->id];
            foreach (array_keys(Preschool::DAILY) as $k) {
                $values[$k] = ! empty($row[$k]);
            }
            PreschoolDailyLog::updateOrCreate(['date' => $date, 'student_id' => $studentId], $values);
        }

        return redirect()->route('preschool.daily', ['date' => $date, 'classroom' => $classroom->id])
            ->with('success', 'บันทึกประจำวันแล้ว');
    }
}
