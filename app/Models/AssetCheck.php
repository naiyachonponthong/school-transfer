<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ผลการตรวจสอบพัสดุประจำปีของครุภัณฑ์หนึ่งชิ้น */
class AssetCheck extends Model
{
    public const RESULTS = [
        'found' => ['พบ ใช้งานได้', 'success'],
        'damaged' => ['พบ ชำรุด', 'warning'],
        'missing' => ['ไม่พบ', 'danger'],
    ];

    protected $fillable = ['year', 'asset_id', 'result', 'note', 'checked_by'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
