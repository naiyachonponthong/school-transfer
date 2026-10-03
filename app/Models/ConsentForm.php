<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** หนังสือขออนุญาตผู้ปกครอง ส่งถึงห้องที่เลือก ผู้ปกครองกดอนุญาต/ไม่อนุญาตในระบบ */
class ConsentForm extends Model
{
    protected $fillable = ['title', 'body', 'classroom_ids', 'due_date', 'is_open', 'created_by'];

    protected function casts(): array
    {
        return ['classroom_ids' => 'array', 'due_date' => DateOnly::class, 'is_open' => 'boolean'];
    }

    public function responses(): HasMany
    {
        return $this->hasMany(ConsentResponse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** นักเรียนที่หนังสือนี้ส่งถึง */
    public function students()
    {
        return Student::active()->whereIn('classroom_id', $this->classroom_ids ?? []);
    }

    public function includes(Student $student): bool
    {
        return in_array($student->classroom_id, $this->classroom_ids ?? [], false);
    }

    public function acceptsResponses(): bool
    {
        return $this->is_open && (! $this->due_date || $this->due_date->gte(today()));
    }
}
