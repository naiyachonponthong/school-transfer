<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareAction extends Model
{
    protected $fillable = ['care_case_id', 'date', 'action', 'result', 'follow_up_on', 'user_id'];

    protected function casts(): array
    {
        return ['date' => DateOnly::class, 'follow_up_on' => DateOnly::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
