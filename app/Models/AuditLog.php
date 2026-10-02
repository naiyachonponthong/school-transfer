<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ประวัติการกระทำสำคัญ — บันทึกผ่าน App\Support\Audit::log() เท่านั้น ไม่มีการแก้/ลบจากหน้าเว็บ */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /** หมวดของ action (ส่วนหน้าจุด) สำหรับกรอง */
    public const GROUPS = [
        'grade' => 'คะแนนและผลการเรียน',
        'course' => 'รายวิชา',
        'student' => 'นักเรียน',
        'document' => 'เอกสาร ปพ.',
        'finance' => 'การเงิน',
        'user' => 'บัญชีผู้ใช้',
        'setting' => 'ตั้งค่า / ปีการศึกษา',
        'admission' => 'รับสมัคร / สอบคัดเลือก',
    ];

    /** ชื่อช่องภาษาไทยสำหรับหน้าประวัติ (ช่องที่ไม่อยู่ในรายการแสดงชื่อเดิม) */
    public const FIELD_LABELS = [
        'student_code' => 'รหัสนักเรียน', 'citizen_id' => 'เลขประจำตัวประชาชน', 'prefix' => 'คำนำหน้า', 'first_name' => 'ชื่อ', 'last_name' => 'นามสกุล',
        'nickname' => 'ชื่อเล่น', 'gender' => 'เพศ', 'birthdate' => 'วันเกิด', 'classroom_id' => 'ห้องเรียน', 'number' => 'เลขที่', 'status' => 'สถานะ',
        'photo' => 'รูปถ่าย', 'blood_type' => 'กรุ๊ปเลือด', 'medical_note' => 'โรคประจำตัว/แพ้ยา', 'address' => 'ที่อยู่', 'phone' => 'เบอร์โทร',
        'nationality' => 'สัญชาติ', 'ethnicity' => 'เชื้อชาติ', 'religion' => 'ศาสนา', 'father_name' => 'ชื่อบิดา', 'mother_name' => 'ชื่อมารดา',
        'admitted_on' => 'วันเข้าเรียน', 'previous_school' => 'โรงเรียนเดิม', 'previous_school_province' => 'จังหวัดโรงเรียนเดิม', 'previous_level' => 'ชั้นเดิม',
        'left_on' => 'วันจบ/ออก', 'leave_reason' => 'สาเหตุที่ออก', 'teacher_id' => 'ครูผู้สอน', 'locked' => 'ล็อกคะแนน',
        'name' => 'ชื่อ', 'username' => 'ชื่อผู้ใช้', 'email' => 'อีเมล', 'role' => 'บทบาท', 'is_active' => 'เปิดใช้งาน', 'password' => 'รหัสผ่าน', 'position' => 'ตำแหน่ง',
        'special' => 'ผลพิเศษ', 'remedial_grade' => 'ผลแก้ตัว', 'before' => 'เดิม', 'after' => 'ใหม่', 'course_id' => 'รายวิชา',
        'school_name' => 'ชื่อโรงเรียน', 'director_name' => 'ผู้อำนวยการ', 'line_channel_token' => 'LINE token', 'line_channel_secret' => 'LINE secret',
        'code' => 'เลข/รหัส', 'asset_no_pattern' => 'รูปแบบเลขครุภัณฑ์', 'asset_category_codes' => 'รหัสประเภทครุภัณฑ์',
        'supply_no_pattern' => 'รูปแบบรหัสวัสดุ', 'supply_category_codes' => 'รหัสหมวดวัสดุ',
    ];

    public static function fieldLabel(string $field): string
    {
        return self::FIELD_LABELS[$field] ?? $field;
    }

    protected $fillable = ['user_id', 'user_name', 'action', 'subject_type', 'subject_id', 'description', 'changes', 'ip'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function groupLabel(): string
    {
        return self::GROUPS[strtok($this->action, '.')] ?? $this->action;
    }
}
