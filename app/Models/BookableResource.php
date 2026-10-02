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

    protected $fillable = ['name', 'type', 'capacity', 'description', 'requires_approval', 'is_active'];

    protected function casts(): array
    {
        return ['requires_approval' => 'boolean', 'is_active' => 'boolean', 'capacity' => 'integer'];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'resource_id');
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
