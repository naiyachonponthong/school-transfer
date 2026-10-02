<?php

namespace App\Support;

use App\Models\Admission;
use App\Models\Student;

/**
 * ผู้เข้าสอบ 1 คนของชุดข้อสอบ — นักเรียนในห้อง (สอบในรายวิชา) หรือผู้สมัคร (สอบคัดเลือก)
 * ให้ส่วนตรวจกระดาษคำตอบทำงานเหมือนกันทั้งสองแบบ
 */
final class ExamTaker
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,      // เลขที่ระบายในช่อง "เลขประจำตัว" (5 หลัก)
        public readonly string $seat,      // เลขที่ / เลขที่นั่งสอบ
        public readonly string $name,
        public readonly ?string $room,     // ชื่อห้องเรียน / ห้องสอบ (แสดงผล)
        public readonly string $roomKey,   // ใช้จัดกลุ่มตามห้อง
        public readonly ?string $sub = null, // ข้อมูลเสริม เช่น โรงเรียนเดิม
    ) {}

    public static function fromStudent(Student $s): self
    {
        return new self($s->id, (string) $s->student_code, (string) ($s->number ?? ''), $s->fullName(), $s->classroom?->name(), (string) $s->classroom_id);
    }

    public static function fromApplication(Admission $a): self
    {
        return new self($a->id, (string) $a->exam_no, (string) ($a->exam_seat ?? ''), $a->fullName(),
            $a->exam_room ? 'ห้องสอบ '.$a->exam_room : null, (string) $a->exam_room, $a->previous_school);
    }

    /** ข้อความในตัวเลือกเจ้าของแผ่น */
    public function label(): string
    {
        return trim(($this->room ? $this->room.' ' : '').'เลขที่ '.$this->seat.' · '.$this->code.' '.$this->name);
    }

    /** รหัสผู้สอบแบบ EVANA = เลขที่ + ห้อง เช่น 7ม11 */
    public function evanaId(): string
    {
        return $this->seat.str_replace(['.', '/', ' ', 'ห้องสอบ'], '', (string) $this->room);
    }
}
