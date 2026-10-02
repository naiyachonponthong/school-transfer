<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ชุดข้อสอบปรนัย 4 ตัวเลือก ตรวจด้วยกล้องมือถือ (ตัวอ่านกระดาษจาก ScanGrade)
 * คำตอบเก็บเป็นสตริง: '1'..'4' = ก..ง · '0' = ไม่ตอบ · '9' = ตอบซ้อน
 */
class Exam extends Model
{
    public const MAX_ITEMS = 100;

    public const CHOICES = ['ก', 'ข', 'ค', 'ง'];

    /** กลุ่มเลขที่บนกระดาษ (เหมือน ScanGrade เดิม) — ระบบนี้ใช้เลขที่ตัวเลขอย่างเดียว กลุ่มระบายหรือไม่ก็ได้ */
    public const SEAT_GROUPS = ['ก', 'ข'];

    /** เหตุผลที่ต้องตรวจทาน (จาก flags ของตัวอ่าน + ที่ server เพิ่ม) */
    public const REVIEW_REASONS = [
        'unknown_code' => 'ไม่พบเลขประจำตัว', 'seat_mismatch' => 'เลขที่ไม่ตรงรายชื่อ', 'duplicate' => 'สแกนซ้ำ',
        'code_incomplete' => 'ระบายรหัสไม่ครบ', 'n_mismatch' => 'แบบกระดาษไม่ตรงจำนวนข้อ', 'multi' => 'ตอบซ้อน',
        'low_conf' => 'อ่านไม่ชัด', 'warp' => 'กระดาษโค้ง/ภาพเพี้ยน',
    ];

    protected $fillable = ['term_id', 'subject_id', 'title', 'n_items', 'exam_date', 'answer_key', 'cancelled', 'cancel_mode',
        'points', 'key_version', 'assessment_name', 'published', 'created_by'];

    protected function casts(): array
    {
        return [
            'exam_date' => DateOnly::class, 'answer_key' => 'array', 'cancelled' => 'array', 'points' => 'float',
            'n_items' => 'integer', 'key_version' => 'integer', 'published' => 'boolean',
        ];
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ExamResponse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** ครูที่สอนวิชานี้ในห้องใดห้องหนึ่งของชุดข้อสอบ หรือคนสร้าง หรือผู้ดูแล */
    public function canManage(User $user): bool
    {
        if ($user->isAdmin() || $this->created_by === $user->id) {
            return true;
        }

        return $this->courses()->where('teacher_id', $user->id)->exists();
    }

    public function scopeManagedBy(Builder $q, User $user): Builder
    {
        return $user->isAdmin() ? $q : $q->where(fn ($w) => $w->where('created_by', $user->id)
            ->orWhereHas('courses', fn ($c) => $c->where('teacher_id', $user->id)));
    }

    /** นักเรียนทุกคนในห้องที่สอบ เรียงห้อง → เลขที่ */
    public function students(): Collection
    {
        $this->loadMissing('courses.classroom');
        $rooms = $this->courses->pluck('classroom')->filter()->sortBy(fn ($c) => [$c->level_order, $c->room])->values();

        return Student::active()->with('classroom')->whereIn('classroom_id', $rooms->pluck('id'))->get()
            ->sortBy(fn ($s) => [$rooms->search(fn ($r) => $r->id === $s->classroom_id), $s->number ?? 999])->values();
    }

    public function label(): string
    {
        return $this->title.' · '.$this->subject?->name;
    }

    /** เฉลยที่ทำความสะอาดแล้ว ยาว n_items: '' ยังไม่ใส่ · '2' · '24' */
    public function key(): array
    {
        return self::cleanKey($this->answer_key ?? [], $this->n_items);
    }

    public static function cleanKey(array $key, int $n): array
    {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $chars = array_unique(str_split(preg_replace('/[^1-4]/', '', (string) ($key[$i] ?? ''))));
            sort($chars);
            $out[] = implode('', array_filter($chars, fn ($c) => $c !== ''));
        }

        return $out;
    }

    public function cancelledItems(): array
    {
        return array_values(array_filter(array_map('intval', $this->cancelled ?? []), fn ($q) => $q >= 1 && $q <= $this->n_items));
    }

    /** ครบทุกข้อ (ข้อที่ยกเลิกไม่ต้องมีเฉลย) */
    public function keyReady(): bool
    {
        $cancelled = array_flip($this->cancelledItems());
        foreach ($this->key() as $i => $k) {
            if ($k === '' && ! isset($cancelled[$i + 1])) {
                return false;
            }
        }

        return true;
    }

    /**
     * ให้คะแนน 1 แผ่น — ใช้ที่เดียวทั้งระบบ (ตรรกะเดียวกับ score_ ของ ScanGrade และ score() ในหน้าสแกน)
     *
     * @return array{score: float, max: float, marks: array<int, int|string>} marks: 1 ถูก · 0 ผิด · 'c' ยกเลิก
     */
    public function score(string $answers): array
    {
        $key = $this->key();
        $cancelled = array_flip($this->cancelledItems());
        $give = $this->cancel_mode !== 'drop';
        $pts = $this->points ?: 1;
        $score = 0;
        $max = 0;
        $marks = [];
        for ($i = 0; $i < $this->n_items; $i++) {
            if (isset($cancelled[$i + 1])) {
                $marks[] = 'c';
                if ($give) {
                    $score += $pts;
                    $max += $pts;
                }

                continue;
            }
            $max += $pts;
            $ch = $answers[$i] ?? '0';
            $ok = $ch >= '1' && $ch <= '4' && str_contains($key[$i], $ch);
            $marks[] = $ok ? 1 : 0;
            if ($ok) {
                $score += $pts;
            }
        }

        return ['score' => round($score, 2), 'max' => round($max, 2), 'marks' => $marks];
    }

    /** ตรวจใหม่ทุกแผ่นหลังแก้เฉลย · คืนจำนวนแผ่นที่คำนวณใหม่ */
    public function regrade(): int
    {
        $n = 0;
        foreach ($this->responses()->where('status', '!=', 'void')->get() as $r) {
            $sc = $this->score($r->answers);
            $r->update(['score' => $sc['score'], 'max_score' => $sc['max'], 'key_version' => $this->key_version]);
            $n++;
        }

        return $n;
    }

    /** แปลงคำตอบที่พิมพ์/วาง (ก–ง, 1–4, ช่องว่าง, แถว KEY ของ EVANA) เป็นสตริง '1234...' */
    public static function normalizeAnswers(string $s): string
    {
        return preg_replace('/[^0-9]/', '', strtr($s, ['ก' => '1', 'ข' => '2', 'ค' => '3', 'ง' => '4']));
    }

    public static function reasons(array $flags): array
    {
        $out = [];
        $items = [];
        foreach ($flags as $f) {
            [$k, $no] = array_pad(explode(':', (string) $f, 2), 2, null);
            if (! isset(self::REVIEW_REASONS[$k])) {
                continue;
            }
            if (in_array($k, ['multi', 'low_conf'], true)) {
                $items[$k][] = $no;
            } else {
                $out[$k] = self::REVIEW_REASONS[$k];
            }
        }
        foreach ($items as $k => $nos) {
            $out[$k] = self::REVIEW_REASONS[$k].' ข้อ '.implode(', ', $nos);
        }

        return array_values($out);
    }
}
