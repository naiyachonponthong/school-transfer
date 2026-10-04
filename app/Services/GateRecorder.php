<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Carbon;

/**
 * บันทึกการเข้า-ออกโรงเรียนของนักเรียน ใช้ร่วมกันทุกช่องทางที่ระบุตัวนักเรียนได้
 * (สแกน QR ที่หน้าจุดสแกน · เครื่องสแกนใบหน้า/บัตรที่ส่งเหตุการณ์เข้ามา)
 */
class GateRecorder
{
    /** เข้า หรือ ออก ตามเวลา เมื่อช่องทางนั้นไม่ได้กำหนดไว้ตายตัว */
    public static function modeAt(Carbon $at): string
    {
        return $at->format('H:i') >= Settings::get('gate_checkout_after', '14:00') ? 'out' : 'in';
    }

    /**
     * @param  string|null  $mode  in | out | null (ตามเวลา)
     * @return array{kind: string, message: string, attendance: Attendance}  kind: present | late | out | repeat
     */
    public static function record(Student $student, ?string $mode = null, ?int $recordedBy = null, ?Carbon $at = null): array
    {
        $at ??= now();
        $mode = in_array($mode, ['in', 'out'], true) ? $mode : self::modeAt($at);
        $time = $at->format('H:i:s');
        $att = Attendance::firstOrNew(['student_id' => $student->id, 'date' => $at->toDateString()]);
        $nick = 'น้อง'.($student->nickname ?: $student->first_name);
        $done = fn (string $kind, string $message) => ['kind' => $kind, 'message' => $message, 'attendance' => $att];

        if ($mode === 'in') {
            if ($att->exists && $att->checked_at && in_array($att->status, ['present', 'late'], true)) {
                return $done('repeat', 'สแกนเข้าไปแล้วเวลา '.substr($att->checked_at, 0, 5).' น.');
            }
            $late = $at->format('H:i') > Settings::get('late_time', '08:00');
            $att->fill([
                'classroom_id' => $student->classroom_id,
                'status' => $late ? 'late' : 'present',
                'checked_at' => $time,
                'source' => 'gate',
                'recorded_by' => $recordedBy,
            ])->save();
            if (Settings::get('line_notify_gate')) {
                Notifier::parents($student, "✅ {$nick} ถึงโรงเรียนแล้ว เวลา ".$at->format('H:i').' น.'.($late ? ' (มาสาย)' : ''));
            }

            return $done($late ? 'late' : 'present', $late ? 'มาสาย' : 'ยินดีต้อนรับ');
        }

        // ขาออก
        if (! $att->exists) {
            // ไม่ได้สแกนเข้า (เช่น ครูเช็คชื่อด้วยมือ) แต่มาเรียน
            $att->fill(['classroom_id' => $student->classroom_id, 'status' => 'present', 'source' => 'gate', 'recorded_by' => $recordedBy]);
        }
        if ($att->checkout_at) {
            return $done('repeat', 'สแกนออกไปแล้วเวลา '.substr($att->checkout_at, 0, 5).' น.');
        }
        $att->checkout_at = $time;
        $att->save();
        if (Settings::get('line_notify_gate')) {
            Notifier::parents($student, "🏠 {$nick} ออกจากโรงเรียนแล้ว เวลา ".$at->format('H:i').' น.');
        }

        return $done('out', 'เดินทางกลับบ้านปลอดภัย');
    }

    /** สแกนซ้ำภายในกี่นาทีไม่นับ (เครื่องอ่านหน้าเดิมสองรอบ) */
    public const STAFF_REPEAT_MINUTES = 5;

    /**
     * ลงเวลาทำงานของครู/บุคลากรจากการสแกนที่ประตู ลงตารางเดียวกับที่ครูกดลงเวลาเองจากมือถือ
     * ครั้งแรกของวัน = เข้า · ครั้งถัดไป = ออก (ครั้งล่าสุดเป็นเวลาออก) เว้นแต่ช่องทางนั้นกำหนดทิศทางไว้ตายตัว
     *
     * @param  string|null  $mode  in | out | null (ตามลำดับการสแกน)
     * @return array{kind: string, attendance: StaffAttendance}  kind: present | late | out | repeat
     */
    public static function recordStaff(User $user, ?string $mode = null, ?Carbon $at = null): array
    {
        $at ??= now();
        $time = $at->format('H:i:s');
        $rec = StaffAttendance::firstOrNew(['user_id' => $user->id, 'date' => $at->toDateString()]);
        $done = fn (string $kind) => ['kind' => $kind, 'attendance' => $rec];
        $recent = fn (?string $prev) => $prev !== null
            && abs($at->diffInSeconds($at->copy()->setTimeFromTimeString($prev))) < self::STAFF_REPEAT_MINUTES * 60;
        $mode = in_array($mode, ['in', 'out'], true) ? $mode : ($rec->check_in ? 'out' : 'in');

        if ($mode === 'in') {
            if ($rec->check_in) {
                return $done('repeat');
            }
            $late = $at->format('H:i') > Settings::get('staff_late_time', '08:00');
            $rec->check_in = $time;
            $rec->source = 'gate';
            // วันที่บันทึกลา/ไปราชการไว้แล้ว เก็บเวลาที่สแกนแต่ไม่เปลี่ยนสถานะ
            if (! in_array($rec->status, ['leave', 'duty'], true)) {
                $rec->status = $late ? 'late' : 'present';
            }
            $rec->save();

            return $done($late ? 'late' : 'present');
        }

        // ขาออก: ครั้งล่าสุดเป็นเวลาออก
        if ($recent($rec->check_out) || ($rec->check_out === null && $recent($rec->check_in))) {
            return $done('repeat');
        }
        if (! $rec->exists) {
            $rec->status = 'present';
            $rec->source = 'gate';
        }
        $rec->check_in ??= $time; // เหมือนการลงเวลากลับจากมือถือเมื่อไม่ได้ลงเวลาเข้า
        $rec->check_out = $time;
        $rec->save();

        return $done('out');
    }
}
