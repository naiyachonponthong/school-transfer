<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** งานสารบรรณ: หนังสือรับ-ส่ง คำสั่ง บันทึกข้อความ เลขที่รันแยกประเภทต่อปี เวียนให้บุคลากรรับทราบได้ */
class OfficeDocument extends Model
{
    public const TYPES = ['in' => 'หนังสือรับ', 'out' => 'หนังสือส่ง', 'order' => 'คำสั่ง', 'memo' => 'บันทึกข้อความ'];

    public const URGENCY = ['normal' => ['ปกติ', 'secondary'], 'urgent' => ['ด่วน', 'warning'], 'very_urgent' => ['ด่วนที่สุด', 'danger']];

    protected $fillable = ['type', 'year', 'seq', 'ref_no', 'doc_date', 'subject', 'party', 'urgency', 'note', 'file', 'created_by'];

    protected function casts(): array
    {
        return ['doc_date' => DateOnly::class];
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'office_document_user')->withPivot('acknowledged_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** เลขทะเบียนของโรงเรียน เช่น 12/2569 */
    public function number(): string
    {
        return $this->seq.'/'.$this->year;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** ผู้ดูแลงานสารบรรณเห็นทุกฉบับ คนอื่นเห็นเฉพาะที่เวียนถึงตัวเอง */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $user->hasPermission('office.manage') ? $q : $q->whereHas('recipients', fn ($r) => $r->where('users.id', $user->id));
    }

    public function canBeViewedBy(User $user): bool
    {
        return $user->hasPermission('office.manage') || $this->recipients()->where('users.id', $user->id)->exists();
    }

}
