<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** ตำแหน่งงานของบุคลากร = ชุดสิทธิ์ (บุคลากรหนึ่งคนถือได้หลายตำแหน่ง) */
class Role extends Model
{
    protected $fillable = ['key', 'name', 'permissions', 'is_system'];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'is_system' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
