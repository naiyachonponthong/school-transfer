<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    /** สถานะ => [ชื่อ, ตัวย่อ, สี bootstrap] */
    public const STATUSES = [
        'present' => ['มา', 'ม', 'success'],
        'late' => ['สาย', 'ส', 'warning'],
        'absent' => ['ขาด', 'ข', 'danger'],
        'leave' => ['ลากิจ', 'ล', 'info'],
        'sick' => ['ลาป่วย', 'ป', 'purple'],
    ];

    protected $fillable = ['student_id', 'classroom_id', 'date', 'status', 'note', 'checked_at', 'checkout_at', 'source', 'recorded_by'];

    protected function casts(): array
    {
        return ['date' => DateOnly::class];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public static function label(string $status): string
    {
        return self::STATUSES[$status][0] ?? $status;
    }

    public static function short(string $status): string
    {
        return self::STATUSES[$status][1] ?? '?';
    }

    public static function color(string $status): string
    {
        return self::STATUSES[$status][2] ?? 'secondary';
    }
}
