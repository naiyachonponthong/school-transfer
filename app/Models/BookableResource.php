<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** ห้อง / รถ / อุปกรณ์ที่จองได้ */
class BookableResource extends Model
{
    public const TYPES = [
        'room' => ['ห้อง', 'bi-door-open'],
        'vehicle' => ['รถ', 'bi-truck-front'],
        'equipment' => ['อุปกรณ์', 'bi-projector'],
    ];

    protected $fillable = ['name', 'type', 'capacity', 'description', 'requires_approval', 'is_active', 'photo', 'location', 'amenities', 'plate_no', 'contact', 'rules'];

    /** ตัวเลือกสิ่งอำนวยความสะดวกที่ใช้บ่อย (พิมพ์เพิ่มเองได้) */
    public const AMENITY_SUGGESTIONS = ['โปรเจกเตอร์', 'จอทีวี', 'เครื่องเสียง', 'ไมโครโฟน', 'เครื่องปรับอากาศ', 'Wi-Fi', 'กระดานไวท์บอร์ด', 'เวที', 'โต๊ะประชุม', 'ปลั๊กไฟ'];

    protected function casts(): array
    {
        return ['requires_approval' => 'boolean', 'is_active' => 'boolean', 'capacity' => 'integer'];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'resource_id');
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? asset('storage/'.$this->photo) : null;
    }

    /** @return list<string> */
    public function amenityList(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->amenities))));
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? $this->type;
    }

    public function icon(): string
    {
        return self::TYPES[$this->type][1] ?? 'bi-calendar';
    }
}
