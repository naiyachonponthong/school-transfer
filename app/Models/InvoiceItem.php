<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['invoice_id', 'description', 'amount', 'fee_item_id'];

    public function feeItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(FeeItem::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'float'];
    }
}
