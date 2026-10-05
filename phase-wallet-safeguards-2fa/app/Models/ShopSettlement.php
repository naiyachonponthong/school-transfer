<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Support\Sequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ใบจ่ายเงินให้ร้านค้า: ยอดขายที่ตัดจากกระเป๋าเงินของงวดหนึ่ง หักส่วนแบ่งของโรงเรียน เหลือยอดที่จ่ายให้ร้าน
 * ออกผ่าน WalletService::settle() เท่านั้น ผิดให้ยกเลิก (เลขที่และแถวเก็บไว้) แล้วออกใบใหม่
 */
class ShopSettlement extends Model
{
    public const METHODS = ['cash' => 'เงินสด', 'transfer' => 'โอนเข้าบัญชี'];

    protected $fillable = [
        'doc_no', 'shop_id', 'from_date', 'to_date', 'sales_count', 'gross', 'fee_percent', 'fee', 'net',
        'method', 'note', 'paid_by', 'paid_at', 'voided_at', 'voided_by', 'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => DateOnly::class, 'to_date' => DateOnly::class, 'paid_at' => 'datetime', 'voided_at' => 'datetime',
            'gross' => 'decimal:2', 'fee_percent' => 'decimal:2', 'fee' => 'decimal:2', 'net' => 'decimal:2',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(WalletSale::class, 'settlement_id');
    }

    /** ใบที่ยังมีผล (ใบที่ยกเลิกเก็บไว้เป็นหลักฐาน แต่ไม่นับยอด) */
    public function scopeValid(Builder $q): Builder
    {
        return $q->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? $this->method;
    }

    public function periodLabel(): string
    {
        return thai_date($this->from_date).($this->from_date->equalTo($this->to_date) ? '' : ' – '.thai_date($this->to_date));
    }

    public static function nextNumber(): string
    {
        $ym = now()->format('Ym');
        $seq = Sequence::next('SP', $ym, fn () => (int) substr((string) self::where('doc_no', 'like', "SP{$ym}%")->max('doc_no'), -4));

        return 'SP'.$ym.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
