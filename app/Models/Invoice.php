<?php

namespace App\Models;

use App\Support\Sequence;
use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const STATUSES = [
        'unpaid' => ['ค้างชำระ', 'danger'],
        'partial' => ['ชำระบางส่วน', 'warning'],
        'paid' => ['ชำระแล้ว', 'success'],
        'void' => ['ยกเลิก', 'secondary'],
    ];

    protected $fillable = ['invoice_no', 'student_id', 'term_id', 'title', 'due_date', 'total', 'discount', 'discount_note', 'paid', 'status', 'created_by', 'fee_plan_id'];

    protected function casts(): array
    {
        return [
            'due_date' => DateOnly::class,
            'last_reminded_at' => 'datetime',
            'total' => 'float',
            'discount' => 'float',
            'paid' => 'float',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('paid_at');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(InvoiceInstallment::class)->orderBy('seq');
    }

    /** งวดถัดไปที่ยังชำระไม่ครบ (เงินที่รับมาตัดงวดแรกก่อน) */
    public function nextInstallment(): ?InvoiceInstallment
    {
        $covered = 0.0;
        foreach ($this->installments as $inst) {
            $covered += $inst->amount;
            if ($this->paid + 0.001 < $covered) {
                return $inst;
            }
        }

        return null;
    }

    public function slips(): HasMany
    {
        return $this->hasMany(PaymentSlip::class);
    }

    public function netTotal(): float
    {
        return max(0, $this->total - $this->discount);
    }

    public function balance(): float
    {
        return max(0, round($this->netTotal() - $this->paid, 2));
    }

    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && in_array($this->status, ['unpaid', 'partial'], true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    /** คำนวณยอดชำระและสถานะใหม่จากรายการรับเงิน */
    public function refreshTotals(): void
    {
        $this->total = (float) $this->items()->sum('amount');
        $this->paid = (float) $this->payments()->valid()->sum('amount');
        if ($this->status !== 'void') {
            $this->status = match (true) {
                // ยอดสุทธิเป็นศูนย์ (ส่วนลดเต็มจำนวน) ถือว่าชำระครบ
                $this->paid + 0.001 >= $this->netTotal() => 'paid',
                $this->paid <= 0 => 'unpaid',
                default => 'partial',
            };
        }
        // แบ่งงวดไว้: กำหนดชำระของใบแจ้งหนี้ = วันครบกำหนดของงวดถัดไป การเตือนและสถานะเลยกำหนดจึงตามงวด
        if ($next = $this->nextInstallment()) {
            $this->due_date = $next->due_date;
        }
        $this->save();
    }

    public static function nextNumber(string $prefix = 'INV'): string
    {
        $ym = now()->format('Ym');
        $seq = Sequence::next($prefix, $ym, fn () => (int) substr((string) self::where('invoice_no', 'like', "{$prefix}{$ym}%")->max('invoice_no'), -5));

        return $prefix.$ym.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
