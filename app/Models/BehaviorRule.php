<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BehaviorRule extends Model
{
    protected $fillable = ['name', 'points', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'points' => 'integer'];
    }
}
