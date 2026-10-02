<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** ตัวเล่ม 1 เล่ม: บาร์โค้ด (ยืม-คืน) · เลขทะเบียน · ฉบับที่ · ชั้นจัดเก็บ · สภาพ */
class BookCopy extends Model
{
    public const CONDITIONS = [
        'good' => ['ดี', 'success'],
        'fair' => ['พอใช้', 'info'],
        'damaged' => ['ชำรุด', 'warning'],
        'repair' => ['ส่งซ่อม', 'warning'],
        'lost' => ['สูญหาย', 'danger'],
        'withdrawn' => ['จำหน่ายออก', 'secondary'],
    ];

    /** สภาพที่ไม่ให้บริการยืม */
    public const OUT_OF_SERVICE = ['repair', 'lost', 'withdrawn'];

    public const SOURCES = ['จัดซื้อ', 'ได้รับบริจาค', 'งบประมาณ/เงินอุดหนุน', 'โครงการ', 'แลกเปลี่ยน'];

    protected $fillable = ['book_id', 'barcode', 'accession_no', 'copy_no', 'location', 'condition', 'price', 'acquired_on', 'source', 'note', 'label_printed_at'];

    protected function casts(): array
    {
        return ['acquired_on' => DateOnly::class, 'price' => 'float', 'copy_no' => 'integer', 'label_printed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        $sync = fn (BookCopy $c) => $c->book?->syncCopies();
        static::saved($sync);
        static::deleted($sync);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(BookLoan::class)->latest('borrowed_on');
    }

    public function activeLoan(): HasOne
    {
        return $this->hasOne(BookLoan::class)->whereNull('returned_on')->latestOfMany();
    }

    public function isCirculating(): bool
    {
        return ! in_array($this->condition, self::OUT_OF_SERVICE, true);
    }

    public function conditionLabel(): string
    {
        return self::CONDITIONS[$this->condition][0] ?? $this->condition;
    }

    public function conditionColor(): string
    {
        return self::CONDITIONS[$this->condition][1] ?? 'secondary';
    }

    /** @return list<string> */
    public function callNumber(): array
    {
        return $this->book->callNumber($this);
    }
}
