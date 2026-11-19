<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * ภาพประกอบของระบบที่ผู้ดูแลเปลี่ยนเองได้ (ตั้งค่า > หน้าตาและระบบ > รูปภาพระบบ)
 * ไม่ได้อัปโหลด = ใช้ภาพเริ่มต้นที่มากับระบบ
 */
class Artwork
{
    /** slot => [ชื่อ, คำอธิบาย, ภาพเริ่มต้นใน public/] */
    public const SLOTS = [
        'dashboard' => ['หน้าหลัก', 'ภาพต้อนรับบนแดชบอร์ดและหัวแอปมือถือ', 'assets/img/school-community-hero.png'],
        'login' => ['เข้าสู่ระบบ', 'ภาพประกอบหน้าเข้าสู่ระบบ', 'assets/img/school-community-hero.png'],
        'pages' => ['แบนเนอร์หน้าต่าง ๆ', 'ภาพมุมขวาของแบนเนอร์หัวหน้า เช่น นักเรียน ห้องเรียน', 'assets/img/school-pages.png'],
        'settings' => ['หน้าตั้งค่า', 'ภาพประกอบส่วนหัวของหน้าตั้งค่า', 'assets/img/school-award.png'],
    ];

    public static function url(string $slot): string
    {
        $path = Settings::get('artwork_'.$slot);
        if ($path && Storage::disk('public')->exists($path)) {
            return asset('storage/'.$path);
        }

        return static::defaultUrl($slot);
    }

    public static function defaultUrl(string $slot): string
    {
        $default = static::SLOTS[$slot][2] ?? 'assets/img/school-community-hero.png';

        return asset(is_file(public_path($default)) ? $default : 'assets/img/school-community-hero.png');
    }

    /** เก็บภาพ ย่อด้านยาวไม่เกิน 1600px (ถ้ามี GD) คงพื้นหลังโปร่งใส คืน path ใน disk public */
    public static function store(UploadedFile $file, int $max = 1600): string
    {
        $path = $file->store('artwork', 'public');
        $full = Storage::disk('public')->path($path);
        $info = @getimagesize($full);
        if (! $info || ! function_exists('imagecreatetruecolor') || max($info[0], $info[1]) <= $max) {
            return $path;
        }
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($full),
            IMAGETYPE_PNG => @imagecreatefrompng($full),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($full) : false,
            default => false,
        };
        if (! $src) {
            return $path;
        }
        $ratio = $max / max($info[0], $info[1]);
        $w = (int) round($info[0] * $ratio);
        $h = (int) round($info[1] * $ratio);
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
        match ($info[2]) {
            IMAGETYPE_JPEG => imagejpeg($dst, $full, 88),
            IMAGETYPE_PNG => imagepng($dst, $full, 6),
            default => imagewebp($dst, $full, 88),
        };
        imagedestroy($src);
        imagedestroy($dst);

        return $path;
    }

    public static function delete(?string $path): void
    {
        if ($path && str_starts_with($path, 'artwork/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
