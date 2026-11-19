<?php

namespace App\Support;

/**
 * เครดิตท้ายหน้า แก้ได้ที่ ตั้งค่า > หน้าตาและระบบ > หน้าตา (ว่าง = ไม่แสดง)
 * ห้ามฝังชื่อผู้พัฒนาในโค้ด
 */
class Brand
{
    public static function credit(): string
    {
        try {
            return trim((string) Settings::get('brand_credit', ''));
        } catch (\Throwable) {
            return ''; // ตัวติดตั้งยังไม่มีฐานข้อมูล
        }
    }

    /** บรรทัดท้ายเอกสารพิมพ์: ชื่อระบบ + เครดิต (ถ้ามี) */
    public static function line(string $system = 'ระบบบริหารโรงเรียน'): string
    {
        $credit = static::credit();

        return $credit === '' ? $system : $system.' · '.$credit;
    }
}
