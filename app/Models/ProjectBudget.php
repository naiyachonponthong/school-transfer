<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * งบหนึ่งบรรทัดของโครงการ: แหล่งเงิน × หมวดรายจ่าย
 * ใบขอซื้อแต่ละใบตัดเงินจากบรรทัดเดียว ยอดคงเหลือจึงคิดได้ตรงตัว
 */
class ProjectBudget extends Model
{
    public const CATEGORIES = ['compensation' => 'ค่าตอบแทน', 'service' => 'ค่าใช้สอย', 'supplies' => 'ค่าวัสดุ', 'equipment' => 'ค่าครุภัณฑ์', 'other' => 'อื่น ๆ'];

    protected $fillable = ['project_id', 'budget_source_id', 'category', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(BudgetSource::class, 'budget_source_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function label(): string
    {
        return $this->categoryLabel().' · '.($this->source?->name ?? '-');
    }

    /** ผูกพันแล้ว: ใบขอซื้อที่อนุมัติครบทุกขั้น */
    public function committed(): float
    {
        return (float) $this->requests()->where('status', 'approved')->sum('total');
    }

    /** รออนุมัติ: กันเงินไว้ก่อน ใบอื่นจะขอซ้อนจนเกินงบไม่ได้ */
    public function pending(): float
    {
        return (float) $this->requests()->where('status', 'pending')->sum('total');
    }

    public function available(): float
    {
        return round((float) $this->amount - $this->committed() - $this->pending(), 2);
    }
}
