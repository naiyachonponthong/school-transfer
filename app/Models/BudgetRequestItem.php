<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** รายการหนึ่งบรรทัดในคำขอใช้งบ */
class BudgetRequestItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['budget_request_id', 'description', 'item_type', 'quantity', 'unit', 'unit_price', 'amount'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'amount' => 'decimal:2'];
    }
}
