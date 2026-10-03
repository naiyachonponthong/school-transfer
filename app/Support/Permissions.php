<?php

namespace App\Support;

/**
 * สิทธิ์ทั้งหมดของบุคลากร นิยามที่เดียว ใช้ทั้ง middleware `permission:`, เมนู และหน้าตั้งค่าตำแหน่งงาน
 * ผู้ดูแลระบบ (role = admin) ได้ทุกสิทธิ์เสมอ ผู้ปกครอง/นักเรียนไม่มีสิทธิ์ชุดนี้
 */
class Permissions
{
    /** กลุ่ม => [key => คำอธิบาย] */
    public const GROUPS = [
        'นักเรียน' => [
            'students.edit' => 'เพิ่ม/แก้ไขข้อมูลนักเรียนและผู้ปกครอง นำเข้าจาก Excel',
            'gate.use' => 'สแกนหน้าประตู พิมพ์บัตรนักเรียน',
            'health.manage' => 'ห้องพยาบาล บันทึกน้ำหนัก-ส่วนสูง',
            'library.manage' => 'ห้องสมุด ยืม-คืนหนังสือ ลงทะเบียนหนังสือ',
            'reports.view' => 'รายงาน / ส่งออก DMC',
            'care.manage' => 'ดูแลช่วยเหลือและเยี่ยมบ้านนักเรียนทุกห้อง (ครูประจำชั้นดูแลห้องตัวเองได้อยู่แล้ว)',
        ],
        'การเงิน' => [
            'finance.view' => 'ดูใบแจ้งหนี้และยอดค้างชำระทั้งโรงเรียน',
            'finance.manage' => 'ออกใบแจ้งหนี้ รับชำระ ตรวจสลิป ยกเลิกใบแจ้งหนี้',
        ],
        'วิชาการและทะเบียน' => [
            'academics.manage' => 'ปีการศึกษา ห้องเรียน เลื่อนชั้น รายวิชา ตารางเรียน ปฏิทิน แบบประเมิน ออก ปพ.7 / ปพ.3',
            'admissions.manage' => 'รับสมัครนักเรียน สอบคัดเลือก มอบตัว',
        ],
        'บุคลากรและบริหารทั่วไป' => [
            'staff.manage' => 'รายงานลงเวลาครู อนุมัติใบลาบุคลากร ทะเบียนประวัติบุคลากร',
            'office.manage' => 'สารบรรณ: ลงทะเบียนหนังสือ เวียนหนังสือ ดูการรับทราบ',
            'facilities.manage' => 'งานพัสดุ/อาคารสถานที่: ครุภัณฑ์ ตรวจสอบพัสดุ คลังวัสดุ รับเรื่องแจ้งซ่อม อนุมัติการจอง',
        ],
        'ระบบ' => [
            'users.manage' => 'ผู้ใช้งาน ตำแหน่งงานและสิทธิ์',
            'settings.manage' => 'ตั้งค่าโรงเรียน LINE แจ้งเตือน สำรองข้อมูล',
            'audit.view' => 'ประวัติการแก้ไข (ใครแก้อะไร เมื่อไร)',
        ],
    ];

    /** ตำแหน่งตั้งต้นที่ระบบสร้างให้: key => [ชื่อ, สิทธิ์] */
    public const DEFAULT_ROLES = [
        // บุคลากรที่ยังไม่ได้กำหนดตำแหน่งใช้สิทธิ์ของ "ครู"
        'teacher' => ['ครู', ['students.edit', 'gate.use', 'health.manage', 'library.manage', 'reports.view']],
        'executive' => ['ผู้บริหาร', ['finance.view', 'reports.view', 'staff.manage', 'audit.view', 'care.manage']],
        'academic' => ['วิชาการ/ทะเบียน', ['students.edit', 'reports.view', 'academics.manage', 'admissions.manage']],
        'finance' => ['การเงิน', ['finance.view', 'finance.manage']],
        'nurse' => ['พยาบาล', ['health.manage']],
        'librarian' => ['บรรณารักษ์', ['library.manage']],
        'clerk' => ['ธุรการ/พัสดุ', ['students.edit', 'gate.use', 'reports.view', 'admissions.manage', 'facilities.manage', 'office.manage']],
    ];

    public const FALLBACK_ROLE = 'teacher';

    /** @return list<string> */
    public static function keys(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::GROUPS)));
    }
}
