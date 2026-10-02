<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BehaviorRecord extends Model
{
    protected $fillable = ['student_id', 'behavior_rule_id', 'title', 'points', 'note', 'date', 'recorded_by'];

    protected function casts(): array
    {
        return ['date' => DateOnly::class, 'points' => 'integer'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
