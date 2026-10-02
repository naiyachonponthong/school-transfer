<?php

namespace App\Support;

use App\Models\Book;

/** รหัสระเบียนบรรณานุกรม (ภายในระบบ) R000001 */
class BookRecordNumber extends CodeSeries
{
    protected const MODEL = Book::class;

    public const DEFAULT_PATTERN = 'R{SEQ6}';

    public const TOKENS = ['{SEQ}' => 'เลขลำดับ'];

    public static function pattern(): string
    {
        return static::DEFAULT_PATTERN;
    }

    public static function categories(): array
    {
        return [];
    }
}
