<?php

namespace App\Http\Controllers;

use App\Models\DocumentIssue;
use App\Models\Student;
use App\Support\AcademicRecord;
use App\Support\Audit;
use App\Support\Curriculum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** ปพ.1 ระเบียนแสดงผลการเรียน (ป / บ / พ) + ทะเบียนคุมแบบพิมพ์ฉบับจริง */
class TranscriptController extends Controller
{
    public function show(Request $request, Student $student)
    {
        abort_unless($student->canBeViewedBy($request->user()), 403);
        $student->load('classroom');
        $stage = array_key_exists((string) $request->query('stage'), Curriculum::STAGES) ? $request->query('stage') : AcademicRecord::defaultStage($student);
        $record = new AcademicRecord($student, $stage);
        $totals = $record->totals();

        return view('reports.transcript', [
            'student' => $student,
            'stage' => $stage,
            'info' => Curriculum::STAGES[$stage],
            'record' => $record,
            'totals' => $totals,
            'gpax' => $record->gpax(),
            'credits' => $totals['total']['earned'],
            'evaluation' => $record->latestEvaluation(),
            // ระดับอื่นที่มีผลการเรียน (ปุ่มสลับ)
            'stages' => collect(array_keys(Curriculum::STAGES))->filter(fn ($s) => $s === $stage || (new AcademicRecord($student, $s))->rows->isNotEmpty())->values(),
            'issues' => DocumentIssue::where(['type' => 'pp1', 'student_id' => $student->id])->latest('id')->get()
                ->filter(fn ($i) => ($i->snapshot['stage'] ?? null) === $stage)->values(),
        ]);
    }

    /** บันทึกการออก ปพ.1 ฉบับจริง (ชุดที่/เลขที่ของแบบพิมพ์ควบคุม) ลงทะเบียนคุม */
    public function issue(Request $request, Student $student)
    {
        $data = $request->validate([
            'stage' => ['required', Rule::in(array_keys(Curriculum::STAGES))],
            'form_series' => ['required', 'string', 'max:20'],
            'form_number' => ['required', 'string', 'max:20'],
            'purpose' => ['nullable', 'string', 'max:255'],
        ], [], ['form_series' => 'ชุดที่', 'form_number' => 'เลขที่']);

        if (DocumentIssue::where(['type' => 'pp1', 'form_series' => $data['form_series'], 'form_number' => $data['form_number']])->exists()) {
            throw ValidationException::withMessages(['form_number' => "แบบพิมพ์ชุดที่ {$data['form_series']} เลขที่ {$data['form_number']} ถูกใช้ไปแล้ว"]);
        }

        $record = new AcademicRecord($student, $data['stage']);
        $issue = DB::transaction(function () use ($data, $student, $record, $request) {
            $year = today()->year + 543;

            return DocumentIssue::create([
                'type' => 'pp1', 'year' => $year, 'number' => DocumentIssue::nextNumber('pp1', $year),
                'student_id' => $student->id, 'student_name' => $student->fullName(),
                'purpose' => ($data['purpose'] ?? null) ?: 'ออก ปพ.1 '.Curriculum::STAGES[$data['stage']]['code'],
                'issued_on' => today(), 'issued_by' => $request->user()->id,
                'form_series' => $data['form_series'], 'form_number' => $data['form_number'],
                'snapshot' => ['stage' => $data['stage'], 'gpax' => $record->gpax(), 'totals' => $record->totals(), 'status' => $student->status],
            ]);
        });

        Audit::log('document.pp1', $student, "ใช้แบบพิมพ์ ปพ.1 ชุดที่ {$issue->form_series} เลขที่ {$issue->form_number} ให้ {$student->fullName()} ({$issue->purpose})");

        return redirect()->route('transcript', ['student' => $student, 'stage' => $data['stage']])
            ->with('success', "บันทึกการออก ปพ.1 ชุดที่ {$issue->form_series} เลขที่ {$issue->form_number} ลงทะเบียนคุมแล้ว");
    }
}
