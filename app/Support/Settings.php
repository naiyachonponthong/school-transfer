<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Settings
{
    public const DEFAULTS = [
        'school_name' => 'โรงเรียนตัวอย่างวิทยา',
        'school_short' => 'ตัวอย่างวิทยา',
        'school_address' => '',
        'school_phone' => '',
        'school_affiliation' => '',    // สังกัด / เขตพื้นที่การศึกษา (ปพ.1 / ปพ.3)
        'school_province' => '',
        'director_name' => '',
        'academic_deputy_name' => '',  // รองผู้อำนวยการฝ่ายวิชาการ (ลงนาม ปพ.5)
        'measurement_head_name' => '', // หัวหน้างานวัดผล (ลงนาม ปพ.5)
        'registrar_name' => '',        // นายทะเบียน (ลงนาม ปพ.1 / ปพ.7)
        'facility_manager_ids' => '',  // ครูงานพัสดุ/อาคารสถานที่ (id คั่นด้วย ,)
        'asset_no_pattern' => AssetNumber::DEFAULT_PATTERN, // รูปแบบเลขครุภัณฑ์อัตโนมัติ
        'asset_category_codes' => '',  // รหัสประเภทครุภัณฑ์ที่ตั้งเอง (JSON)
        'supply_no_pattern' => SupplyNumber::DEFAULT_PATTERN, // รูปแบบรหัสวัสดุอัตโนมัติ
        'supply_category_codes' => '', // รหัสหมวดวัสดุที่ตั้งเอง (JSON)
        'late_time' => '08:00',        // หลังเวลานี้ถือว่าสาย
        'staff_late_time' => '08:00',
        'periods_per_day' => '7',
        'grade_scale' => '80,75,70,65,60,55,50', // คะแนนขั้นต่ำของเกรด 4, 3.5, 3, 2.5, 2, 1.5, 1
        'privacy_notice' => '',   // ประกาศความเป็นส่วนตัว (เว้นว่าง = ไม่บังคับให้รับทราบ)
        'privacy_purge_auto' => '0', // ลบข้อมูลที่พ้นระยะเก็บอัตโนมัติทุกเดือน (privacy:purge)
        'privacy_version' => '1', // เพิ่มเลขเมื่อแก้ประกาศแล้วต้องการให้ทุกคนรับทราบใหม่
        'school_days' => '5', // 5 = จันทร์–ศุกร์, 6 = รวมวันเสาร์
        'period_times' => "08:30-09:20\n09:20-10:10\n10:20-11:10\n11:10-12:00\n13:00-13:50\n13:50-14:40\n14:40-15:30",
        'theme_color' => Theme::DEFAULT,
        'promptpay_id' => '',
        'bank_info' => '',
        'wallet_biller_id' => '',       // Biller ID 15 หลักของบัญชีรับชำระ (เติมเงินกระเป๋าอัตโนมัติ)
        'wallet_gateway_secret' => '',  // รหัสลับที่ธนาคาร/ตัวกลางใช้เซ็นข้อความแจ้งผลการชำระ
        'wallet_reconciled_at' => '',   // ผลกระทบยอดกระเป๋าเงินครั้งล่าสุด (App\Support\WalletReconciler)
        'wallet_reconcile_count' => '0',
        'wallet_reconcile_issues' => '',
        'purchase_steps' => 'owner,procurement,finance,director', // ขั้นอนุมัติใบขอซื้อ/ขอจ้างที่เปิดใช้ (App\Models\PurchaseRequest::STEPS)
        'two_factor_required' => '0',   // บังคับยืนยันตัวตน 2 ขั้นสำหรับผู้ดูแลระบบและผู้ที่ดูแลเงิน/ผู้ใช้/ตั้งค่า
        // LINE Official Account (Messaging API)
        'line_channel_token' => '',
        'line_channel_secret' => '',
        'line_login_channel_id' => '',     // LINE Login (เข้าสู่ระบบด้วย LINE)
        'line_login_channel_secret' => '',
        'webpush_public_key' => '',        // กุญแจ VAPID ของแจ้งเตือนบนอุปกรณ์ (สร้างอัตโนมัติ)
        'webpush_private_key' => '',
        'club_signup_from' => '',          // ช่วงที่นักเรียนเลือกชุมนุมเองได้ (App\Models\Club)
        'club_signup_until' => '',
        'demo_mode' => '0',                // โหมดทดลองใช้ (App\Support\Demo)
        'demo_reset' => '0',
        'demo_user_exec' => '', 'demo_user_teacher' => '', 'demo_user_parent' => '', 'demo_user_student' => '',
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
        'library_barcode_pattern' => 'B{SEQ8}',   // บาร์โค้ดตัวเล่ม
        'library_accession_pattern' => '{SEQ5}',  // เลขทะเบียนหนังสือ
        'library_loan_days' => '7',
        // รับสมัคร
        'admission_open' => '1',
        'admission_levels' => 'ม.1,ม.4',
    ];

    /** เก็บแบบเข้ารหัสในฐานข้อมูล (ไฟล์สำรองหลุดไปก็อ่านไม่ได้ถ้าไม่มี APP_KEY) */
    public const ENCRYPTED = ['line_channel_token', 'line_channel_secret', 'line_login_channel_secret', 'webpush_private_key', 'wallet_gateway_secret'];

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

        foreach (self::ENCRYPTED as $key) {
            if (filled($stored[$key] ?? null)) {
                try {
                    $stored[$key] = Crypt::decryptString($stored[$key]);
                } catch (DecryptException) {
                    // ค่าที่บันทึกไว้ก่อนเริ่มเข้ารหัส ใช้ได้ตามเดิม และจะถูกเข้ารหัสเมื่อบันทึกครั้งถัดไป
                }
            }
        }

        return self::$cache = array_merge(self::DEFAULTS, $stored);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::ENCRYPTED, true) && filled($value)) {
                $value = Crypt::encryptString((string) $value);
            }
            DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value]);
        }
        self::flush();
    }

    /** ล้างค่าที่จำไว้ (ค่าที่จำในตัวแปร static อยู่ข้ามคำขอได้ในโปรเซสที่รันยาว เช่น เทสต์ / queue) */
    public static function flush(): void
    {
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
