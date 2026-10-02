<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * บัญชีนักเรียน: สร้างทั้งห้องครั้งเดียว ชื่อผู้ใช้ = รหัสนักเรียน รหัสผ่านสุ่ม
 * รหัสผ่านแสดงครั้งเดียวตอนสร้าง/รีเซ็ต (ระบบเก็บแบบเข้ารหัส อ่านย้อนไม่ได้) พิมพ์เป็นใบแจกนักเรียน
 */
class StudentAccountController extends Controller
{
    /** สร้างบัญชีให้นักเรียนที่ยังไม่มีบัญชีในห้องนี้ */
    public function createForClassroom(Request $request)
    {
        $classroom = Classroom::findOrFail($request->validate(['classroom_id' => ['required', 'exists:classrooms,id']])['classroom_id']);
        abort_unless($classroom->isManagedBy($request->user()), 403, 'สร้างบัญชีได้เฉพาะห้องที่คุณเป็นครูประจำชั้น');

        $credentials = DB::transaction(function () use ($classroom) {
            $out = [];
            foreach ($classroom->students()->whereNull('user_id')->get() as $s) {
                $out[] = $this->issue($s);
            }

            return $out;
        });

        if (! $credentials) {
            return back()->with('warning', "นักเรียนห้อง {$classroom->name()} มีบัญชีครบทุกคนแล้ว (ถ้าลืมรหัสผ่าน ให้รีเซ็ตรายคนที่หน้าข้อมูลนักเรียน)");
        }

        return redirect()->route('student-accounts.slips')
            ->with('student_credentials', $credentials)
            ->with('success', 'สร้างบัญชีนักเรียนห้อง '.$classroom->name().' แล้ว '.count($credentials).' คน — พิมพ์ใบแจกตอนนี้เลย รหัสผ่านจะไม่แสดงอีก');
    }

    /** สร้างบัญชี (ถ้ายังไม่มี) หรือรีเซ็ตรหัสผ่านให้นักเรียนรายคน */
    public function reset(Request $request, Student $student)
    {
        abort_unless($request->user()->isAdmin() || $student->classroom?->isManagedBy($request->user()), 403);
        $cred = DB::transaction(fn () => $this->issue($student));

        return redirect()->route('student-accounts.slips')->with('student_credentials', [$cred])
            ->with('success', 'ออกรหัสผ่านใหม่ให้ '.$student->fullName().' แล้ว');
    }

    public function disable(Request $request, Student $student)
    {
        abort_unless($request->user()->isAdmin() || $student->classroom?->isManagedBy($request->user()), 403);
        $student->user?->update(['is_active' => ! $student->user->is_active]);

        return back()->with('success', $student->user?->is_active ? 'เปิดใช้บัญชีนักเรียนแล้ว' : 'ปิดการใช้งานบัญชีนักเรียนแล้ว');
    }

    /** ใบแจกรหัสผ่าน (แสดงจาก session ครั้งเดียว) */
    public function slips(Request $request)
    {
        $creds = session('student_credentials');
        if (! $creds) {
            return redirect()->route('students.index')->with('warning', 'ไม่มีรหัสผ่านที่จะพิมพ์ (แสดงได้ครั้งเดียวหลังสร้าง/รีเซ็ต)');
        }

        return view('students.account-slips', ['creds' => $creds]);
    }

    /** @return array{name:string, classroom:?string, number:?int, username:string, password:string} */
    private function issue(Student $student): array
    {
        $password = self::password();
        $user = $student->user;
        if ($user) {
            $user->update(['password' => Hash::make($password), 'is_active' => true, 'must_change_password' => true]);
        } else {
            $username = $student->student_code;
            if (User::where('username', $username)->exists()) {
                $username = 's'.$student->student_code;
            }
            $user = User::create([
                'name' => $student->fullName(),
                'username' => $username,
                'role' => 'student',
                'is_active' => true,
                'password' => Hash::make($password),
                'must_change_password' => true,
            ]);
            $student->update(['user_id' => $user->id]);
        }

        return [
            'name' => $student->fullName(),
            'classroom' => $student->classroom?->name(),
            'number' => $student->number,
            'username' => $user->username,
            'password' => $password,
        ];
    }

    /** รหัสผ่าน 8 ตัว ตัดตัวที่สับสนง่าย (0/O, 1/l/I) ออก ให้เด็กพิมพ์ตามใบได้ไม่ผิด */
    public static function password(int $length = 8): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $out;
    }
}
