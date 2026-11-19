<?php

namespace App\Support;

use App\Models\User;
use App\Models\WebPage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** เว็บไซต์โรงเรียน (หน้าบ้าน) */
class Website
{
    public static function enabled(): bool
    {
        return Settings::get('website_enabled') === '1';
    }

    /** ผู้ที่ดูเว็บไซต์ได้แม้ยังปิดอยู่ (เตรียมเนื้อหาก่อนเปิด) */
    public static function canManage(?User $user): bool
    {
        return (bool) $user?->hasPermission('website.manage');
    }

    public static function visibleTo(?User $user): bool
    {
        return static::enabled() || static::canManage($user);
    }

    /** หน้าเนื้อหาที่ขึ้นเมนู "เกี่ยวกับโรงเรียน" */
    public static function menuPages()
    {
        return WebPage::where('is_published', true)->where('in_menu', true)->orderBy('sort')->orderBy('id')->get(['slug', 'title']);
    }

    /** เก็บรูป (ย่อด้านยาวไม่เกิน 1600px) ใน storage/app/public/web/... */
    public static function storeImage(UploadedFile $file, string $folder): string
    {
        return Artwork::store($file, 1600, 'web/'.$folder);
    }

    /** เก็บไฟล์แนบ (PDF ฯลฯ) ชื่อเดิมของไฟล์เก็บไว้แสดง */
    public static function storeFile(UploadedFile $file): array
    {
        return ['path' => $file->store('web/files', 'public'), 'name' => mb_substr($file->getClientOriginalName(), 0, 150)];
    }

    /** ลบไฟล์ของเว็บไซต์ (เฉพาะใน web/ กันลบไฟล์อื่นของระบบ) */
    public static function deleteFile(?string $path): void
    {
        if ($path && str_starts_with($path, 'web/') && ! str_contains($path, '..')) {
            Storage::disk('public')->delete($path);
        }
    }

    /** ข้อความหลายบรรทัด > HTML ปลอดภัย: escape ทั้งหมด แล้วทำลิงก์ให้กดได้และขึ้นบรรทัดใหม่ */
    public static function text(?string $text): string
    {
        $html = e((string) $text);
        $html = preg_replace('~(https?://[^\s<]+)~u', '<a href="$1" target="_blank" rel="noopener">$1</a>', $html);

        return nl2br((string) $html);
    }

    /** รหัสวิดีโอ YouTube จากลิงก์ youtu.be / watch?v= / shorts / embed (ไม่ใช่ลิงก์ YouTube = null) */
    public static function youtubeId(?string $url): ?string
    {
        return preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/))([A-Za-z0-9_-]{11})~', (string) $url, $m) ? $m[1] : null;
    }

    /** ลิงก์ Google Maps > ลิงก์ฝังแผนที่ (iframe) ถ้าแปลงได้ */
    public static function mapEmbed(): ?string
    {
        $url = trim((string) Settings::get('website_map_url', ''));
        // ไม่ได้ใส่ลิงก์: ใช้พิกัดหรือที่อยู่โรงเรียนแทน ถ้าไม่มีทั้งคู่ไม่แสดงแผนที่
        if ($url === '' && ! Settings::get('school_address') && ! (Settings::get('school_lat') && Settings::get('school_lng'))) {
            return null;
        }
        if (preg_match('~^https://(www\.)?google\.[a-z.]+/maps/embed\?~', $url)) {
            return $url;
        }
        $address = (string) Settings::get('school_address', '');
        $q = Settings::get('school_lat') && Settings::get('school_lng')
            ? Settings::get('school_lat').','.Settings::get('school_lng')
            : (string) Settings::get('school_name').' '.$address;

        return 'https://maps.google.com/maps?q='.rawurlencode($q).'&output=embed';
    }
}
