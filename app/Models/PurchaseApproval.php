<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** ผลการพิจารณาหนึ่งขั้นของใบขอซื้อ/ขอจ้าง (เพิ่มอย่างเดียว) */
class PurchaseApproval extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['purchase_request_id', 'step', 'step_key', 'user_id', 'decision', 'note'];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
