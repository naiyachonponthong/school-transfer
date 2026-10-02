<?php

namespace App\Models;

use App\Support\BookRecordNumber;
use App\Support\Dewey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * ระเบียนบรรณานุกรม (1 ชื่อเรื่อง) — ตัวเล่มจริงอยู่ที่ BookCopy
 * code = รหัสระเบียนในระบบ · copies = จำนวนเล่มที่ยังอยู่ในห้องสมุด (สรุปจากตัวเล่ม)
 */
class Book extends Model
{
    protected $fillable = ['code', 'isbn', 'collection', 'class_number', 'author_mark', 'volume', 'title', 'author', 'contributors', 'edition',
        'pub_place', 'publisher', 'pub_year', 'pages', 'illustrated', 'size_cm', 'series', 'language', 'subjects', 'summary', 'note', 'cover',
        'category', 'copies', 'location'];

    protected function casts(): array
    {
        return ['illustrated' => 'boolean', 'pages' => 'integer', 'size_cm' => 'integer', 'copies' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Book $b) => $b->code ??= BookRecordNumber::next()[0]);
        // หมวด (ใช้กรอง/สถิติ) ตามประเภททรัพยากรหรือหมวดใหญ่ DDC
        static::saving(function (Book $b) {
            $b->collection ??= 'general';
            if ($b->collection !== 'general') {
                $b->category = Dewey::COLLECTIONS[$b->collection][0] ?? $b->category;
            } elseif ($label = Dewey::label($b->class_number)) {
                $b->category = $label;
            }
        });
        // สร้างหนังสือแบบเดิม (ระบุจำนวนเล่ม ไม่ได้เพิ่มตัวเล่ม) → สร้างตัวเล่มให้ เล่มแรกใช้รหัสระเบียนเป็นบาร์โค้ด
        static::created(function (Book $b) {
            for ($n = 1; $n <= (int) $b->copies; $n++) {
                $b->items()->create(['barcode' => $n === 1 ? $b->code : $b->code.'-'.$n, 'copy_no' => $n, 'location' => $b->location]);
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookCopy::class)->orderBy('copy_no');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(BookLoan::class);
    }

    public function activeLoans(): HasMany
    {
        return $this->hasMany(BookLoan::class)->whereNull('returned_on');
    }

    /** เล่มที่ให้บริการได้ (ไม่ส่งซ่อม/สูญหาย/จำหน่าย) และไม่ถูกยืมอยู่ */
    public function available(): int
    {
        $circulating = $this->circulating_count ?? $this->items()->whereNotIn('condition', BookCopy::OUT_OF_SERVICE)->count();
        $out = $this->active_loans_count ?? $this->activeLoans()->count();

        return max(0, $circulating - $out);
    }

    /** [ชื่อ, สัญลักษณ์, ตำแหน่ง (none/prefix/class), ให้ยืมได้] */
    public function collectionInfo(): array
    {
        return Dewey::COLLECTIONS[$this->collection ?? 'general'] ?? Dewey::COLLECTIONS['general'];
    }

    public function loanable(): bool
    {
        return (bool) $this->collectionInfo()[3];
    }

    /**
     * เลขเรียกหนังสือ เรียงบรรทัดตามป้ายสัน: [ย/อ] · เลขหมู่ (หรือ น/รส) · เลขผู้แต่ง · ล. · ฉ. (ฉบับที่ 2 ขึ้นไป)
     *
     * @return list<string>
     */
    public function callNumber(?BookCopy $copy = null): array
    {
        [, $symbol, $place] = $this->collectionInfo();
        $lines = [];
        if ($place === 'prefix') {
            $lines[] = $symbol;
        }
        if ($place === 'class') {
            $lines[] = $symbol;
        } elseif ($this->class_number) {
            $lines[] = $this->class_number;
        }
        if ($this->author_mark) {
            $lines[] = $this->author_mark;
        }
        if ($this->volume) {
            $lines[] = 'ล.'.preg_replace('/^ล\.?\s*/u', '', $this->volume);
        }
        if ($copy && $copy->copy_no > 1) {
            $lines[] = 'ฉ.'.$copy->copy_no;
        }

        return $lines;
    }

    public function callNumberText(?BookCopy $copy = null): string
    {
        return implode(' ', $this->callNumber($copy));
    }

    /** หมวดใหญ่ DDC (สีแถบบนป้ายสัน) */
    public function mainClass(): ?string
    {
        return $this->collectionInfo()[2] === 'class' ? '800' : Dewey::mainClass($this->class_number);
    }

    /** พิมพลักษณ์ เช่น "พิมพ์ครั้งที่ 2. กรุงเทพฯ : นานมีบุ๊คส์, 2565" */
    public function imprint(): string
    {
        $pub = trim(($this->pub_place ? $this->pub_place.' : ' : '').($this->publisher ?? '').($this->pub_year ? ', '.$this->pub_year : ''), ' ,:');

        return trim(($this->edition ? 'พิมพ์ครั้งที่ '.preg_replace('/^พิมพ์ครั้งที่\s*/u', '', $this->edition).'. ' : '').$pub);
    }

    /** ลักษณะรูปเล่ม เช่น "120 หน้า : ภาพประกอบ ; 21 ซม." */
    public function physical(): string
    {
        $out = $this->pages ? $this->pages.' หน้า' : '';
        if ($this->illustrated) {
            $out .= ($out ? ' : ' : '').'ภาพประกอบ';
        }
        if ($this->size_cm) {
            $out .= ($out ? ' ; ' : '').$this->size_cm.' ซม.';
        }

        return $out;
    }

    /** @return list<string> */
    public function subjectList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[;\n]/u', (string) $this->subjects))));
    }

    public function coverUrl(): ?string
    {
        return $this->cover ? Storage::disk('public')->url($this->cover) : null;
    }

    /** จำนวนเล่มที่ยังอยู่ในห้องสมุด (ไม่นับที่จำหน่ายออก) */
    public function syncCopies(): void
    {
        $this->forceFill(['copies' => $this->items()->where('condition', '!=', 'withdrawn')->count()])->saveQuietly();
    }
}
