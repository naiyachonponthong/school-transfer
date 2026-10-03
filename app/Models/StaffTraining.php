<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffTraining extends Model
{
    protected $fillable = ['user_id', 'title', 'organizer', 'date', 'hours', 'file'];

    protected function casts(): array
    {
        return ['date' => DateOnly::class, 'hours' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
