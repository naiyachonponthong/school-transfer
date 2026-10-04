<?php

namespace App\Http\Middleware;

use App\Support\Demo;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * ข้อจำกัดของผู้ที่เข้าระบบผ่านปุ่ม "ทดลองใช้" (session demo_role)
 * - ทุกบทบาท: ห้ามเปลี่ยนรหัสผ่าน/ข้อมูลบัญชี ตั้งค่า จัดการผู้ใช้ สำรองข้อมูล ส่งออกข้อมูล และลบข้อมูล
 * - ผู้บริหารทดลอง: ดูได้อย่างเดียว (ห้ามทุกคำขอที่ไม่ใช่การเปิดดู)
 */
class DemoRestrictions
{
    /** route ที่ห้ามทุกแบบ (รวมการเปิดดู) เพราะมีข้อมูลอ่อนไหวหรือเป็นงานของผู้ดูแลระบบ */
    private const BLOCK_ALWAYS = ['settings', 'settings.*', 'users.*', 'roles.*', 'backups.*', 'audit*', 'students.data-export', 'students.import*', 'demo.settings', 'demo.snapshot', 'demo.reset', 'line.login', 'line.callback'];

    /** route ที่ห้ามเมื่อเป็นการบันทึก/แก้ไข */
    private const BLOCK_WRITES = ['profile.*', 'password.*', 'push.*', '*.destroy', '*.undo', 'classrooms.promote*', 'terms.*', 'classrooms.*', 'subjects.*', 'privacy.purge',
        'student-accounts.*']; // สร้าง/รีเซ็ต/ปิดบัญชีนักเรียน: ปิดบัญชีทดลองของคนอื่นได้

    private const ALWAYS_ALLOWED = ['logout', 'privacy.accept', 'privacy.show'];

    public function handle(Request $request, Closure $next)
    {
        $role = Demo::role();
        if (! $role) {
            return $next($request);
        }

        $name = (string) $request->route()?->getName();
        $write = ! $request->isMethodSafe();
        $blocked = ! in_array($name, self::ALWAYS_ALLOWED, true) && (
            Str::is(self::BLOCK_ALWAYS, $name)
            || ($write && ($role === 'exec' || Str::is(self::BLOCK_WRITES, $name)))
        );

        if ($blocked) {
            $message = $role === 'exec' && $write ? 'บัญชีทดลองของผู้บริหารดูได้อย่างเดียว' : 'โหมดทดลองใช้: ส่วนนี้ปิดไว้ ไม่สามารถใช้งานได้';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 403)
                : redirect()->to($write ? url()->previous(route('home')) : route('home'))->with('warning', $message);
        }

        return $next($request);
    }
}
