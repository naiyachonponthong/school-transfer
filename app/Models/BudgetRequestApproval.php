<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ผลการพิจารณาหนึ่งขั้นของคำขอใช้งบ พร้อมสำเนาลายเซ็นของผู้พิจารณา (เพิ่มอย่างเดียว) */
class BudgetRequestApproval extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['budget_request_id', 'step', 'step_key', 'user_id', 'decision', 'note', 'signature'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(BudgetRequest::class, 'budget_request_id');
    }
}
