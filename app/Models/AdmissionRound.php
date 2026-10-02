<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * รอบสอบคัดเลือก 1 ชั้นของปีที่รับสมัคร (เช่น ม.1 ปี 2570)
 * ผู้มีสิทธิ์สอบ → จัดห้องสอบ/เลขประจำตัวสอบ → ชุดข้อสอบรายวิชา (ตรวจด้วยระบบตรวจข้อสอบเดิม) → จัดอันดับ → ประกาศผล
 */
class AdmissionRound extends Model
{
    public const RESULTS = [
        'pass' => ['ผ่านการคัดเลือก', 'success'],
        'reserve' => ['สำรอง', 'warning'],
        'fail' => ['ไม่ผ่าน', 'danger'],
        'absent' => ['ขาดสอบ', 'secondary'],
    ];

    /** ผลคัดเลือก → สถานะใบสมัคร */
    public const RESULT_STATUS = ['pass' => 'accepted', 'reserve' => 'reserve', 'fail' => 'rejected', 'absent' => 'rejected'];

    protected $fillable = ['year', 'level', 'exam_date', 'rooms', 'exam_no_start', 'order_by', 'quota', 'reserve', 'min_score',
        'announce_note', 'published_at', 'published_by'];

    protected function casts(): array
    {
        return [
            'exam_date' => DateOnly::class, 'rooms' => 'array', 'published_at' => 'datetime', 'min_score' => 'float',
            'quota' => 'integer', 'reserve' => 'integer', 'exam_no_start' => 'integer', 'year' => 'integer',
        ];
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class)->orderBy('id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function label(): string
    {
        return "สอบคัดเลือกชั้น {$this->level} ปีการศึกษา {$this->year}";
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /** ใบสมัครที่ส่งแล้วของรอบนี้ */
    public function applications(): Builder
    {
        return Admission::where('year', $this->year)->where('level', $this->level)->submitted();
    }

    /** มีสิทธิ์สอบ = ส่งใบสมัครแล้ว ยังไม่ตัดสิน และไม่ค้างค่าสมัคร */
    public function eligible(): Builder
    {
        return $this->applications()->whereIn('status', ['submitted', 'reviewing'])
            ->where(fn ($q) => $q->whereIn('fee_status', ['none', 'paid'])->orWhereNull('fee_amount')->orWhere('fee_amount', '<=', 0));
    }

    /** ผู้เข้าสอบ (ได้เลขประจำตัวสอบแล้ว) เรียงตามเลข */
    public function takers(): Builder
    {
        return $this->applications()->whereNotNull('exam_no')->orderBy('exam_no');
    }

    /** เลขประจำตัวสอบคนแรกเริ่มต้น: ม.1 → 10001 · ม.4 → 40001 */
    public function defaultExamNoStart(): int
    {
        return (preg_match('/\d/', $this->level, $m) ? (int) $m[0] : 1) * 10000 + 1;
    }

    public function seatCount(): int
    {
        return collect($this->rooms ?? [])->sum('seats');
    }

    /** มีกระดาษคำตอบของผู้สมัครที่สแกนแล้ว (จัดเลขใหม่ทั้งหมดไม่ได้) */
    public function hasScans(): bool
    {
        return ExamResponse::whereIn('exam_id', $this->exams()->pluck('id'))->whereNotNull('application_id')->exists();
    }

    /**
     * จัดอันดับ: คะแนนรวมถ่วงน้ำหนัก → คะแนนวิชาตามลำดับวิชา → สมัครก่อน
     * ไม่มีผลทุกวิชา = ขาดสอบ (ไม่จัดอันดับ) · ขาดบางวิชา = วิชานั้นได้ 0
     *
     * @return Collection<int, array{application: Admission, scores: Collection, total: float, missing: int, absent: bool, rank: ?int, result: string, reserve_no: ?int}>
     */
    public function standings(): Collection
    {
        $exams = $this->exams()->get();
        $scores = ExamResponse::whereIn('exam_id', $exams->pluck('id'))->where('status', 'ok')->whereNotNull('application_id')->get()
            ->groupBy('application_id')->map(fn ($rs) => $rs->keyBy('exam_id'));

        $rows = $this->takers()->get()->map(function (Admission $a) use ($exams, $scores) {
            $mine = $scores->get($a->id, collect());
            $subject = $exams->mapWithKeys(fn ($e) => [$e->id => $mine->get($e->id)?->score]);
            $missing = $subject->filter(fn ($v) => $v === null)->count();

            return [
                'application' => $a, 'scores' => $subject, 'missing' => $missing,
                'absent' => $exams->isEmpty() || $missing === $exams->count(),
                'total' => round($exams->sum(fn ($e) => ($subject[$e->id] ?? 0) * $e->weight), 2),
            ];
        });

        $sorted = $rows->sort(function ($x, $y) use ($exams) {
            if ($x['absent'] !== $y['absent']) {
                return $x['absent'] <=> $y['absent'];
            }
            if ($x['total'] != $y['total']) {
                return $y['total'] <=> $x['total'];
            }
            foreach ($exams as $e) {
                if (($a = $x['scores'][$e->id] ?? 0) != ($b = $y['scores'][$e->id] ?? 0)) {
                    return $b <=> $a;
                }
            }

            return strcmp($x['application']->app_no, $y['application']->app_no);
        })->values();

        $rank = $passed = $reserves = 0;

        return $sorted->map(function ($r) use (&$rank, &$passed, &$reserves) {
            $r += ['rank' => null, 'reserve_no' => null];
            if ($r['absent']) {
                return ['result' => 'absent'] + $r;
            }
            $r['rank'] = ++$rank;
            if ($this->min_score !== null && $r['total'] < $this->min_score) {
                $r['result'] = 'fail';
            } elseif ($this->quota === null || $passed < $this->quota) {
                $passed++;
                $r['result'] = 'pass';
            } elseif ($reserves < $this->reserve) {
                $r['reserve_no'] = ++$reserves;
                $r['result'] = 'reserve';
            } else {
                $r['result'] = 'fail';
            }

            return $r;
        });
    }
}
