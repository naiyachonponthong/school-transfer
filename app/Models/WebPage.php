<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** หน้าเนื้อหาของเว็บไซต์โรงเรียน เช่น ประวัติ วิสัยทัศน์ พันธกิจ */
class WebPage extends Model
{
    /** หน้าตั้งต้นที่สร้างให้ตอนเปิดเว็บไซต์ครั้งแรก: slug => ชื่อ */
    public const STARTER = [
        'history' => 'ประวัติโรงเรียน',
        'vision' => 'วิสัยทัศน์ พันธกิจ เป้าประสงค์',
        'identity' => 'อัตลักษณ์และเอกลักษณ์',
        'curriculum' => 'หลักสูตรสถานศึกษา',
    ];

    protected $fillable = ['slug', 'title', 'body', 'cover', 'in_menu', 'sort', 'is_published'];

    protected function casts(): array
    {
        return ['in_menu' => 'boolean', 'is_published' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function coverUrl(): ?string
    {
        return $this->cover ? asset('storage/'.(string) $this->cover) : null;
    }
}
