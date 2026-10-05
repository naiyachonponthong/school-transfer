<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeItem extends Model
{
    protected $fillable = ['name', 'category', 'default_amount', 'is_active'];

    protected function casts(): array
    {
        return ['default_amount' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
