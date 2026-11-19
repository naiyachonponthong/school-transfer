<?php

namespace App\Support\License;

/**
 * กุญแจสาธารณะของร้าน (ed25519 base64) ฝังไว้ในโค้ด ไม่เอาไว้ใน .env
 * เพราะ .env ลูกค้าแก้ได้ ถ้าแก้ค่าพวกนี้ token ที่ร้านเซ็นจะตรวจไม่ผ่านและระบบจะล็อกตัวเอง
 *
 * สร้างคู่กุญแจที่ร้าน: php artisan license:keygen  และ  php artisan license:keygen --release
 */
final class Keys
{
    /** ตรวจลายเซ็นของ license token ที่ร้านออกให้ (คู่กุญแจบนเซิร์ฟเวอร์ร้านจริง ใช้ร่วมกับ attendance) */
    public const LICENSE_PUBLIC = 'y55Hg1aGeougkPY5TM/MC++jN0KAAQy1KQhvKU2BFLY=';

    /** ตรวจลายเซ็นรายการไฟล์ของชุดติดตั้ง (integrity manifest) */
    public const RELEASE_PUBLIC = 'ZH5gmAFfKlW8ZDVvsW/nnwacYTQA9VyDAHlVZ4PHkxo=';

    /** base64 ที่ผ่านฟอร์มหรือไฟล์มา อาจมีช่องว่างหรือขึ้นบรรทัดปน */
    public static function raw(string $base64): ?string
    {
        $clean = str_replace([' ', "\r", "\n", "\t"], ['+', '', '', ''], trim($base64));
        $bin = base64_decode($clean, true);

        return $bin !== false && strlen($bin) === 32 ? $bin : null;
    }
}
