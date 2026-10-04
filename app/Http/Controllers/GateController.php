<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Student;
use App\Services\GateRecorder;
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
            ->scannedBy($code)
            ->first();

        if (! $student) {
            return response()->json(['ok' => false, 'message' => 'ไม่พบข้อมูลนักเรียน'], 404);
        }

        $mode = in_array($request->input('mode'), ['in', 'out'], true) ? $request->input('mode') : null;
        $result = GateRecorder::record($student, $mode, $request->user()->id);

        return $this->result($student, $result['attendance'], $result['kind'], $result['message']);
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
