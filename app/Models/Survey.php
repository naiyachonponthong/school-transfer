<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * แบบประเมินที่โรงเรียนกำหนดเองได้ (ด้าน, ข้อกลับคะแนน, เกณฑ์แปลผล)
 * ใช้ทำแบบคัดกรองพฤติกรรม/อารมณ์ได้ — แบบมาตรฐานที่มีลิขสิทธิ์ (เช่น SDQ, EQ)
 * โรงเรียนต้องได้รับอนุญาตจากเจ้าของก่อนนำข้อคำถามมาใส่
 */
class Survey extends Model
{
    public const RESPONDENTS = ['teacher' => 'ครูประเมิน', 'parent' => 'ผู้ปกครองประเมิน', 'both' => 'ครูและผู้ปกครอง',
        'student' => 'นักเรียนประเมินตนเอง', 'all' => 'ครู ผู้ปกครอง และนักเรียน'];

    protected $fillable = ['title', 'description', 'respondent', 'scale', 'subscales', 'total_bands', 'is_active'];

    protected function casts(): array
    {
        return ['scale' => 'array', 'subscales' => 'array', 'total_bands' => 'array', 'is_active' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SurveyItem::class)->orderBy('sort');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function allows(string $role): bool
    {
        return match ($this->respondent) {
            'all' => true,
            'both' => in_array($role, ['teacher', 'parent'], true),
            default => $this->respondent === $role,
        };
    }

    public function maxValue(): int
    {
        return (int) collect($this->scale)->max('value');
    }

    /**
     * คิดคะแนนรายด้าน + รวม และแปลผลตามเกณฑ์
     *
     * @param  array<int, int>  $answers  item_id => value
     * @return array{subscales: array, total: ?int, total_band: ?array}
     */
    public function score(array $answers): array
    {
        $max = $this->maxValue();
        $sums = [];
        foreach ($this->items as $item) {
            if (! array_key_exists($item->id, $answers) || ! $item->subscale) {
                continue;
            }
            $v = (int) $answers[$item->id];
            $sums[$item->subscale] = ($sums[$item->subscale] ?? 0) + ($item->reverse ? $max - $v : $v);
        }

        $subs = [];
        $total = null;
        foreach ($this->subscales ?? [] as $s) {
            $val = $sums[$s['key']] ?? 0;
            $subs[$s['key']] = ['name' => $s['name'], 'score' => $val, 'band' => self::band($s['bands'] ?? [], $val)];
            if (! empty($s['in_total'])) {
                $total = ($total ?? 0) + $val;
            }
        }

        return ['subscales' => $subs, 'total' => $total, 'total_band' => $total === null ? null : self::band($this->total_bands ?? [], $total)];
    }

    /** เกณฑ์เรียงจากน้อยไปมาก: ใช้ช่วงแรกที่ค่า <= max */
    public static function band(array $bands, int $value): ?array
    {
        foreach ($bands as $b) {
            if ($value <= (int) $b['max']) {
                return $b;
            }
        }

        return $bands ? end($bands) : null;
    }
}
