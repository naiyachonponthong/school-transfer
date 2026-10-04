<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** การเติมเงินเข้ากระเป๋า: เงินสด (เข้าทันที) หรือโอนแนบสลิป (รอการเงินตรวจ) */
class WalletTopup extends Model
{
    public const METHODS = ['cash' => 'เงินสด', 'transfer' => 'โอน/พร้อมเพย์'];

    public const STATUSES = ['pending' => ['รอตรวจสอบ', 'warning'], 'approved' => ['เข้ากระเป๋าแล้ว', 'success'], 'rejected' => ['ไม่อนุมัติ', 'danger']];

    protected $fillable = ['wallet_id', 'amount', 'method', 'status', 'slip', 'slip_hash', 'note', 'requested_by', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'reviewed_at' => 'datetime'];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
