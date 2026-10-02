<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * สแกนกระดาษคำตอบ: หน้ากล้องในระบบ (ไม่ต้องติดตั้งแอป/ไม่ต้องขึ้น GitHub Pages แบบ ScanGrade เดิม)
 * ตัวอ่าน (omr.js) ทำงานในเครื่อง → ส่งผลเข้ามาทีละไม่เกิน 10 แผ่น · ออฟไลน์ได้ (คิวในมือถือ)
 */
class ExamScanController extends Controller
{
    public const BATCH_MAX = 10;

    public const IMAGE_MAX_BYTES = 600 * 1024;

    private function authorizeExam(Request $request, Exam $exam): void
    {
        abort_unless($exam->canManage($request->user()), 403, 'ชุดข้อสอบนี้ไม่ได้อยู่ในความรับผิดชอบของคุณ');
    }

    public static function normCode(?string $code): string
    {
        $digits = preg_replace('/\D/', '', (string) $code);

        return $digits === '' ? '' : (ltrim($digits, '0') ?: '0');
    }

    public function scan(Request $request, Exam $exam)
    {
        $this->authorizeExam($request, $exam);
        $exam->load('subject');

        return view('exams.scan', ['exam' => $exam, 'roster' => $this->rosterData($exam)]);
    }

    /** รายชื่อ + เฉลยสำหรับหน้าสแกน (เก็บไว้ในเครื่อง ใช้ตอนออฟไลน์) */
    public function roster(Request $request, Exam $exam)
    {
        $this->authorizeExam($request, $exam);

        return response()->json($this->rosterData($exam));
    }

    private function rosterData(Exam $exam): array
    {
        $exam->loadMissing('subject');

        return [
            'exam' => [
                'id' => $exam->id, 'title' => $exam->title, 'subject' => $exam->subjectLabel(), 'n_items' => $exam->n_items,
                'key' => $exam->key(), 'cancelled' => $exam->cancelledItems(), 'cancel_mode' => $exam->cancel_mode,
                'points' => $exam->points, 'key_ready' => $exam->keyReady(),
            ],
            'groups' => Exam::SEAT_GROUPS,
            'students' => $exam->takers()->map(fn ($t) => [
                'id' => $t->id, 'code' => self::normCode($t->code), 'seat' => $t->seat, 'name' => $t->name, 'room' => $t->room,
            ])->values(),
            'scanned' => $exam->responses()->whereIn('status', ['ok', 'review'])->whereNotNull($exam->takerColumn())->pluck($exam->takerColumn())->unique()->values(),
        ];
    }

    /**
     * รับผลสแกน · item = {request_id, student_code, seat, answers, confidence, flags[], review, image (data:image/jpeg), scanned_at, source}
     * ส่งซ้ำด้วย request_id เดิม = คืนผลเดิม ไม่บันทึกซ้ำ
     */
    public function submit(Request $request, Exam $exam)
    {
        $this->authorizeExam($request, $exam);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:'.self::BATCH_MAX],
            'items.*.request_id' => ['required', 'string', 'max:80'],
            'items.*.answers' => ['required', 'string', 'size:'.$exam->n_items, 'regex:/^[0-49]+$/'],
            'items.*.student_code' => ['nullable', 'string', 'max:8'],
            'items.*.seat' => ['nullable', 'string', 'max:8'],
            'items.*.confidence' => ['nullable', 'numeric'],
            'items.*.flags' => ['nullable', 'array', 'max:120'],
            'items.*.flags.*' => ['string', 'max:40'],
            'items.*.review' => ['nullable', 'boolean'],
            'items.*.image' => ['nullable', 'string'],
            'items.*.scanned_at' => ['nullable', 'date'],
            'items.*.source' => ['nullable', 'in:camera,photo'],
        ]);

        $col = $exam->takerColumn();
        $students = $exam->takers()->keyBy(fn ($t) => self::normCode($t->code));
        $results = DB::transaction(function () use ($data, $exam, $students, $request, $col) {
            $existing = ExamResponse::whereIn('request_id', collect($data['items'])->pluck('request_id'))->get()->keyBy('request_id');
            $taken = $exam->responses()->whereIn('status', ['ok', 'review'])->whereNotNull($col)->pluck('id', $col);
            $out = [];
            foreach ($data['items'] as $it) {
                if ($prev = $existing->get($it['request_id'])) {
                    $out[] = $this->result($prev) + ['duplicate_request' => true];

                    continue;
                }
                $flags = array_values(array_filter($it['flags'] ?? [], fn ($f) => ! str_starts_with($f, 'blank:')));
                $code = preg_replace('/[^0-9?*]/', '', (string) ($it['student_code'] ?? ''));
                $st = preg_match('/^\d+$/', $code) ? $students->get(self::normCode($code)) : null;
                if (! $st) {
                    $flags[] = 'unknown_code';
                }
                $seat = (string) ($it['seat'] ?? '');
                if ($st && $st->seat !== '' && preg_match('/^(\d+)/', $seat, $m) && (int) $m[1] !== (int) $st->seat) {
                    $flags[] = 'seat_mismatch';
                }
                if ($st && isset($taken[$st->id])) {
                    $flags[] = 'duplicate';
                }
                $review = ! empty($it['review']) || collect($flags)->contains(fn ($f) => preg_match('/^(unknown_code|seat_mismatch|duplicate|low_conf|multi|warp|n_mismatch|code_incomplete)/', $f));
                $sc = $exam->score($it['answers']);
                $r = $exam->responses()->create([
                    $col => $st?->id, 'request_id' => $it['request_id'], 'answers' => $it['answers'],
                    'score' => $sc['score'], 'max_score' => $sc['max'], 'status' => $review ? 'review' : 'ok',
                    'flags' => array_values(array_unique($flags)), 'confidence' => round((float) ($it['confidence'] ?? 0), 3),
                    'code_read' => substr($code, 0, 8), 'seat_read' => mb_substr($seat, 0, 8), 'source' => $it['source'] ?? 'camera',
                    'key_version' => $exam->key_version, 'scanned_by' => $request->user()->id,
                    'scanned_at' => isset($it['scanned_at']) ? Carbon::parse($it['scanned_at'])->setTimezone(config('app.timezone')) : now(),
                ]);
                if (! empty($it['image']) && ($path = $this->storeImage($exam, $r, $it['image']))) {
                    $r->update(['image' => $path]);
                }
                if ($st) {
                    $taken[$st->id] = $r->id;
                }
                $out[] = $this->result($r);
            }

            return $out;
        });

        return response()->json(['results' => $results]);
    }

    private function result(ExamResponse $r): array
    {
        $r->loadMissing('student.classroom', 'application');
        $t = $r->taker();

        return [
            'request_id' => $r->request_id, 'response_id' => $r->id, 'status' => $r->status, 'score' => $r->score, 'max' => $r->max_score,
            'reasons' => $r->reasons(),
            'student' => $t ? ['id' => $t->id, 'name' => $t->name, 'seat' => $t->seat, 'room' => $t->room] : null,
        ];
    }

    /** เก็บภาพดัดตรง (JPEG) บนดิสก์ส่วนตัว — ไฟล์เสีย/ใหญ่เกินข้ามไป ไม่ทำให้การบันทึกผลล้ม */
    private function storeImage(Exam $exam, ExamResponse $r, string $dataUrl): ?string
    {
        if (! preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            return null;
        }
        $bytes = base64_decode($m[1], true);
        if ($bytes === false || strlen($bytes) > self::IMAGE_MAX_BYTES || ! @getimagesizefromstring($bytes)) {
            return null;
        }
        $path = "scans/{$exam->id}/{$r->id}-".Str::random(8).'.jpg';
        Storage::disk('local')->put($path, $bytes);

        return $path;
    }

    public function image(Request $request, Exam $exam, ExamResponse $response)
    {
        $this->authorizeExam($request, $exam);
        abort_unless($response->exam_id === $exam->id && $response->image && Storage::disk('local')->exists($response->image), 404);

        return response()->file(Storage::disk('local')->path($response->image), ['Cache-Control' => 'private, max-age=86400']);
    }

    /** ตรวจทานทีละแผ่น: ภาพจริง + วงที่ระบบอ่านได้ แก้คำตอบ/ระบุเจ้าของ */
    public function review(Request $request, Exam $exam, ExamResponse $response)
    {
        $this->authorizeExam($request, $exam);
        abort_unless($response->exam_id === $exam->id, 404);
        $exam->load('subject');
        $response->load('student.classroom', 'application', 'scanner');
        $queue = $exam->responses()->where('status', 'review')->orderBy('scanned_at')->pluck('id');
        $col = $exam->takerColumn();
        $taken = $exam->responses()->whereIn('status', ['ok', 'review'])->where('id', '!=', $response->id)->whereNotNull($col)->pluck('id', $col);

        return view('exams.review', [
            'exam' => $exam, 'r' => $response, 'queue' => $queue, 'position' => $queue->search($response->id),
            'students' => $exam->takers()->map(fn ($t) => ['id' => $t->id, 'label' => $t->label(), 'taken' => $taken->get($t->id)])->values(),
        ]);
    }

    public function update(Request $request, Exam $exam, ExamResponse $response)
    {
        $this->authorizeExam($request, $exam);
        abort_unless($response->exam_id === $exam->id, 404);
        $user = $request->user();
        $action = $request->input('action', 'save');

        if (in_array($action, ['void', 'restore'], true)) {
            $response->logEdit($user, $action === 'void' ? 'ยกเลิกแผ่นนี้' : 'นำกลับมา');
            $response->fill(['status' => $action === 'void' ? 'void' : 'review'])->save();

            return $this->next($exam, $response, $action === 'void' ? 'ยกเลิกแผ่นนี้แล้ว' : 'นำกลับมาแล้ว — ตรวจทานอีกครั้ง');
        }

        $data = $request->validate([
            'answers' => ['required', 'string', 'size:'.$exam->n_items, 'regex:/^[0-49]+$/'],
            'student_id' => ['nullable', 'integer'],
            'replace' => ['nullable', 'boolean'],
        ]);
        $sid = (int) ($data['student_id'] ?? 0);
        $col = $exam->takerColumn();
        $student = $sid ? $exam->takers()->firstWhere('id', $sid) : null;
        if ($sid && ! $student) {
            return back()->withErrors(['student_id' => $exam->isAdmission() ? 'ผู้สมัครคนนี้ไม่ได้อยู่ในรายชื่อผู้เข้าสอบ' : 'นักเรียนคนนี้ไม่ได้อยู่ในห้องที่สอบ']);
        }
        if (! $student) {
            return back()->withErrors(['student_id' => 'ระบุเจ้าของแผ่นก่อนบันทึก (พิมพ์เลขประจำตัวหรือชื่อ)'])->withInput();
        }
        $other = $exam->responses()->whereIn('status', ['ok', 'review'])->where($col, $student->id)->where('id', '!=', $response->id)->first();
        if ($other && ! $request->boolean('replace')) {
            return back()->withInput()->with('replace_prompt', "{$student->name} มีแผ่นอื่นอยู่แล้ว (คะแนน {$other->score}) — กดบันทึกอีกครั้งเพื่อใช้แผ่นนี้แทน แผ่นเดิมจะถูกยกเลิก (ไม่ลบ)");
        }

        DB::transaction(function () use ($exam, $response, $data, $student, $other, $user, $col) {
            if ($other) {
                $other->logEdit($user, 'ยกเลิก เพราะใช้แผ่น #'.$response->id.' แทน');
                $other->fill(['status' => 'void'])->save();
            }
            $changes = [];
            if ($response->answers !== $data['answers']) {
                $diff = collect(str_split($data['answers']))->filter(fn ($c, $i) => ($response->answers[$i] ?? '') !== $c)->keys()->map(fn ($i) => $i + 1);
                $changes[] = 'แก้คำตอบข้อ '.$diff->implode(', ');
            }
            if ($response->{$col} !== $student->id) {
                $changes[] = 'ระบุเจ้าของ '.$student->name;
            }
            $sc = $exam->score($data['answers']);
            $response->logEdit($user, $changes ? implode(' · ', $changes) : 'ยืนยันผล');
            $response->fill([
                'answers' => $data['answers'], $col => $student->id, 'score' => $sc['score'], 'max_score' => $sc['max'],
                'status' => 'ok', 'reviewed_by' => $user->id, 'reviewed_at' => now(),
            ])->save();
        });

        return $this->next($exam, $response, 'บันทึกแล้ว · '.$student->name.' ได้ '.rtrim(rtrim(number_format($response->score, 2), '0'), '.').' คะแนน');
    }

    /** ไปแผ่นถัดไปในคิวตรวจทาน (หมดคิว → กลับหน้าผลตรวจ) */
    private function next(Exam $exam, ExamResponse $current, string $msg)
    {
        $next = $exam->responses()->where('status', 'review')->where('id', '!=', $current->id)->orderBy('scanned_at')->first();

        return $next
            ? redirect()->route('exams.review', [$exam, $next])->with('success', $msg)
            : redirect()->route('exams.results', [$exam, 'tab' => 'scores'])->with('success', $msg.' · ตรวจทานครบแล้ว');
    }
}
