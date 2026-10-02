<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyTransaction extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = ['in' => 'รับเข้า', 'out' => 'จ่ายออก', 'adjust' => 'ปรับยอด'];

    protected $fillable = ['supply_id', 'type', 'quantity', 'balance', 'requisition_id', 'user_id', 'note'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(SupplyRequisition::class);
    }
}
