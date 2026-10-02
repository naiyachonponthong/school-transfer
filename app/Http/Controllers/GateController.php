<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use App\Services\Notifier;
use App\Support\Settings;
use Illuminate\Http\Request;

/**
 * จุดสแกนหน้าประตู: สแกน QR บนบัตรนักเรียน (กล้องมือถือ/แท็บเล็ต หรือเครื่องอ่านบาร์โค้ด USB)
 * ก่อนเวลา "ออก" = เข้าโรงเรียน (มา/สาย ตามเวลาที่ตั้ง), หลังจากนั้น = กลับบ้าน
 */
class GateController extends Controller
{
    public function index()
    {
        $today = today()->toDateString();
        $recent = Attendance::with('student.classroom')->where('date', $today)->where('source', 'gate')
            ->orderByDesc('updated_at')->limit(12)->get();

        return view('gate.index', [
            'recent' => $recent,
            'inCount' => Attendance::where('date', $today)->whereNotNull('checked_at')->where('source', 'gate')->count(),
            'outCount' => Attendance::where('date', $today)->whereNotNull('checkout_at')->count(),
            'lateTime' => Settings::get('late_time', '08:00'),
            'checkoutAfter' => Settings::get('gate_checkout_after', '14:00'),
            'total' => Student::active()->count(),
        ]);
    }

    public function scan(Request $request)
    {
        $code = trim((string) $request->input('code'));
        $student = Student::with('classroom')->active()
            ->where(fn ($q) => $q->where('qr_token', $code)->orWhere('student_code', $code))
            ->first();

        if (! $student) {
            return response()->json(['ok' => false, 'message' => 'ไม่พบข้อมูลนักเรียน'], 404);
        }

        $today = today()->toDateString();
        $time = now()->format('H:i:s');
        $mode = $request->input('mode') ?: (now()->format('H:i') >= Settings::get('gate_checkout_after', '14:00') ? 'out' : 'in');
        $att = Attendance::firstOrNew(['student_id' => $student->id, 'date' => $today]);
        $nick = 'น้อง'.($student->nickname ?: $student->first_name);

        if ($mode === 'in') {
            if ($att->exists && $att->checked_at && in_array($att->status, ['present', 'late'], true)) {
                return $this->result($student, $att, 'repeat', 'สแกนเข้าไปแล้วเวลา '.substr($att->checked_at, 0, 5).' น.');
            }
            $late = now()->format('H:i') > Settings::get('late_time', '08:00');
            $att->fill([
                'classroom_id' => $student->classroom_id,
                'status' => $late ? 'late' : 'present',
                'checked_at' => $time,
                'source' => 'gate',
                'recorded_by' => $request->user()->id,
            ])->save();
            if (Settings::get('line_notify_gate')) {
                Notifier::parents($student, "✅ {$nick} ถึงโรงเรียนแล้ว เวลา ".now()->format('H:i').' น.'.($late ? ' (มาสาย)' : ''));
            }

            return $this->result($student, $att, $late ? 'late' : 'present', $late ? 'มาสาย' : 'ยินดีต้อนรับ');
        }

        // ขาออก
        if (! $att->exists) {
            // ไม่ได้สแกนเข้า (เช่น ครูเช็คชื่อด้วยมือ) แต่มาเรียน
            $att->fill(['classroom_id' => $student->classroom_id, 'status' => 'present', 'source' => 'gate', 'recorded_by' => $request->user()->id]);
        }
        if ($att->checkout_at) {
            return $this->result($student, $att, 'repeat', 'สแกนออกไปแล้วเวลา '.substr($att->checkout_at, 0, 5).' น.');
        }
        $att->checkout_at = $time;
        $att->save();
        if (Settings::get('line_notify_gate')) {
            Notifier::parents($student, "🏠 {$nick} ออกจากโรงเรียนแล้ว เวลา ".now()->format('H:i').' น.');
        }

        return $this->result($student, $att, 'out', 'เดินทางกลับบ้านปลอดภัย');
    }

    private function result(Student $s, Attendance $att, string $kind, string $message)
    {
        return response()->json([
            'ok' => true,
            'kind' => $kind,
            'message' => $message,
            'time' => now()->format('H:i'),
            'student' => [
                'name' => $s->fullName(),
                'nickname' => $s->nickname,
                'classroom' => $s->classroom?->name(),
                'number' => $s->number,
                'photo' => $s->photoUrl(),
                'initials' => $s->initials(),
            ],
        ]);
    }

    /** พิมพ์บัตรนักเรียนพร้อม QR ทั้งห้อง */
    public function cards(Request $request)
    {
        $classrooms = Classroom::currentYear()->ordered()->get();
        $classroom = $classrooms->firstWhere('id', (int) $request->query('classroom')) ?? $classrooms->first();
        $students = $classroom ? $classroom->students()->get() : collect();
        $students->each->qrPayload();

        return view('gate.cards', compact('classrooms', 'classroom', 'students'));
    }
}
