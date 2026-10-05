<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** งบของกิจกรรมจากประเภทเงินหนึ่งประเภท */
class ActivityBudget extends Model
{
    protected $fillable = ['project_activity_id', 'budget_source_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ProjectActivity::class, 'project_activity_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(BudgetSource::class, 'budget_source_id');
    }
}
