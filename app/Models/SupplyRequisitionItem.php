<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyRequisitionItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['requisition_id', 'supply_id', 'quantity', 'issued'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'issued' => 'integer'];
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }
}
