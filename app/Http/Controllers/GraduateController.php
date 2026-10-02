<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\DocumentIssue;
use App\Models\Student;
use App\Models\Term;
use App\Support\AcademicRecord;
use App\Support\Curriculum;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/** ปพ.3 แบบรายงานผู้สำเร็จการศึกษา + อนุมัติการจบ (ป.6 / ม.3 / ม.6) */
class GraduateController extends Controller
{
    public function index(Request $request)
    {
        $finals = array_column(Curriculum::STAGES, 'final');
        $level = in_array($request->query('level'), $finals, true) ? $request->query('level') : 'ม.3';
        $year = (int) ($request->query('year') ?: Term::current()?->year);
        $approvedOn = $request->date('approved_on') ?? today();
        $stage = Curriculum::stageOfFinal($level);
        $rows = $this->rows($level, $year, $stage);

        if ($request->query('export') === 'csv') {
            return $this->csv($rows, $level, $year);
        }

        return view('graduates.index', [
            'level' => $level, 'year' => $year, 'approvedOn' => $approvedOn, 'finals' => $finals,
            'stage' => $stage, 'info' => Curriculum::STAGES[$stage], 'rows' => $rows,
            'years' => Classroom::whereIn('level', $finals)->distinct()->orderByDesc('year')->pluck('year'),
        ]);
    }

    /** อนุมัติการจบ: เปลี่ยนสถานะเป็นจบการศึกษา + วันอนุมัติการจบ (เฉพาะคนที่ผ่านเกณฑ์) */
    public function approve(Request $request)
    {
        $data = $request->validate([
            'level' => ['required', Rule::in(array_column(Curriculum::STAGES, 'final'))],
            'year' => ['required', 'integer'],
            'approved_on' => ['required', 'date'],
            'student_ids' => ['array'],
            'student_ids.*' => ['integer'],
        ], [], ['approved_on' => 'วันอนุมัติการจบ']);
        $rows = $this->rows($data['level'], (int) $data['year'], Curriculum::stageOfFinal($data['level']))->keyBy(fn ($r) => $r['student']->id);

        $done = 0;
        $skipped = 0;
        foreach ($data['student_ids'] ?? [] as $id) {
            $row = $rows[$id] ?? null;
            if (! $row || ! $row['record']->eligible()) {
                $skipped++;

                continue;
            }
            $row['student']->update(['status' => 'graduated', 'left_on' => $data['approved_on'], 'leave_reason' => 'จบการศึกษา']);
            $done++;
        }

        return redirect()->route('graduates.index', ['level' => $data['level'], 'year' => $data['year'], 'approved_on' => $data['approved_on']])
            ->with('success', "อนุมัติการจบ {$done} คน".($skipped ? " (ข้าม {$skipped} คนที่ยังไม่ผ่านเกณฑ์)" : ''));
    }

    /** @return Collection<int, array{student: Student, record: AcademicRecord, issue: ?DocumentIssue}> */
    private function rows(string $level, int $year, string $stage): Collection
    {
        $classroomIds = Classroom::where(['level' => $level, 'year' => $year])->pluck('id');
        $issues = DocumentIssue::where('type', 'pp1')->latest('id')->get()->filter(fn ($i) => ($i->snapshot['stage'] ?? null) === $stage)->groupBy('student_id');

        return Student::with('classroom')->whereIn('classroom_id', $classroomIds)->whereIn('status', ['active', 'graduated'])
            ->leftJoin('classrooms', 'classrooms.id', '=', 'students.classroom_id')
            ->orderBy('classrooms.room')->orderBy('students.number')->select('students.*')->get()
            ->map(fn (Student $s) => ['student' => $s, 'record' => new AcademicRecord($s, $stage), 'issue' => $issues[$s->id][0] ?? null]);
    }

    private function csv(Collection $rows, string $level, int $year)
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ที่', 'เลขประจำตัวนักเรียน', 'เลขประจำตัวประชาชน', 'คำนำหน้า', 'ชื่อ', 'นามสกุล', 'เพศ', 'วันเกิด (พ.ศ.)', 'ที่เรียน', 'ที่ได้', 'ผลการเรียนเฉลี่ย', 'ผลการตัดสิน', 'วันอนุมัติการจบ', 'ปพ.1 ชุดที่', 'ปพ.1 เลขที่']);
            foreach ($rows->values() as $i => $r) {
                $s = $r['student'];
                $t = $r['record']->totals()['total'];
                $gpax = $r['record']->gpax();
                fputcsv($out, [
                    $i + 1, $s->student_code, $s->citizen_id, $s->prefix, $s->first_name, $s->last_name,
                    ['M' => 'ชาย', 'F' => 'หญิง'][$s->gender] ?? '', $s->birthdate ? $s->birthdate->format('d/m/').($s->birthdate->year + 543) : '',
                    $t['taken'], $t['earned'], $gpax !== null ? number_format($gpax, 2) : '',
                    $r['record']->eligible() ? 'จบ' : 'ไม่จบ', $s->status === 'graduated' && $s->left_on ? $s->left_on->format('d/m/').($s->left_on->year + 543) : '',
                    $r['issue']?->form_series, $r['issue']?->form_number,
                ]);
            }
            fclose($out);
        }, "ปพ3-{$level}-{$year}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
