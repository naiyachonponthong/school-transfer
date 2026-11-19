<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** ลิงก์หน่วยงานที่เกี่ยวข้อง / ลิงก์บริการ บนเว็บไซต์โรงเรียน */
class WebLink extends Model
{
    public const KINDS = ['agency' => 'หน่วยงานที่เกี่ยวข้อง', 'service' => 'บริการออนไลน์'];

    protected $fillable = ['kind', 'title', 'url', 'logo', 'icon', 'sort'];

    public function logoUrl(): ?string
    {
        return $this->logo ? asset('storage/'.(string) $this->logo) : null;
    }
}
