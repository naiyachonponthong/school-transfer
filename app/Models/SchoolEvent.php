<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** กิจกรรมในปฏิทินโรงเรียน */
class SchoolEvent extends Model
{
    protected $table = 'events';

    public const TYPES = [
        'holiday' => ['วันหยุด', 'danger', 'bi-sun'],
        'exam' => ['สอบ', 'warning', 'bi-pencil-square'],
        'activity' => ['กิจกรรม', 'success', 'bi-flag'],
        'meeting' => ['ประชุม', 'info', 'bi-people'],
    ];

    protected $fillable = ['title', 'description', 'start_date', 'end_date', 'type', 'audience', 'created_by'];

    protected function casts(): array
    {
        return ['start_date' => DateOnly::class, 'end_date' => DateOnly::class];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $user->isStaff() ? $q : $q->where('audience', 'all');
    }

    public function scopeOverlapping(Builder $q, string $from, string $to): Builder
    {
        return $q->where('start_date', '<=', $to)->where('end_date', '>=', $from);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? $this->type;
    }

    public function typeColor(): string
    {
        return self::TYPES[$this->type][1] ?? 'secondary';
    }

    public function typeIcon(): string
    {
        return self::TYPES[$this->type][2] ?? 'bi-calendar';
    }

    public function covers(string $date): bool
    {
        return $this->start_date->toDateString() <= $date && $this->end_date->toDateString() >= $date;
    }
}
