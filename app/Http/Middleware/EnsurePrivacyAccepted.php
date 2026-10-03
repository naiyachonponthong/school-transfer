<?php

namespace App\Http\Middleware;

use App\Http\Controllers\PrivacyController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** โรงเรียนตั้งประกาศความเป็นส่วนตัวไว้: ผู้ใช้ต้องรับทราบฉบับปัจจุบันก่อนใช้งาน (ตรวจครั้งเดียวต่อ session) */
class EnsurePrivacyAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $request->routeIs('privacy.*', 'logout', 'password.change', 'password.change.save')) {
            return $next($request);
        }

        $key = 'privacy_ok_v'.PrivacyController::currentVersion();
        if (! $request->session()->get($key)) {
            if (PrivacyController::required($user)) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'กรุณารับทราบประกาศความเป็นส่วนตัวก่อนใช้งาน'], 423)
                    : redirect()->guest(route('privacy.show'));
            }
            $request->session()->put($key, true);
        }

        return $next($request);
    }
}
