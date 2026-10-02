<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Settings
{
    public const DEFAULTS = [
        'school_name' => 'โรงเรียนตัวอย่างวิทยา',
        'school_short' => 'ตัวอย่างวิทยา',
        'school_address' => '',
        'school_phone' => '',
        'director_name' => '',
        'academic_deputy_name' => '',  // รองผู้อำนวยการฝ่ายวิชาการ (ลงนาม ปพ.5)
        'measurement_head_name' => '', // หัวหน้างานวัดผล (ลงนาม ปพ.5)
        'registrar_name' => '',        // นายทะเบียน (ลงนาม ปพ.1 / ปพ.7)
        'late_time' => '08:00',        // หลังเวลานี้ถือว่าสาย
        'staff_late_time' => '08:00',
        'periods_per_day' => '7',
        'period_times' => "08:30-09:20\n09:20-10:10\n10:20-11:10\n11:10-12:00\n13:00-13:50\n13:50-14:40\n14:40-15:30",
        'theme_color' => Theme::DEFAULT,
        'promptpay_id' => '',
        'bank_info' => '',
        // LINE Official Account (Messaging API)
        'line_channel_token' => '',
        'line_channel_secret' => '',
        'line_oa_id' => '',          // เช่น @school
        'line_notify_gate' => '1',   // แจ้งผู้ปกครองเมื่อสแกนเข้า/ออก
        'line_notify_absent' => '1', // แจ้งเมื่อขาด/สาย
        // ประตูโรงเรียน
        'gate_checkout_after' => '14:00',
        // GPS ลงเวลาครู
        'gps_required' => '0',
        'school_lat' => '',
        'school_lng' => '',
        'gps_radius' => '300',
        // ห้องสมุด
        'library_loan_days' => '7',
        // รับสมัคร
        'admission_open' => '1',
        'admission_levels' => 'ม.1,ม.4',
    ];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $stored = Cache::rememberForever('school.settings', function () {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return DB::table('settings')->pluck('value', 'key')->all();
        });

        return self::$cache = array_merge(self::DEFAULTS, $stored);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value]);
        }
        Cache::forget('school.settings');
        self::$cache = null;
    }

    /** @return list<string> เวลาแต่ละคาบ */
    public static function periodTimes(): array
    {
        $lines = preg_split('/\R/', (string) self::get('period_times'));
        $count = max(1, (int) self::get('periods_per_day', 7));

        return array_pad(array_slice(array_map('trim', $lines), 0, $count), $count, '');
    }
}
