<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** สไลด์หน้าแรกของเว็บไซต์โรงเรียน */
class WebSlide extends Model
{
    protected $fillable = ['image', 'title', 'caption', 'url', 'sort', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function imageUrl(): string
    {
        return asset('storage/'.(string) $this->image);
    }
}
