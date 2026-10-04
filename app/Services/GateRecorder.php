<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Student;
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
}
