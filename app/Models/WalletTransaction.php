<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** บรรทัดในสมุดรายการของกระเป๋าเงิน เพิ่มอย่างเดียว ไม่แก้ ไม่ลบ */
class WalletTransaction extends Model
{
    public const TYPES = [
        'topup' => ['เติมเงิน', 'success'], 'purchase' => ['ซื้อสินค้า', 'primary'], 'void' => ['คืนเงินจากการยกเลิก', 'info'],
        'adjust' => ['ปรับยอด', 'secondary'], 'withdraw' => ['ถอนคืน', 'warning'],
    ];

    public const UPDATED_AT = null;

    protected $fillable = ['wallet_id', 'type', 'amount', 'balance_after', 'sale_id', 'topup_id', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'balance_after' => 'decimal:2'];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(WalletSale::class, 'sale_id');
    }
}
