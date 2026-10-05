<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Support\Sequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/** โครงการในแผนปฏิบัติการประจำปีงบประมาณ */
class Project extends Model
{
    public const STATUSES = ['active' => ['ดำเนินการ', 'success'], 'closed' => ['ปิดโครงการ', 'secondary']];

    protected $fillable = ['code', 'name', 'fiscal_year', 'department_id', 'owner_id', 'objective', 'starts_on', 'ends_on', 'status', 'summary', 'created_by'];

    protected function casts(): array
    {
        return ['starts_on' => DateOnly::class, 'ends_on' => DateOnly::class];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(ProjectBudget::class);
    }

    public function requests(): HasManyThrough
    {
        return $this->hasManyThrough(PurchaseRequest::class, ProjectBudget::class);
    }

    /** โครงการที่ผู้ใช้เห็นได้: ผู้ดูแลงบ/ผู้อนุมัติ/พัสดุเห็นทุกโครงการ คนอื่นเห็นโครงการที่ตัวเองรับผิดชอบ */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return self::seesAll($user) ? $q : $q->where('owner_id', $user->id);
    }

    public static function seesAll(User $user): bool
    {
        return $user->hasPermission('budget.manage') || $user->hasPermission('budget.approve') || $user->hasPermission('procurement.manage');
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public static function nextCode(int $fiscalYear): string
    {
        $yy = substr((string) $fiscalYear, -2);
        $seq = Sequence::next('PJ', (string) $fiscalYear, fn () => (int) substr((string) self::where('code', 'like', "P{$yy}-%")->max('code'), -3));

        return "P{$yy}-".str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
