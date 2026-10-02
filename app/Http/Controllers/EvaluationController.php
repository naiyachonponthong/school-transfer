<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\StudentEvaluation;
use App\Models\Term;
use App\Support\Evaluation;
use Illuminate\Http\Request;

/** บันทึกคุณลักษณะอันพึงประสงค์ 8 ข้อ + อ่าน คิดวิเคราะห์ และเขียน ทั้งห้อง (ครูประจำชั้น/ผู้ดูแล) */
class EvaluationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $term = Term::find($request->query('term')) ?? Term::current();
        $terms = Term::orderByDesc('year')->orderByDesc('term')->get();

        $classrooms = ! $term ? collect() : Classroom::where('year', $term->year)
            ->when(! $user->isAdmin(), fn ($q) => $q->where(fn ($w) => $w->where('homeroom_teacher_id', $user->id)->orWhere('co_teacher_id', $user->id)))
            ->ordered()->get();
        $classroom = $classrooms->firstWhere('id', (int) $request->query('classroom')) ?? $classrooms->first();

        $students = $classroom ? $classroom->students()->get() : collect();
        $evaluations = $term && $classroom
            ? StudentEvaluation::where('term_id', $term->id)->whereIn('student_id', $students->pluck('id'))->get()->keyBy('student_id')
            : collect();

        return view('evaluations.index', compact('term', 'terms', 'classrooms', 'classroom', 'students', 'evaluations'));
    }

    public function save(Request $request, Classroom $classroom)
    {
        abort_unless($classroom->isManagedBy($request->user()), 403, 'บันทึกได้เฉพาะครูประจำชั้นของห้องนี้');
        $levels = implode(',', array_keys(Evaluation::LEVELS));
        $data = $request->validate([
            'term_id' => ['required', 'exists:terms,id'],
            'eval' => ['array'],
            'eval.*.t' => ['array'],
            'eval.*.t.*' => ['nullable', 'in:'.$levels],
            'eval.*.rtw' => ['nullable', 'in:'.$levels],
        ]);
        $term = Term::findOrFail($data['term_id']);
        abort_unless((int) $term->year === (int) $classroom->year, 422, 'ภาคเรียนไม่ตรงกับปีการศึกษาของห้อง');

        $input = $data['eval'] ?? [];
        $saved = 0;
        foreach ($classroom->students()->pluck('id') as $studentId) {
            if (! array_key_exists($studentId, $input)) {
                continue;
            }
            $row = $input[$studentId];
            $traits = [];
            foreach (array_keys(Evaluation::TRAITS) as $no) {
                $v = $row['t'][$no] ?? null;
                $traits[$no] = $v === null || $v === '' ? null : (int) $v;
            }
            $rtw = ($row['rtw'] ?? null) === null || $row['rtw'] === '' ? null : (int) $row['rtw'];

            $key = ['term_id' => $term->id, 'student_id' => $studentId];
            if ($rtw === null && ! array_filter($traits, fn ($v) => $v !== null)) {
                StudentEvaluation::where($key)->delete();

                continue;
            }
            StudentEvaluation::updateOrCreate($key, ['traits' => $traits, 'rtw' => $rtw, 'recorded_by' => $request->user()->id]);
            $saved++;
        }

        return redirect()->route('evaluations.index', ['term' => $term->id, 'classroom' => $classroom->id])
            ->with('success', "บันทึกผลประเมินแล้ว {$saved} คน");
    }
}
