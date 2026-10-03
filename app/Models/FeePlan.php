<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Services\Notifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** แผนเรียกเก็บค่าธรรมเนียมของระดับชั้นหนึ่งในภาคเรียน */
class FeePlan extends Model
{
    protected $fillable = ['title', 'year', 'term_id', 'level', 'due_date'];

    protected function casts(): array
    {
        return ['due_date' => DateOnly::class];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeePlanItem::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function total(): float
    {
        return (float) $this->items->sum('amount');
    }

    /** นักเรียนที่กำลังเรียนในระดับชั้นของแผนนี้ และยังไม่ได้รับใบแจ้งหนี้จากแผนนี้ */
    public function pendingStudents()
    {
        $issued = $this->invoices()->where('status', '!=', 'void')->pluck('student_id');

        return Student::active()
            ->whereHas('classroom', fn ($q) => $q->where('year', $this->year)->where('level', $this->level))
            ->whereNotIn('id', $issued);
    }

    /**
     * ออกใบแจ้งหนี้ให้ทุกคนที่ยังไม่ได้รับ (รันซ้ำได้ ไม่ออกซ้ำ) หักส่วนลดประจำตัวให้อัตโนมัติ
     *
     * @return int จำนวนใบที่ออก
     */
    public function issue(User $by): int
    {
        $this->load('items.feeItem');
        $students = $this->pendingStudents()->get();
        if ($this->items->isEmpty() || $students->isEmpty()) {
            return 0;
        }
        $discounts = StudentDiscount::applicable($this->year)->whereIn('student_id', $students->pluck('id'))->get()->groupBy('student_id');
        $total = $this->total();

        DB::transaction(function () use ($students, $discounts, $total, $by) {
            foreach ($students as $student) {
                [$discount, $note] = StudentDiscount::calculate($discounts[$student->id] ?? collect(), $this->items);
                $invoice = Invoice::create([
                    'invoice_no' => Invoice::nextNumber(),
                    'student_id' => $student->id,
                    'term_id' => $this->term_id,
                    'fee_plan_id' => $this->id,
                    'title' => $this->title,
                    'due_date' => $this->due_date,
                    'total' => $total,
                    'discount' => $discount,
                    'discount_note' => $note,
                    'status' => $discount + 0.001 >= $total ? 'paid' : 'unpaid',
                    'created_by' => $by->id,
                ]);
                $invoice->items()->createMany($this->items->map(fn ($i) => [
                    'description' => $i->feeItem->name, 'amount' => $i->amount, 'fee_item_id' => $i->fee_item_id,
                ])->all());

                if ($invoice->status !== 'paid') {
                    Notifier::parents($student, "🧾 ใบแจ้งหนี้ใหม่: {$this->title} ยอด ".baht($invoice->balance()).' บาท'
                        .($this->due_date ? ' กำหนดชำระ '.thai_date($this->due_date) : '').' ชำระผ่าน QR พร้อมเพย์ได้ในระบบ', route('invoices.show', $invoice));
                }
            }
        });

        return $students->count();
    }
}
