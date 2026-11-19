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

    protected static function booted(): void
    {
        // สถานะการมาเรียนเปลี่ยน (ครูเช็กชื่อ สแกนหน้าประตู อนุมัติใบลา) > คำนวณการหักคะแนนความประพฤติอัตโนมัติใหม่
        $sync = function (Attendance $a) {
            if (! \App\Support\AutoBehavior::enabled() && ! \App\Models\BehaviorRecord::whereNotNull('auto_key')->where('student_id', $a->student_id)->exists()) {
                return; // ปิดอยู่และไม่เคยหักไว้ ไม่ต้องทำอะไร
            }
            rescue(fn () => \App\Support\AutoBehavior::sync($a->student_id, $a->date->toDateString()), null, true);
        };
        static::saved(function (Attendance $a) use ($sync) {
            if ($a->wasRecentlyCreated || $a->wasChanged(['status', 'date'])) {
                $sync($a);
            }
        });
        static::deleted($sync);
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
