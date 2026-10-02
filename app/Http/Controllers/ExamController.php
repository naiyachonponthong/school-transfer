<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Exam;
use App\Models\Score;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * ตรวจข้อสอบ (ScanGrade ในระบบโรงเรียน)
 * ชุดข้อสอบ → เฉลย → พิมพ์กระดาษคำตอบ → สแกนด้วยมือถือ → ตรวจทาน → ตารางคะแนน/ส่งเข้าสมุดคะแนน → วิเคราะห์ข้อสอบแบบ EVANA
 */
class ExamController extends Controller
{
    private function authorizeExam(Request $request, Exam $exam): void
    {
        abort_unless($exam->canManage($request->user()), 403, 'ชุดข้อสอบนี้ไม่ได้อยู่ในความรับผิดชอบของคุณ');
    }

    /** รายวิชาที่ผู้ใช้สร้างชุดข้อสอบได้ (ภาคเรียนปัจจุบัน) */
    private function courses(Request $request)
    {
        $user = $request->user();
        $term = Term::current();

        return Course::with(['subject', 'classroom', 'teacher'])
            ->when($term, fn ($q) => $q->where('term_id', $term->id))
            ->when(! $user->isAdmin(), fn ($q) => $q->where('teacher_id', $user->id))
            ->get()->sortBy(fn ($c) => [$c->subject->code, $c->classroom->level_order, $c->classroom->room])->values();
    }

    public function index(Request $request)
    {
        // ชุดข้อสอบคัดเลือกจัดการจากหน้าสอบคัดเลือก
        $exams = Exam::managedBy($request->user())->whereNull('admission_round_id')->with(['subject', 'courses.classroom'])
            ->withCount(['responses as sheets_count' => fn ($q) => $q->where('status', '!=', 'void'),
                'responses as review_count' => fn ($q) => $q->where('status', 'review')])
            ->latest()->paginate(20);

        return view('exams.index', ['exams' => $exams, 'courses' => $this->courses($request)]);
    }

    public function store(Request $request)
    {
        $data = $this->validateExam($request);
        $courses = Course::whereIn('id', $data['course_ids'])->get();
        abort_unless($courses->every(fn ($c) => $c->canEdit($request->user())), 403);
        abort_if($courses->pluck('subject_id')->unique()->count() > 1, 422, 'เลือกได้เฉพาะห้องที่เรียนวิชาเดียวกัน');

        $exam = DB::transaction(function () use ($data, $courses, $request) {
            $exam = Exam::create([
                'term_id' => $courses->first()->term_id, 'subject_id' => $courses->first()->subject_id,
                'title' => $data['title'], 'n_items' => $data['n_items'], 'exam_date' => $data['exam_date'] ?? null,
                'answer_key' => [], 'cancelled' => [], 'created_by' => $request->user()->id,
            ]);
            $exam->courses()->sync($courses->pluck('id'));

            return $exam;
        });

        return redirect()->route('exams.show', $exam)->with('success', 'สร้างชุดข้อสอบแล้ว ใส่เฉลยได้เลย');
    }

    private function validateExam(Request $request, ?Exam $exam = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'n_items' => ['required', 'integer', 'between:1,'.Exam::MAX_ITEMS],
            'exam_date' => ['nullable', 'date'],
            'course_ids' => [$exam ? 'sometimes' : 'required', 'array', 'min:1'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
        ], ['course_ids.required' => 'เลือกห้องที่สอบอย่างน้อย 1 ห้อง']);
    }

    /** หน้าหลักของชุดข้อสอบ = ใส่เฉลย */
    public function show(Request $request, Exam $exam)
    {
        $this->authorizeExam($request, $exam);
        $exam->load(['subject', 'courses.classroom', 'courses.assessments']);
        $counts = $exam->responses()->select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status');

        return view('exams.show', [
            'exam' => $exam, 'counts' => $counts,
            'courses' => $this->courses($request)->where('subject_id', $exam->subject_id)->values(),
            'assessmentNames' => $exam->courses->flatMap(fn ($c) => $c->assessments->pluck('name'))->unique()->values(),
        ]);
    }

    public function update(Request $request, Exam $exam)
    {
        $this->authorizeExam($request, $exam);
        $data = $this->validateExam($request, $exam) + $request->validate([
            'assessment_name' => ['nullable', 'string', 'max:255'],
            'published' => ['nullable', 'boolean'],
        ]);
        $data['n_items'] = (int) $data['n_items'];
        if ($data['n_items'] !== $exam->n_items && $exam->responses()->exists()) {
            return back()->with('warning', 'ชุดนี้มีกระดาษคำตอบที่สแกนแล้ว เปลี่ยนจำนวนข้อไม่ได้');
        }
        if (isset($data['course_ids'])) {
            $courses = Course::whereIn('id', $data['course_ids'])->where('subject_id', $exam->subject_id)->where('term_id', $exam->term_id)->get();
            abort_unless($courses->isNotEmpty() && ($request->user()->isAdmin() || $courses->every(fn ($c) => $c->canEdit($request->user()) || $exam->courses->contains($c))), 403);
            $exam->courses()->sync($courses->pluck('id'));
        }
        $exam->update([
            'title' => $data['title'], 'n_items' => $data['n_items'], 'exam_date' => $data['exam_date'] ?? null,
            'assessment_name' => $data['assessment_name'] ?? $exam->assessment_name,
            'published' => $request->boolean('published'),
        ] + ($exam->isAdmission() ? $request->validate([
            'subject_name' => ['required', 'string', 'max:100'],
            'weight' => ['required', 'numeric', 'gt:0', 'max:100'],
        ]) : []));

        return back()->with('success', 'บันทึกชุดข้อสอบแล้ว');
    }

    /** บันทึกเฉลย + ตรวจใหม่ทุกแผ่นอัตโนมัติ (แก้เฉลยผิดทีหลังได้) */
    public function saveKey(Request $request, Exam $exam)
    {
        $this->authorizeExam($request, $exam);
        $data = $request->validate([
            'key' => ['present', 'array'],
            'key.*' => ['nullable', 'string', 'max:4'],
            'cancelled' => ['array'],
            'cancelled.*' => ['integer', 'between:1,'.$exam->n_items],
            'cancel_mode' => ['nullable', Rule::in(['give', 'drop'])],
            'points' => ['nullable', 'numeric', 'gt:0', 'max:100'],
        ]);
        $regraded = DB::transaction(function () use ($exam, $data) {
            $exam->update([
                'answer_key' => Exam::cleanKey($data['key'], $exam->n_items),
                'cancelled' => array_values(array_unique(array_map('intval', $data['cancelled'] ?? []))),
                'cancel_mode' => $data['cancel_mode'] ?? 'give',
                'points' => $data['points'] ?? 1,
                'key_version' => $exam->key_version + 1,
            ]);

            return $exam->regrade();
        });
        $missing = collect($exam->key())->filter(fn ($k, $i) => $k === '' && ! in_array($i + 1, $exam->cancelledItems(), true))->count();
        $msg = ($missing ? "บันทึกแล้ว (ยังขาดเฉลย {$missing} ข้อ)" : 'บันทึกเฉลยแล้ว').($regraded ? " · ตรวจใหม่ {$regraded} แผ่น" : '');

        return $request->expectsJson()
            ? response()->json(['message' => $msg, 'key_ready' => $exam->keyReady(), 'regraded' => $regraded])
            : back()->with('success', $msg);
    }

    public function destroy(Request $request, Exam $exam)
    {
        $this->authorizeExam($request, $exam);
        // แผ่นคำตอบถูกลบด้วย cascade ของฐานข้อมูล ส่วนภาพสแกนต้องลบโฟลเดอร์เอง
        Storage::disk('local')->deleteDirectory('scans/'.$exam->id);
        $exam->delete();

        return ($exam->isAdmission() ? redirect()->route('admission-exams.show', [$exam->admission_round_id, 'tab' => 'subjects']) : redirect()->route('exams.index'))
            ->with('success', 'ลบชุดข้อสอบแล้ว');
    }

    /** ใช้ข้อสอบ + เฉลยเดิมกับห้อง/เทอมใหม่ (ไม่คัดลอกผลตรวจ) */
    public function duplicate(Request $request, Exam $exam)
    {
        $this->authorizeExam($request, $exam);
        $copy = $exam->replicate(['key_version', 'published', 'assessment_name']);
        $copy->fill(['title' => $exam->title.' (สำเนา)', 'exam_date' => null, 'key_version' => 1, 'created_by' => $request->user()->id])->save();
        $copy->courses()->sync($exam->courses->filter(fn ($c) => $c->canEdit($request->user()))->pluck('id'));

        return redirect()->route('exams.show', $copy)->with('success', 'คัดลอกชุดข้อสอบแล้ว — เลือกห้องที่สอบใหม่ได้ที่ "แก้ไข"');
    }

    /** พิมพ์กระดาษคำตอบ: แผ่นเปล่า หรือรายบุคคลทั้งห้อง (ระบายรหัส + เลขที่ให้แล้ว) */
    public function sheets(Request $request, Exam $exam)
    {
        $this->authorizeExam($request, $exam);
        $exam->load('subject', 'courses.classroom');
        // สอบคัดเลือก: ไม่ใช้กลุ่ม ก/ข · ช่อง "ชั้น" บนกระดาษพิมพ์ห้องสอบ
        $students = $exam->takers()->map(fn ($t) => [
            'name' => $t->name, 'code' => $t->code, 'seat_no' => $t->seat, 'seat_group' => $t->seat !== '' && ! $exam->isAdmission() ? 'ก' : '',
            'classroom' => $t->room, 'classroom_id' => $t->roomKey,
        ])->values();

        return view('exams.sheets', ['exam' => $exam, 'students' => $students, 'rooms' => $exam->takerRooms()]);
    }

    /** ผลตรวจ: คิวตรวจทาน · ทุกแผ่น · ตารางคะแนน */
    public function results(Request $request, Exam $exam)
    {
        $this->authorizeExam($request, $exam);
        $exam->load('subject', 'courses.classroom');
        $responses = $exam->responses()->with(['student.classroom', 'application', 'scanner'])->orderByDesc('scanned_at')->get();
        $active = $responses->where('status', '!=', 'void');
        $col = $exam->takerColumn();
        $byTaker = $active->whereNotNull($col)->groupBy($col);

        $rows = $exam->takers()->map(fn ($t) => ['taker' => $t, 'response' => $byTaker->get($t->id)?->sortBy(fn ($r) => $r->status === 'ok' ? 0 : 1)->first()]);
        $scores = $active->where('status', 'ok')->whereNotNull($col)->pluck('score')->filter(fn ($v) => $v !== null)->sort()->values();

        return view('exams.results', [
            'exam' => $exam, 'tab' => $request->query('tab', $active->where('status', 'review')->isNotEmpty() ? 'review' : 'scores'),
            'responses' => $responses, 'rows' => $rows, 'stats' => self::stats($scores),
            'assessmentNames' => $exam->courses()->with('assessments')->get()->flatMap(fn ($c) => $c->assessments->pluck('name'))->unique()->values(),
        ]);
    }

    public static function stats($scores): ?array
    {
        $n = $scores->count();
        if (! $n) {
            return null;
        }
        $mean = $scores->avg();

        return [
            'n' => $n, 'mean' => $mean, 'max' => $scores->max(), 'min' => $scores->min(),
            'median' => $n % 2 ? $scores[intdiv($n, 2)] : ($scores[$n / 2 - 1] + $scores[$n / 2]) / 2,
            'sd' => sqrt($scores->sum(fn ($s) => ($s - $mean) ** 2) / $n),
        ];
    }

    /**
     * ส่งออก CSV (Excel เปิดภาษาไทยได้): ?format=scores ตารางคะแนน · ?format=evana แถว KEY + คำตอบรายคน (รหัส เลขที่+ห้อง)
     */
    public function export(Request $request, Exam $exam)
    {
        $this->authorizeExam($request, $exam);
        $exam->load('subject');
        $evana = $request->query('format') === 'evana';
        $col = $exam->takerColumn();
        $responses = $exam->responses()->where('status', 'ok')->whereNotNull($col)->get()->keyBy($col);
        $students = $exam->takers();
        $name = ($exam->subject->code ?? $exam->subjectLabel()).'_'.$exam->title.($evana ? '_EVANA' : '').'.csv';
        $admission = $exam->isAdmission();

        return response()->streamDownload(function () use ($exam, $evana, $responses, $students, $admission) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            if ($evana) {
                fputcsv($out, array_merge(['ID'], range(1, $exam->n_items)));
                fputcsv($out, array_merge(['KEY'], array_map(fn ($k) => $k === '' ? '' : $k, $exam->key())));
                foreach ($students as $s) {
                    if ($r = $responses->get($s->id)) {
                        // รหัสผู้สอบแบบ EVANA = เลขที่ + ห้อง เช่น 7ม11
                        fputcsv($out, array_merge([$admission ? $s->code : $s->evanaId()], str_split($r->answers)));
                    }
                }
            } else {
                fputcsv($out, $admission ? ['ห้องสอบ', 'เลขที่นั่ง', 'เลขประจำตัวสอบ', 'ชื่อ-สกุล', 'คะแนน', 'คะแนนเต็ม', 'สถานะ']
                    : ['ห้อง', 'เลขที่', 'เลขประจำตัว', 'ชื่อ-สกุล', 'คะแนน', 'คะแนนเต็ม', 'สถานะ']);
                foreach ($students as $s) {
                    $r = $responses->get($s->id);
                    fputcsv($out, [$s->room, $s->seat, $s->code, $s->name, $r?->score, $r?->max_score, $r ? 'ตรวจแล้ว' : ($admission ? 'ขาดสอบ / ไม่มีผล' : 'ไม่มีผล')]);
                }
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** ส่งคะแนนเข้าช่องคะแนนในสมุดคะแนนของทุกห้อง (แปลงสัดส่วนตามคะแนนเต็มของช่อง) */
    public function sync(Request $request, Exam $exam)
    {
        $this->authorizeExam($request, $exam);
        abort_if($exam->isAdmission(), 422, 'ชุดข้อสอบคัดเลือกไม่มีสมุดคะแนน');
        $data = $request->validate(['assessment_name' => ['required', 'string', 'max:255']]);
        $exam->update(['assessment_name' => $data['assessment_name']]);
        $responses = $exam->responses()->where('status', 'ok')->whereNotNull('student_id')->whereNotNull('score')->get()->keyBy('student_id');

        $done = 0;
        $skipped = [];
        foreach ($exam->courses()->with(['classroom', 'assessments'])->get() as $course) {
            $a = $course->assessments->firstWhere('name', $data['assessment_name']);
            if (! $a || ! $course->canEdit($request->user()) || ($course->locked && ! $request->user()->isAdmin())) {
                $skipped[] = $course->classroom->name();

                continue;
            }
            foreach ($course->classroom->students()->pluck('id') as $sid) {
                if (($r = $responses->get($sid)) && $r->max_score > 0) {
                    Score::updateOrCreate(['assessment_id' => $a->id, 'student_id' => $sid], ['score' => round($r->score / $r->max_score * $a->max_score, 2)]);
                    $done++;
                }
            }
        }

        return back()->with($done ? 'success' : 'warning', "ส่งคะแนน {$done} คนเข้าช่อง \"{$data['assessment_name']}\" แล้ว"
            .($skipped ? ' · ข้ามห้อง '.implode(', ', $skipped).' (ไม่มีช่องนี้/ล็อกแล้ว/ไม่ได้สอน)' : ''));
    }

    /** วิเคราะห์ข้อสอบแบบ EVANA — คำนวณในเบราว์เซอร์ด้วย evana.js (ตัวเดียวกับ ScanGrade ที่ตรวจกับผลจริงแล้ว) */
    public function analysis(Request $request, Exam $exam)
    {
        $this->authorizeExam($request, $exam);
        $exam->load('subject', 'courses.classroom', 'creator');
        $col = $exam->takerColumn();
        $all = $exam->responses()->where('status', '!=', 'void')->get();
        // เรียงตามห้อง → เลขที่ (EVANA ใช้ลำดับนี้ตัดสินตอนคะแนนเท่ากันที่รอยตัดกลุ่ม)
        $takers = $exam->takers()->keyBy('id');
        $order = $takers->keys()->flip();
        $papers = $all->where('status', 'ok')->filter(fn ($r) => $r->{$col} && $takers->has($r->{$col}))
            ->sortBy(fn ($r) => $order[$r->{$col}])->values()
            ->map(fn ($r) => ['id' => $exam->isAdmission() ? $takers[$r->{$col}]->code : $takers[$r->{$col}]->evanaId(), 'room' => $takers[$r->{$col}]->roomKey, 'answers' => $r->answers]);

        return view('exams.analysis', [
            'exam' => $exam, 'papers' => $papers, 'pendingReview' => $all->where('status', 'review')->count(),
            'rooms' => $exam->takerRooms(),
        ]);
    }
}
