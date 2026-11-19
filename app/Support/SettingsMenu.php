<?php

namespace App\Support;

use App\Models\User;

/**
 * โครงหน้าตั้งค่าแบบหมวด: หมวด (การ์ด) > หัวข้อ (pills) แต่ละหัวข้อเป็นไฟล์ settings/sections/{key}.blade.php
 * หัวข้อที่มีหน้าของตัวเองอยู่แล้ว (link) แสดงเป็นการ์ดพาไปหน้านั้น
 */
class SettingsMenu
{
    /** key => [ชื่อ, ไอคอน bi, คำค้นเพิ่ม, route ของหน้าที่มีอยู่แล้ว|null, สิทธิ์ที่ต้องมี|null] */
    public const SECTIONS = [
        'school' => ['ข้อมูลโรงเรียน', 'building', 'ชื่อ ที่อยู่ โลโก้ ผู้อำนวยการ ผู้ลงนาม', null, null],
        'users' => ['ผู้ใช้งาน', 'person-gear', 'บัญชี ครู รหัสผ่าน', 'users.index', 'users.manage'],
        'roles' => ['ตำแหน่งและสิทธิ์', 'shield-lock', 'บทบาท permission', 'roles.index', 'users.manage'],
        'org' => ['โครงสร้างองค์กร', 'diagram-3', 'ฝ่าย กลุ่มงาน', 'org.index', null],
        'schedule' => ['เวลาและตารางเรียน', 'clock', 'มาสาย คาบ เกรด เกณฑ์ตัดเกรด วันเรียน', null, null],
        'gate' => ['สแกนหน้าประตูและ GPS', 'qr-code-scan', 'ลงเวลา ตำแหน่ง รัศมี กลับบ้าน', null, null],
        'behavior' => ['คะแนนความประพฤติ', 'award', 'พฤติกรรม หักคะแนน', null, null],
        'library' => ['ห้องสมุดและรับสมัคร', 'book', 'ยืมหนังสือ รับสมัครนักเรียน', null, null],
        'facility' => ['พัสดุและอาคาร', 'tools', 'ครุภัณฑ์ แจ้งซ่อม เลขครุภัณฑ์ วัสดุ', null, null],
        'website' => ['เว็บไซต์โรงเรียน', 'globe2', 'หน้าเว็บ หน้าแรก สารผู้อำนวยการ Facebook แผนที่ เปิดปิดเว็บไซต์', null, null],
        'website-content' => ['ข่าวและเนื้อหาเว็บไซต์', 'newspaper', 'ข่าว สไลด์ หน้าเนื้อหา ทำเนียบ ลิงก์', 'website.admin', 'website.manage'],
        'payment' => ['ช่องทางรับชำระเงิน', 'bank', 'พร้อมเพย์ บัญชีธนาคาร ใบแจ้งหนี้', null, null],
        'line' => ['LINE แจ้งเตือน', 'chat-dots', 'LINE OA token webhook', null, null],
        'line-login' => ['เข้าสู่ระบบด้วย LINE', 'box-arrow-in-right', 'LINE Login channel', null, null],
        'messages' => ['ประวัติข้อความ LINE', 'chat-left-text', 'ส่งข้อความ log', 'settings.messages', null],
        'privacy' => ['ความเป็นส่วนตัว (PDPA)', 'shield-check', 'ประกาศ ยินยอม', null, null],
        'appearance' => ['หน้าตา', 'palette', 'สีธีม เครดิต ท้ายหน้า', null, null],
        'images' => ['รูปภาพระบบ', 'images', 'ภาพประกอบ hero', null, null],
        'demo' => ['โหมดทดลองใช้', 'play-circle', 'สาธิต demo ต้นแบบ', null, null],
        'backups' => ['สำรองข้อมูล', 'archive', 'backup กู้คืน', 'backups.index', null],
        'audit' => ['ประวัติการแก้ไข', 'clock-history', 'audit log', 'audit.index', 'audit.view'],
        'license' => ['License', 'patch-check', 'คีย์ ทดลองใช้ หมดอายุ', null, null],
        'system' => ['ข้อมูลระบบ', 'cpu', 'เวอร์ชัน งานตั้งเวลา cron PHP ฐานข้อมูล', null, null],
    ];

    /** [ชื่อหมวด, คำอธิบาย, ไอคอน, [หัวข้อ]] */
    public const GROUPS = [
        ['โรงเรียนและบุคลากร', 'ข้อมูลโรงเรียน ผู้ใช้ และสิทธิ์', 'building', ['school', 'users', 'roles', 'org']],
        ['การเรียนการสอน', 'เวลาเรียน เกรด ความประพฤติ และงานสนับสนุน', 'mortarboard', ['schedule', 'gate', 'behavior', 'library', 'facility']],
        ['การเงินและการสื่อสาร', 'เว็บไซต์ รับชำระเงิน LINE และความเป็นส่วนตัว', 'chat-dots', ['website', 'website-content', 'payment', 'line', 'line-login', 'messages', 'privacy']],
        ['หน้าตาและระบบ', 'สี รูปภาพ License และเครื่องมือระบบ', 'sliders2', ['appearance', 'images', 'demo', 'backups', 'audit', 'license', 'system']],
    ];

    /** หัวข้อที่ผู้ใช้คนนี้เปิดได้ */
    public static function sections(?User $user): array
    {
        return array_filter(self::SECTIONS, fn ($s) => $s[4] === null || ($user?->hasPermission($s[4]) ?? false));
    }

    /** หมวดที่มีหัวข้อให้เปิดอย่างน้อย 1 หัวข้อ */
    public static function groups(?User $user): array
    {
        $sections = self::sections($user);

        return array_values(array_filter(array_map(function ($g) use ($sections) {
            $g[3] = array_values(array_filter($g[3], fn ($k) => isset($sections[$k])));

            return $g;
        }, self::GROUPS), fn ($g) => $g[3] !== []));
    }

    public static function url(string $key): string
    {
        if ($key === 'license') {
            return route('settings.license').'#workspace'; // path แยก ด่าน License ยกเว้นให้เปิดได้ตอนระบบถูกล็อก
        }

        return route('settings', ['s' => $key]).'#workspace'; // เลื่อนลงไปที่หัวข้อเลย ไม่ต้องเลื่อนผ่านแบนเนอร์ทุกครั้ง
    }
}
