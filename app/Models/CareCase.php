<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** กรณีดูแลช่วยเหลือนักเรียน: ข้อมูลอ่อนไหว เห็นได้เฉพาะครูประจำชั้น ผู้รับผิดชอบ และผู้มีสิทธิ์ care.manage */
class CareCase extends Model
{
    public const CATEGORIES = [
        'learning' => 'การเรียน', 'behavior' => 'พฤติกรรม', 'health' => 'สุขภาพ',
        'economic' => 'เศรษฐกิจ', 'family' => 'ครอบครัว', 'safety' => 'ความปลอดภัย',
    ];

    public const LEVELS = ['risk' => ['กลุ่มเสี่ยง', 'warning'], 'problem' => ['กลุ่มมีปัญหา', 'danger']];

    public const STATUSES = [
        'open' => ['กำลังช่วยเหลือ', 'primary'], 'monitoring' => ['ติดตามผล', 'info'],
        'referred' => ['ส่งต่อ', 'warning'], 'closed' => ['ปิดกรณี', 'secondary'],
    ];

    protected $fillable = ['student_id', 'category', 'level', 'title', 'detail', 'status', 'owner_id', 'opened_by', 'closed_at'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(CareAction::class)->orderByDesc('date')->orderByDesc('id');
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->hasPermission('care.manage')) {
            return $q;
        }

        return $q->where(fn ($w) => $w->where('owner_id', $user->id)->orWhere('opened_by', $user->id)
            ->orWhereHas('student', fn ($s) => $s->whereIn('classroom_id', $user->myClassrooms()->pluck('id'))));
    }

    public function canBeAccessedBy(User $user): bool
    {
        return self::visibleTo($user)->whereKey($this->id)->exists();
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

}
