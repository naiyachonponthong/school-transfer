<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeePlanItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['fee_plan_id', 'fee_item_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'float'];
    }

    public function feeItem(): BelongsTo
    {
        return $this->belongsTo(FeeItem::class);
    }
}
