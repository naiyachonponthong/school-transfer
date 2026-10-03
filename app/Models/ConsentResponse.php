<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentResponse extends Model
{
    protected $fillable = ['consent_form_id', 'student_id', 'user_id', 'agreed', 'note'];

    protected function casts(): array
    {
        return ['agreed' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
