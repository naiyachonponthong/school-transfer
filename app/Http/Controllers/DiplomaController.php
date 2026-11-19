<?php

namespace App\Http\Controllers;

use App\Models\DocumentIssue;
use App\Models\Student;
use App\Support\Audit;
use App\Support\Curriculum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * ปพ.2 ประกาศนียบัตร: ออกให้นักเรียนที่อนุมัติการจบแล้ว เลขที่รันต่อปี (ไม่ซ้ำ) และเก็บในทะเบียนคุมเดียวกับ ปพ.1 / ปพ.7
 * ฉบับจริงต้องพิมพ์ลงแบบพิมพ์ของกระทรวง: โหมด overlay พิมพ์เฉพาะข้อความ และเลื่อนตำแหน่งได้ที่ ตั้งค่า > ข้อมูลโรงเรียน
 */
class DiplomaController extends Controller
{
    public function issue(Request $request)
    {
        $data = $request->validate([
            'level' => ['required', Rule::in(array_column(Curriculum::STAGES, 'final'))],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer'],
        ], ['student_ids.required' => 'เลือกนักเรียนที่จะออก ปพ.2 อย่างน้อย 1 คน']);
        $stage = Curriculum::stageOfFinal($data['level']);

        $issues = collect();
        $skipped = 0;
        foreach (Student::whereIn('id', $data['student_ids'])->get() as $student) {
            if ($student->status !== 'graduated') {
                $skipped++; // ต้องอนุมัติการจบก่อน

                continue;
            }
            // 1 คน 1 ระดับ ออกครั้งเดียว ขอพิมพ์ซ้ำได้จากเลขเดิม
            $existing = DocumentIssue::where(['type' => 'pp2', 'student_id' => $student->id])->get()->first(fn ($i) => ($i->snapshot['stage'] ?? null) === $stage);
            $issues->push($existing ?? DB::transaction(function () use ($student, $stage, $request) {
                $year = today()->year + 543;

                return DocumentIssue::create([
                    'type' => 'pp2', 'year' => $year, 'number' => DocumentIssue::nextNumber('pp2', $year),
                    'student_id' => $student->id, 'student_name' => $student->fullName(), 'purpose' => 'ประกาศนียบัตร '.Curriculum::STAGES[$stage]['name'],
                    'issued_on' => today(), 'snapshot' => self::snapshot($student, $stage), 'issued_by' => $request->user()->id,
                ]);
            }));
        }
        if ($issues->isEmpty()) {
            return back()->withErrors(['student_ids' => 'ออก ปพ.2 ได้เฉพาะนักเรียนที่อนุมัติการจบแล้ว']);
        }
        $new = $issues->filter(fn ($i) => $i->wasRecentlyCreated);
        if ($new->isNotEmpty()) {
            Audit::log('document.pp2', null, 'ออก ปพ.2 ระดับ'.Curriculum::STAGES[$stage]['name'].' '.$new->count().' ฉบับ เลขที่ '.$new->map->code()->implode(', '));
        }

        return redirect()->route('diplomas.print', ['ids' => $issues->pluck('id')->implode(',')])
            ->with('success', 'ออก ปพ.2 ใหม่ '.$new->count().' ฉบับ'.($issues->count() > $new->count() ? ' (พิมพ์ซ้ำเลขเดิม '.($issues->count() - $new->count()).' ฉบับ)' : '').($skipped ? ' ข้าม '.$skipped.' คนที่ยังไม่อนุมัติการจบ' : ''));
    }

    public function print(Request $request)
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $request->query('ids'))));
        $issues = DocumentIssue::where('type', 'pp2')->whereIn('id', $ids)->orderBy('number')->get();
        abort_if($issues->isEmpty(), 404);

        return view('graduates.pp2', [
            'issues' => $issues,
            'overlay' => $request->query('mode') === 'overlay',
            'dx' => (float) \App\Support\Settings::get('pp2_offset_x', 0),
            'dy' => (float) \App\Support\Settings::get('pp2_offset_y', 0),
        ]);
    }

    /** ข้อมูลที่พิมพ์ลงประกาศนียบัตร ณ วันที่ออก */
    public static function snapshot(Student $student, string $stage): array
    {
        return [
            'stage' => $stage,
            'stage_name' => Curriculum::STAGES[$stage]['name'],
            'name' => $student->fullName(),
            'student_code' => $student->student_code,
            'citizen_id' => $student->citizen_id,
            'birthdate' => $student->birthdate?->toDateString(),
            'graduated_on' => $student->left_on?->toDateString(),
            'photo' => $student->photo,
            'director' => (string) \App\Support\Settings::get('director_name', ''),
        ];
    }
}
