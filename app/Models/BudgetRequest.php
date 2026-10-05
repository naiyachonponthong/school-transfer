<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Support\CodeSeries;
use App\Support\Sequence;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * คำขอใช้งบของกิจกรรม: ขอซื้อ/จ้าง ขอเบิกเงิน หรือขอยืมเงิน
 * ผ่านการพิจารณาตามลำดับขั้น ขั้นสุดท้ายคือเจ้าหน้าที่ตัดงบ ซึ่งระบุว่าตัดจากประเภทเงินใดเท่าไร
 * เงินถูกกันไว้ตั้งแต่ยื่น (ขอเกินงบคงเหลือของกิจกรรมไม่ได้) และถูกตัดจริงเมื่อผ่านขั้นตัดงบ
 */
class BudgetRequest extends Model
{
    public const TYPES = ['buy_hire' => 'ขอซื้อ/จ้าง', 'disburse' => 'ขอเบิกเงิน', 'loan' => 'ขอยืมเงิน'];

    public const METHODS = ['specific' => 'เฉพาะเจาะจง', 'selection' => 'คัดเลือก', 'ebidding' => 'ประกวดราคาอิเล็กทรอนิกส์ (e-bidding)', 'other' => 'อื่น ๆ'];

    public const ITEM_TYPES = ['supply' => 'วัสดุ', 'asset' => 'ครุภัณฑ์', 'service' => 'จ้าง/บริการ', 'other' => 'อื่น ๆ'];

    public const STATUSES = ['pending' => ['รอพิจารณา', 'warning'], 'approved' => ['ตัดงบแล้ว', 'success'], 'rejected' => ['ไม่อนุมัติ', 'danger'], 'cancelled' => ['ยกเลิก', 'secondary']];

    /** ขั้นพิจารณา: key => [ชื่อขั้น, สิทธิ์ของผู้พิจารณา] · สามขั้นแรกเปิด-ปิดได้ ขั้นตัดงบมีเสมอ */
    public const STEPS = [
        'review' => ['ผู้ตรวจสอบเอกสาร', 'budget.review'],
        'vice' => ['รองผู้อำนวยการ', 'budget.approve_vice'],
        'director' => ['ผู้อำนวยการ', 'budget.approve'],
        'cut' => ['เจ้าหน้าที่ตัดงบ', 'budget.cut'],
    ];

    public const OPTIONAL_STEPS = ['review', 'vice', 'director'];

    protected $fillable = ['req_no', 'project_activity_id', 'requester_id', 'type', 'title', 'reason', 'method', 'vendor', 'needed_on', 'total', 'status', 'steps', 'step', 'decided_at'];

    protected function casts(): array
    {
        return ['needed_on' => DateOnly::class, 'total' => 'decimal:2', 'steps' => 'array', 'step' => 'integer', 'decided_at' => 'datetime'];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ProjectActivity::class, 'project_activity_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetRequestItem::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(BudgetRequestApproval::class)->orderBy('id');
    }

    public function cuts(): HasMany
    {
        return $this->hasMany(BudgetRequestCut::class);
    }

    /** ลำดับขั้นที่ใช้กับใบที่ยื่นตอนนี้ */
    public static function configuredSteps(): array
    {
        $chosen = array_filter(explode(',', (string) Settings::get('budget_request_steps')));

        return array_merge(array_values(array_intersect(self::OPTIONAL_STEPS, $chosen)), ['cut']);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function currentStepKey(): ?string
    {
        return $this->status === 'pending' ? ($this->steps[$this->step] ?? null) : null;
    }

    public function currentStepLabel(): ?string
    {
        return ($key = $this->currentStepKey()) ? self::STEPS[$key][0] : null;
    }

    /**
     * ผู้ใช้คนนี้พิจารณาขั้นที่รออยู่ได้หรือไม่
     * ขั้นตัดงบแยกตามกลุ่มของโครงการ: ห้องเรียนพิเศษต้องมีสิทธิ์ budget.cut_special กลุ่มทั่วไปใช้ budget.cut
     */
    public function canBeDecidedBy(User $user): bool
    {
        $key = $this->currentStepKey();
        if ($key === null) {
            return false;
        }
        if ($key === 'cut') {
            return $user->hasPermission($this->activity->project->track === 'special' ? 'budget.cut_special' : 'budget.cut');
        }

        return $user->hasPermission(self::STEPS[$key][1]);
    }

    /**
     * ใบที่รอผู้ใช้คนนี้พิจารณา (ขั้นปัจจุบันอยู่ใน JSON ของแต่ละใบ จึงกรองหลังดึงมา จำนวนใบที่รอมีไม่มาก)
     *
     * @return \Illuminate\Support\Collection<int, BudgetRequest>
     */
    public static function awaiting(User $user)
    {
        return self::with(['activity.project', 'requester'])->where('status', 'pending')->oldest('id')->get()
            ->filter(fn (BudgetRequest $r) => $r->canBeDecidedBy($user))->values();
    }

    public function canBeViewedBy(User $user): bool
    {
        return Project::seesAll($user) || $this->requester_id === $user->id || $this->activity->project->owner_id === $user->id;
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
        $seq = Sequence::next('BR', (string) $fy, fn () => (int) substr((string) self::where('req_no', 'like', "BR{$fy}%")->max('req_no'), -4));

        return 'BR'.$fy.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
