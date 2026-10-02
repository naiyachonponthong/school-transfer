<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    protected $fillable = ['code', 'title', 'author', 'category', 'publisher', 'copies', 'location'];

    public function loans(): HasMany
    {
        return $this->hasMany(BookLoan::class);
    }

    public function activeLoans(): HasMany
    {
        return $this->hasMany(BookLoan::class)->whereNull('returned_on');
    }

    public function available(): int
    {
        $out = $this->active_loans_count ?? $this->activeLoans()->count();

        return max(0, $this->copies - $out);
    }
}
