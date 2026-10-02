<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** บัญชีที่ใช้รหัสผ่านที่คนอื่นตั้งให้ ต้องเปลี่ยนรหัสก่อนเข้าหน้าอื่น */
class EnsurePasswordChanged
{
    private const ALLOWED = ['password.change', 'password.change.save', 'logout'];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user?->must_change_password && ! in_array($request->route()?->getName(), self::ALLOWED, true)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'กรุณาเปลี่ยนรหัสผ่านก่อนใช้งาน'], 423)
                : redirect()->route('password.change');
        }

        return $next($request);
    }
}
