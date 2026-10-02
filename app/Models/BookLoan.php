<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookLoan extends Model
{
    protected $fillable = ['book_id', 'book_copy_id', 'student_id', 'borrowed_on', 'due_on', 'returned_on', 'recorded_by'];

    protected function casts(): array
    {
        return ['borrowed_on' => DateOnly::class, 'due_on' => DateOnly::class, 'returned_on' => DateOnly::class];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'book_copy_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isOverdue(): bool
    {
        return ! $this->returned_on && $this->due_on->lt(today());
    }
}
