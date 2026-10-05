<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** รายการหนึ่งบรรทัดในใบขอซื้อ/ขอจ้าง */
class PurchaseRequestItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['purchase_request_id', 'description', 'item_type', 'quantity', 'unit', 'unit_price', 'amount'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'amount' => 'decimal:2'];
    }
}
