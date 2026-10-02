<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageLog extends Model
{
    protected $fillable = ['channel', 'user_id', 'text', 'status', 'error'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
