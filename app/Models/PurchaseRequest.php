<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Support\CodeSeries;
use App\Support\Settings;
use App\Support\Sequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ใบขอซื้อ/ขอจ้าง: ตัดเงินจากงบหนึ่งบรรทัดของโครงการ แล้วผ่านการอนุมัติตามลำดับขั้น
 * อนุมัติครบทุกขั้น = เงินถูกผูกพัน (กันไว้ ยังไม่ได้จ่าย)
 */
class PurchaseRequest extends Model
{
    public const KINDS = ['buy' => 'ขอซื้อ', 'hire' => 'ขอจ้าง'];

    public const METHODS = ['specific' => 'เฉพาะเจาะจง', 'selection' => 'คัดเลือก', 'ebidding' => 'ประกวดราคาอิเล็กทรอนิกส์ (e-bidding)', 'other' => 'อื่น ๆ'];

    public const ITEM_TYPES = ['supply' => 'วัสดุ', 'asset' => 'ครุภัณฑ์', 'service' => 'จ้าง/บริการ'];

    public const STATUSES = ['pending' => ['รออนุมัติ', 'warning'], 'approved' => ['อนุมัติแล้ว', 'success'], 'rejected' => ['ไม่อนุมัติ', 'danger'], 'cancelled' => ['ยกเลิก', 'secondary']];

    /** ขั้นอนุมัติที่มีให้เลือก (เปิด-ปิดได้ในหน้างบประมาณ): key => [ชื่อขั้น, ผู้ตัดสิน] */
    public const STEPS = [
        'owner' => ['ผู้รับผิดชอบโครงการ', 'ผู้รับผิดชอบโครงการของใบขอซื้อนั้น'],
        'procurement' => ['เจ้าหน้าที่พัสดุ', 'ผู้มีสิทธิ์ procurement.manage'],
        'finance' => ['เจ้าหน้าที่การเงิน', 'ผู้มีสิทธิ์ budget.manage'],
        'director' => ['ผู้อำนวยการ', 'ผู้มีสิทธิ์ budget.approve'],
    ];

    private const STEP_PERMISSIONS = ['procurement' => 'procurement.manage', 'finance' => 'budget.manage', 'director' => 'budget.approve'];

    protected $fillable = ['req_no', 'project_budget_id', 'requester_id', 'kind', 'title', 'reason', 'method', 'vendor', 'needed_on', 'total', 'status', 'steps', 'step', 'decided_at'];

    protected function casts(): array
    {
        return ['needed_on' => DateOnly::class, 'total' => 'decimal:2', 'steps' => 'array', 'step' => 'integer', 'decided_at' => 'datetime'];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(ProjectBudget::class, 'project_budget_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(PurchaseApproval::class)->orderBy('id');
    }

    /** ขั้นอนุมัติที่เปิดใช้อยู่ตอนนี้ (อย่างน้อยหนึ่งขั้นเสมอ) */
    public static function configuredSteps(): array
    {
        $keys = array_values(array_intersect(array_keys(self::STEPS), array_filter(explode(',', (string) Settings::get('purchase_steps')))));

        return $keys ?: ['director'];
    }

    public function currentStepKey(): ?string
    {
        return $this->status === 'pending' ? ($this->steps[$this->step] ?? null) : null;
    }

    public function currentStepLabel(): ?string
    {
        return ($key = $this->currentStepKey()) ? self::STEPS[$key][0] : null;
    }

    /** ผู้ใช้คนนี้ตัดสินขั้นที่รออยู่ได้หรือไม่ */
    public function canBeDecidedBy(User $user): bool
    {
        $key = $this->currentStepKey();

        return match (true) {
            $key === null => false,
            $key === 'owner' => $this->budget->project->owner_id === $user->id || $user->hasPermission('budget.manage'),
            default => $user->hasPermission(self::STEP_PERMISSIONS[$key]),
        };
    }

    /**
     * ใบที่รอผู้ใช้คนนี้ตัดสินอยู่ (ขั้นปัจจุบันของแต่ละใบอยู่ใน JSON จึงกรองหลังดึงมา จำนวนใบที่รออนุมัติมีไม่มาก)
     *
     * @return \Illuminate\Support\Collection<int, PurchaseRequest>
     */
    public static function awaiting(User $user)
    {
        return self::with(['budget.project', 'budget.source', 'requester'])->where('status', 'pending')->oldest('id')->get()
            ->filter(fn (PurchaseRequest $r) => $r->canBeDecidedBy($user))->values();
    }

    public function canBeViewedBy(User $user): bool
    {
        return Project::seesAll($user) || $this->requester_id === $user->id || $this->budget->project->owner_id === $user->id;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public static function nextNumber(): string
    {
        $fy = CodeSeries::fiscalYear(now());
        $seq = Sequence::next('PR', (string) $fy, fn () => (int) substr((string) self::where('req_no', 'like', "PR{$fy}%")->max('req_no'), -4));

        return 'PR'.$fy.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
