<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** เงินที่ตัดจริงของคำขอ จากประเภทเงินหนึ่งประเภท */
class BudgetRequestCut extends Model
{
    public $timestamps = false;

    protected $fillable = ['budget_request_id', 'budget_source_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(BudgetSource::class, 'budget_source_id');
    }
}
