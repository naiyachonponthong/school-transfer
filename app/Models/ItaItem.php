<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** หัวข้อ OIT (O1, O2, ...) ของปีงบประมาณหนึ่ง */
class ItaItem extends Model
{
    protected $fillable = ['ita_year_id', 'code', 'group', 'title', 'description', 'sort', 'done'];

    protected function casts(): array
    {
        return ['done' => 'boolean'];
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(ItaYear::class, 'ita_year_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(ItaEvidence::class)->orderBy('sort')->orderBy('id');
    }
}
