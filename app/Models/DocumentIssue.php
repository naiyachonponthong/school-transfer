<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** เอกสารที่ออกให้นักเรียน (ทะเบียนคุม) */
class DocumentIssue extends Model
{
    public const TYPES = ['pp1' => 'ปพ.1 ระเบียนแสดงผลการเรียน', 'pp7' => 'ปพ.7 ใบรับรองผลการศึกษา'];

    protected $fillable = ['type', 'year', 'number', 'student_id', 'student_name', 'purpose', 'issued_on', 'snapshot', 'issued_by', 'form_series', 'form_number'];

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

    /** เลขลำดับถัดไปในทะเบียนคุมของปี พ.ศ. นี้ (เรียกภายใน transaction) */
    public static function nextNumber(string $type, int $year): int
    {
        return (int) self::where(['type' => $type, 'year' => $year])->lockForUpdate()->max('number') + 1;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** เลขที่เอกสาร เช่น 12/2569 */
    public function code(): string
    {
        return "{$this->number}/{$this->year}";
    }
}
