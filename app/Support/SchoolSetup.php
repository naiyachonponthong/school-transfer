<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * ตั้งค่าเริ่มต้นของโรงเรียนหลังสร้างตาราง: ชื่อโรงเรียน + บัญชีผู้ดูแลคนแรก
 * ใช้ร่วมกันระหว่างคำสั่ง `school:install` และตัวติดตั้งผ่านเว็บ (บทบาท/สิทธิ์ตั้งต้นมาจาก migration อยู่แล้ว)
 */
class SchoolSetup
{
    /** ตั้งชื่อโรงเรียน (ชื่อย่อเว้นว่าง = ใช้ชื่อเต็ม) */
    public static function school(string $name, ?string $short = null): void
    {
        Settings::set(['school_name' => $name, 'school_short' => $short ?: $name]);
    }

    /** เปิดเว็บไซต์โรงเรียนและสร้างหน้าเนื้อหาตั้งต้นที่ยังไม่มี (ติดตั้งใหม่เท่านั้น ระบบที่อัปเดตมาให้ผู้ดูแลเปิดเอง) */
    public static function website(): void
    {
        Settings::set(['website_enabled' => '1']);
        $sort = 0;
        foreach (\App\Models\WebPage::STARTER as $slug => $title) {
            \App\Models\WebPage::firstOrCreate(['slug' => $slug], ['title' => $title, 'body' => '', 'sort' => ++$sort, 'is_published' => false]);
        }
    }

    /**
     * สร้างหรือแก้บัญชีผู้ดูแลคนแรก: ใช้ผู้ดูแลคนแรกที่มีอยู่ (เช่นจากข้อมูลตัวอย่าง) ถ้าไม่มีสร้างใหม่
     * ถ้าชื่อผู้ใช้ที่เลือกซ้ำกับบัญชีอื่น ย้ายชื่อผู้ใช้ของบัญชีนั้นออกก่อน
     *
     * @param  string  $passwordHash  รหัสผ่านที่ hash แล้ว (ตัวติดตั้งไม่เก็บรหัสตัวจริงใน session)
     */
    public static function admin(string $name, string $username, string $passwordHash): User
    {
        return DB::transaction(function () use ($name, $username, $passwordHash) {
            $admin = User::query()->where('role', 'admin')->orderBy('id')->first() ?? new User(['role' => 'admin']);

            User::query()->where('username', $username)->when($admin->exists, fn ($q) => $q->whereKeyNot($admin->getKey()))
                ->get()->each(fn (User $dup) => $dup->forceFill(['username' => 'old-'.$dup->id.'-'.$dup->username])->save());

            $admin->forceFill([
                'name' => $name,
                'username' => $username,
                'role' => 'admin',
                'position' => $admin->position ?: 'ผู้ดูแลระบบ',
                'is_active' => true,
                'password' => $passwordHash,
                'must_change_password' => false,
            ])->save();

            return $admin;
        });
    }
}
