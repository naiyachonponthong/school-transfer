<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** เอกสารที่ออกให้นักเรียน (ทะเบียนคุม) */
class DocumentIssue extends Model
{
    public const TYPES = ['pp7' => 'ปพ.7 ใบรับรองผลการศึกษา'];

    protected $fillable = ['type', 'year', 'number', 'student_id', 'student_name', 'purpose', 'issued_on', 'snapshot', 'issued_by'];

    protected function casts(): array
    {
        return ['issued_on' => DateOnly::class, 'snapshot' => 'array', 'year' => 'integer', 'number' => 'integer'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** เลขที่เอกสาร เช่น 12/2569 */
    public function code(): string
    {
        return "{$this->number}/{$this->year}";
    }
}
