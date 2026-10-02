<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAttendance extends Model
{
    public const STATUSES = [
        'present' => ['มาปฏิบัติงาน', 'success'],
        'late' => ['มาสาย', 'warning'],
        'leave' => ['ลา', 'info'],
        'duty' => ['ไปราชการ', 'purple'],
    ];

    protected $fillable = ['user_id', 'date', 'check_in', 'check_out', 'status', 'note', 'lat', 'lng', 'distance_m'];

    protected function casts(): array
    {
        return ['date' => DateOnly::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
