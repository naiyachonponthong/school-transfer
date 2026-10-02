<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Models\AdmissionRound;
use App\Models\Exam;
use App\Support\AdmissionForm;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * สอบคัดเลือกเข้าเรียน (งานรับสมัคร):
 * จัดห้องสอบ/เลขประจำตัวสอบ → ชุดข้อสอบรายวิชา (ตรวจด้วยระบบตรวจข้อสอบ) → รวมคะแนน/จัดอันดับ → ประกาศผล
 */
class AdmissionExamController extends Controller
{
    public const DOCS = ['door' => 'รายชื่อติดหน้าห้องสอบ', 'sign' => 'ใบลงชื่อเข้าสอบ', 'desk' => 'บัตรติดโต๊ะสอบ',
        'announce' => 'ประกาศรายชื่อผู้ผ่านการคัดเลือก', 'scores' => 'รายงานคะแนนสอบคัดเลือก'];

    public function index(Request $request)
    {
        $year = (int) $request->query('year', ApplyController::year());
        $rounds = AdmissionRound::where('year', $year)->withCount('exams')->get()->keyBy('level');
        $levels = collect(AdmissionForm::levels())->merge($rounds->keys())
            ->merge(Admission::where('year', $year)->submitted()->distinct()->pluck('level'))->filter()->unique()->values();

        return view('admission-exams.index', [
            'year' => $year, 'levels' => $levels, 'rounds' => $rounds,
            'applied' => Admission::where('year', $year)->submitted()->selectRaw('level, count(*) c')->groupBy('level')->pluck('c', 'level'),
            'takers' => Admission::where('year', $year)->submitted()->whereNotNull('exam_no')->selectRaw('level, count(*) c')->groupBy('level')->pluck('c', 'level'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['year' => ['required', 'integer', 'between:2500,2700'], 'level' => ['required', 'string', 'max:20']]);
        $round = AdmissionRound::firstOrCreate($data);
        $round->exam_no_start ??= $round->defaultExamNoStart();
        $round->save();

        return redirect()->route('admission-exams.show', $round);
    }

    public function show(Request $request, AdmissionRound $round)
    {
        $exams = $round->exams()->withCount(['responses as sheets_count' => fn ($q) => $q->where('status', 'ok'),
            'responses as review_count' => fn ($q) => $q->where('status', 'review')])->get();
        $takers = $round->takers()->get();
        $standings = $round->standings();

        return view('admission-exams.show', [
            'round' => $round, 'exams' => $exams, 'takers' => $takers, 'standings' => $standings,
            'tab' => $request->query('tab', $takers->isEmpty() ? 'seats' : ($exams->isEmpty() ? 'subjects' : 'results')),
            'counts' => [
                'applied' => $round->applications()->count(),
                'eligible' => $round->eligible()->count(),
                'waiting' => $round->eligible()->whereNull('exam_no')->count(),
                'unpaid' => $round->applications()->whereIn('status', ['submitted', 'reviewing'])->whereIn('fee_status', ['unpaid', 'pending'])->where('fee_amount', '>', 0)->count(),
            ],
            'roomUse' => $takers->groupBy('exam_room')->map->count(),
            'problems' => $this->problems($round, $exams, $takers),
            'hasScans' => $round->hasScans(),
        ]);
    }

    /** สิ่งที่ต้องทำก่อนประกาศผล */
    private function problems(AdmissionRound $round, Collection $exams, Collection $takers): array
    {
        $out = [];
        if ($takers->isEmpty()) {
            $out[] = 'ยังไม่ได้จัดห้องสอบ/เลขประจำตัวสอบ';
        }
        if ($exams->isEmpty()) {
            $out[] = 'ยังไม่มีวิชาสอบ';
        }
        foreach ($exams as $e) {
            if (! $e->keyReady()) {
                $out[] = "วิชา{$e->subjectLabel()} ยังใส่เฉลยไม่ครบ";
            }
            if ($e->review_count) {
                $out[] = "วิชา{$e->subjectLabel()} มีกระดาษรอตรวจทาน {$e->review_count} แผ่น";
            }
        }

        return $out;
    }

    /** ตั้งค่าเกณฑ์คัดเลือก/วันสอบ/ข้อความประกาศ */
    public function update(Request $request, AdmissionRound $round)
    {
        $data = $request->validate([
            'exam_date' => ['nullable', 'date'],
            'quota' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'reserve' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'min_score' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'announce_note' => ['nullable', 'string', 'max:1000'],
        ], [], ['quota' => 'จำนวนรับ', 'reserve' => 'จำนวนสำรอง', 'min_score' => 'คะแนนขั้นต่ำ']);
        $data['reserve'] ??= 0;
        $round->fill($data);
        if ($diff = Audit::diff($round)) {
            Audit::log('admission.round', $round, 'แก้เกณฑ์'.$round->label(), $diff);
        }
        $round->save();

        return back()->with('success', $round->isPublished() ? 'บันทึกแล้ว — ประกาศผลไปแล้ว ถ้าต้องการให้เกณฑ์ใหม่มีผล ให้ยกเลิกประกาศแล้วประกาศใหม่' : 'บันทึกเกณฑ์แล้ว');
    }

    /**
     * จัดห้องสอบ + ออกเลขประจำตัวสอบ 5 หลัก
     * all = จัดใหม่ทั้งหมด (ทำได้ก่อนสแกนกระดาษ) · append = เพิ่มเฉพาะผู้มีสิทธิ์ที่ยังไม่มีเลข ต่อท้ายที่นั่งที่เหลือ
     */
    public function seats(Request $request, AdmissionRound $round)
    {
        $data = $request->validate([
            'rooms' => ['required', 'string', 'max:5000'],
            'exam_no_start' => ['required', 'integer', 'between:1,99999'],
            'order_by' => ['required', Rule::in(['app_no', 'name'])],
            'mode' => ['required', Rule::in(['all', 'append'])],
            'exam_date' => ['nullable', 'date'],
        ], [], ['rooms' => 'ห้องสอบ', 'exam_no_start' => 'เลขประจำตัวสอบคนแรก']);
        $rooms = self::parseRooms($data['rooms']);
        if (! $rooms) {
            throw ValidationException::withMessages(['rooms' => 'ใส่ห้องสอบอย่างน้อย 1 ห้อง บรรทัดละห้อง เช่น "321, 30"']);
        }
        if ($data['mode'] === 'all' && $round->hasScans()) {
            throw ValidationException::withMessages(['mode' => 'สแกนกระดาษคำตอบไปแล้ว จัดเลขใหม่ทั้งหมดไม่ได้ (เลขจะไม่ตรงกระดาษ) — ใช้ "เพิ่มเฉพาะคนที่ยังไม่มีเลข"']);
        }

        $count = DB::transaction(function () use ($round, $data, $rooms) {
            $round->fill(['rooms' => $rooms, 'exam_no_start' => $data['exam_no_start'], 'order_by' => $data['order_by']]
                + (array_key_exists('exam_date', $data) ? ['exam_date' => $data['exam_date']] : []))->save();
            if ($data['mode'] === 'all') {
                $round->applications()->whereNotNull('exam_no')->update(['exam_no' => null, 'exam_room' => null, 'exam_seat' => null]);
            }
            $people = self::sortPeople($round->eligible()->whereNull('exam_no')->get(), $data['order_by']);
            $current = $round->takers()->get();
            $lastSeat = $current->groupBy('exam_room')->map(fn ($g) => $g->max(fn ($a) => (int) $a->exam_seat));
            $slots = [];
            foreach ($rooms as $r) {
                for ($s = ($lastSeat[$r['name']] ?? 0) + 1; $s <= $r['seats']; $s++) {
                    $slots[] = [$r['name'], $s];
                }
            }
            if ($people->count() > count($slots)) {
                throw ValidationException::withMessages(['rooms' => 'ที่นั่งไม่พอ ขาดอีก '.($people->count() - count($slots)).' ที่ — เพิ่มห้องหรือจำนวนที่นั่ง']);
            }
            $next = max($data['exam_no_start'], (int) $current->max(fn ($a) => (int) $a->exam_no) + 1);
            if ($people->isNotEmpty() && $next + $people->count() - 1 > 99999) {
                throw ValidationException::withMessages(['exam_no_start' => 'เลขประจำตัวสอบเกิน 5 หลัก (กระดาษคำตอบระบายได้ 5 หลัก) — ลดเลขเริ่มต้น']);
            }
            foreach ($people->values() as $i => $a) {
                $a->update(['exam_no' => str_pad((string) ($next + $i), 5, '0', STR_PAD_LEFT), 'exam_room' => $slots[$i][0], 'exam_seat' => (string) $slots[$i][1]]);
            }

            return $people->count();
        });
        Audit::log('admission.seats', $round, ($data['mode'] === 'all' ? 'จัดห้องสอบใหม่ทั้งหมด ' : 'เพิ่มผู้เข้าสอบ ')."{$count} คน · ".$round->label());

        return redirect()->route('admission-exams.show', [$round, 'tab' => 'seats'])
            ->with($count ? 'success' : 'warning', $count ? "จัดห้องสอบและออกเลขประจำตัวสอบให้ {$count} คนแล้ว" : 'ไม่มีผู้มีสิทธิ์สอบที่ยังไม่มีเลข');
    }

    /** บรรทัดละห้อง: "321, 30" · "ห้อง 321 : 35 ที่นั่ง" · "321" (ไม่ระบุ = 30 ที่) */
    public static function parseRooms(string $text): array
    {
        $rooms = [];
        foreach (preg_split('/\R/', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^(.*?)\s*[,|=:\t]\s*(\d{1,3})\s*(ที่นั่ง|ที่|คน)?$/u', $line, $m)) {
                [$name, $seats] = [trim($m[1]), (int) $m[2]];
            } else {
                [$name, $seats] = [$line, 30];
            }
            if ($name !== '' && $seats > 0 && ! isset($rooms[$name])) {
                $rooms[$name] = ['name' => mb_substr($name, 0, 60), 'seats' => min($seats, 99)];
            }
        }

        return array_values($rooms);
    }

    /** เรียงตามเลขใบสมัคร หรือชื่อ (ลำดับพจนานุกรมไทย) */
    private static function sortPeople(Collection $people, string $by): Collection
    {
        if ($by !== 'name') {
            return $people->sortBy('app_no')->values();
        }
        $key = fn ($a) => trim($a->first_name.' '.$a->last_name);
        if (class_exists(\Collator::class)) {
            $c = new \Collator('th_TH');

            return $people->sort(fn ($x, $y) => $c->compare($key($x), $key($y)))->values();
        }

        // ไม่มี intl: ย้ายสระหน้า (เ แ โ ใ ไ) ไปหลังพยัญชนะก่อนเทียบ
        return $people->sortBy(fn ($a) => preg_replace('/(^|\s)([เแโใไ])(\S)/u', '$1$3$2', $key($a)))->values();
    }

    /** เพิ่มวิชาสอบ = ชุดข้อสอบ 1 ชุดในระบบตรวจข้อสอบ */
    public function addSubject(Request $request, AdmissionRound $round)
    {
        $data = $request->validate([
            'subject_name' => ['required', 'string', 'max:100'],
            'n_items' => ['required', 'integer', 'between:1,'.Exam::MAX_ITEMS],
            'weight' => ['nullable', 'numeric', 'gt:0', 'max:100'],
        ], [], ['subject_name' => 'วิชา', 'n_items' => 'จำนวนข้อ', 'weight' => 'น้ำหนัก']);
        $exam = Exam::create([
            'admission_round_id' => $round->id, 'subject_name' => $data['subject_name'], 'title' => 'สอบคัดเลือก '.$round->level,
            'n_items' => $data['n_items'], 'weight' => $data['weight'] ?? 1, 'exam_date' => $round->exam_date,
            'answer_key' => [], 'cancelled' => [], 'created_by' => $request->user()->id,
        ]);

        return redirect()->route('exams.show', $exam)->with('success', "เพิ่มวิชา{$exam->subject_name}แล้ว ใส่เฉลยได้เลย");
    }

    /** ประกาศผล: บันทึกคะแนนรวม/อันดับ และเปลี่ยนสถานะใบสมัครเป็น ผ่าน / สำรอง / ไม่ผ่าน (ผู้สมัครเห็นทันที) */
    public function publish(Request $request, AdmissionRound $round)
    {
        $exams = $round->exams()->withCount(['responses as review_count' => fn ($q) => $q->where('status', 'review')])->get();
        if ($problems = $this->problems($round, $exams, $round->takers()->get())) {
            return back()->withErrors(['publish' => 'ยังประกาศผลไม่ได้: '.implode(' · ', $problems)]);
        }
        $standings = $round->standings();
        DB::transaction(function () use ($round, $standings, $request) {
            foreach ($standings as $row) {
                $a = $row['application'];
                if (in_array($a->status, ['enrolled', 'draft'], true)) {
                    continue; // มอบตัวแล้วไม่เปลี่ยนสถานะ
                }
                $a->update([
                    'status' => AdmissionRound::RESULT_STATUS[$row['result']], 'exam_total' => $row['absent'] ? null : $row['total'],
                    'exam_rank' => $row['rank'], 'reserve_no' => $row['reserve_no'],
                ]);
            }
            $round->update(['published_at' => now(), 'published_by' => $request->user()->id]);
        });
        $by = $standings->countBy('result');
        Audit::log('admission.publish', $round, 'ประกาศผล'.$round->label().' · ผ่าน '.($by['pass'] ?? 0).' สำรอง '.($by['reserve'] ?? 0).' ไม่ผ่าน '.($by['fail'] ?? 0).' ขาดสอบ '.($by['absent'] ?? 0));

        return redirect()->route('admission-exams.show', [$round, 'tab' => 'results'])
            ->with('success', 'ประกาศผลแล้ว — ผู้สมัครดูผลได้ที่หน้าตรวจสอบสถานะใบสมัคร · ผ่าน '.($by['pass'] ?? 0).' คน · สำรอง '.($by['reserve'] ?? 0).' คน');
    }

    /** ยกเลิกประกาศ: สถานะกลับเป็น "กำลังตรวจสอบ" (ยกเว้นที่มอบตัวแล้ว) แก้คะแนน/เกณฑ์แล้วประกาศใหม่ได้ */
    public function unpublish(Request $request, AdmissionRound $round)
    {
        DB::transaction(function () use ($round) {
            $round->takers()->whereIn('status', ['accepted', 'reserve', 'rejected'])
                ->update(['status' => 'reviewing', 'exam_total' => null, 'exam_rank' => null, 'reserve_no' => null]);
            $round->update(['published_at' => null, 'published_by' => null]);
        });
        Audit::log('admission.unpublish', $round, 'ยกเลิกประกาศผล'.$round->label());

        return back()->with('success', 'ยกเลิกประกาศผลแล้ว — ผู้สมัครเห็นสถานะ "กำลังตรวจสอบ"');
    }

    public function print(Request $request, AdmissionRound $round, string $doc)
    {
        abort_unless(isset(self::DOCS[$doc]), 404);
        $takers = $round->takers()->get()->sortBy(fn ($a) => [(string) $a->exam_room, (int) $a->exam_seat])->values();
        $room = $request->query('room');

        return view('admission-exams.print', [
            'round' => $round, 'doc' => $doc, 'title' => self::DOCS[$doc], 'exams' => $round->exams()->get(),
            'byRoom' => ($room ? $takers->where('exam_room', $room) : $takers)->groupBy('exam_room'),
            'standings' => in_array($doc, ['announce', 'scores'], true) ? $round->standings() : collect(),
        ]);
    }

    public function export(AdmissionRound $round)
    {
        $exams = $round->exams()->get();
        $standings = $round->standings();

        return response()->streamDownload(function () use ($exams, $standings) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_merge(['อันดับ', 'ผล', 'ลำดับสำรอง', 'เลขประจำตัวสอบ', 'เลขที่ใบสมัคร', 'ชื่อ-สกุล', 'โรงเรียนเดิม', 'ห้องสอบ', 'ที่นั่ง'],
                $exams->map(fn ($e) => $e->subjectLabel().($e->weight != 1 ? ' (×'.(float) $e->weight.')' : ''))->all(), ['คะแนนรวม']));
            foreach ($standings as $r) {
                $a = $r['application'];
                fputcsv($out, array_merge([$r['rank'], AdmissionRound::RESULTS[$r['result']][0], $r['reserve_no'], "\t".$a->exam_no, $a->app_no, $a->fullName(),
                    $a->previous_school, $a->exam_room, $a->exam_seat], $exams->map(fn ($e) => $r['scores'][$e->id])->all(), [$r['absent'] ? '' : $r['total']]));
            }
            fclose($out);
        }, "admission_{$round->year}_{$round->level}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
