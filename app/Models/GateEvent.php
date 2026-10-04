<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** เหตุการณ์สแกนหนึ่งครั้งจากเครื่องที่ประตู */
class GateEvent extends Model
{
    public const RESULTS = [
        'present' => ['เข้า', 'success'], 'late' => ['เข้า (สาย)', 'warning'], 'out' => ['ออก', 'info'],
        'repeat' => ['สแกนซ้ำ', 'secondary'], 'unknown' => ['ไม่พบนักเรียน', 'danger'],
    ];

    /** เก็บประวัติเหตุการณ์ไว้กี่วัน (ข้อมูลการมาเรียนอยู่ในตารางการมาเรียน ไม่ถูกลบ) */
    public const KEEP_DAYS = 90;

    protected $fillable = ['gate_device_id', 'student_id', 'code', 'result', 'occurred_at', 'payload'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'payload' => 'array'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(GateDevice::class, 'gate_device_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
