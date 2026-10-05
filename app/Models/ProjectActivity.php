<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;

/**
 * กิจกรรมของโครงการ: งบอยู่ที่ระดับนี้ แยกตามประเภทเงิน คำขอใช้งบทุกใบอ้างกิจกรรมหนึ่งกิจกรรม
 */
class ProjectActivity extends Model
{
    protected $fillable = ['project_id', 'name', 'detail'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(ActivityBudget::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(BudgetRequest::class);
    }

    public function cuts(): HasManyThrough
    {
        return $this->hasManyThrough(BudgetRequestCut::class, BudgetRequest::class);
    }

    public function label(): string
    {
        return $this->project->code.' '.$this->project->name.' › '.$this->name;
    }

    public function total(): float
    {
        return (float) $this->budgets()->sum('amount');
    }

    /** ตัดงบไปแล้ว (คำขอที่ผ่านทุกขั้น) */
    public function spent(): float
    {
        return (float) $this->cuts()->sum('budget_request_cuts.amount');
    }

    /** รอพิจารณา: กันเงินไว้ก่อน ใบอื่นจะขอซ้อนจนเกินงบไม่ได้ */
    public function pending(): float
    {
        return (float) $this->requests()->where('status', 'pending')->sum('total');
    }

    public function available(): float
    {
        return round($this->total() - $this->spent() - $this->pending(), 2);
    }

    /**
     * งบคงเหลือแยกประเภทเงิน (งบ − ที่ตัดไปแล้ว) ใช้ตอนตัดงบ
     *
     * @return Collection<int, array{source: BudgetSource, budget: float, spent: float, left: float}> key = budget_source_id
     */
    public function bySource(): Collection
    {
        $spent = $this->cuts()->selectRaw('budget_source_id, sum(budget_request_cuts.amount) as total')->groupBy('budget_source_id')->pluck('total', 'budget_source_id');

        return $this->budgets()->with('source')->get()->sortBy(fn (ActivityBudget $b) => $b->source->name)->mapWithKeys(fn (ActivityBudget $b) => [$b->budget_source_id => [
            'source' => $b->source, 'budget' => (float) $b->amount, 'spent' => (float) ($spent[$b->budget_source_id] ?? 0),
            'left' => round((float) $b->amount - (float) ($spent[$b->budget_source_id] ?? 0), 2),
        ]]);
    }
}
