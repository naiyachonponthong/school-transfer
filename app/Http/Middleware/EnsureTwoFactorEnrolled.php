<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** เมื่อโรงเรียนเปิดบังคับ: ผู้ดูแลระบบและผู้ที่ดูแลเงิน/ผู้ใช้/ตั้งค่า ต้องเปิดยืนยันตัวตน 2 ขั้นก่อนเข้าหน้าอื่น */
class EnsureTwoFactorEnrolled
{
    private const ALLOWED = ['two-factor.*', 'logout', 'password.change', 'password.change.save', 'privacy.show', 'privacy.accept'];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && ! session('demo_role') && ! $request->routeIs(...self::ALLOWED) && $user->mustUseTwoFactor() && ! $user->hasTwoFactor()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'กรุณาเปิดการยืนยันตัวตน 2 ขั้นก่อนใช้งาน'], 423)
                : redirect()->route('two-factor.setup')->with('warning', 'โรงเรียนกำหนดให้บัญชีที่ดูแลเงิน ผู้ใช้ หรือการตั้งค่า ต้องเปิดการยืนยันตัวตน 2 ขั้นก่อนใช้งาน');
        }

        return $next($request);
    }
}
