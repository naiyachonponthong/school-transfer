<?php

namespace App\Http\Middleware;

use App\Support\Installer;
use App\Support\License\Guard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ด่าน License (ต่อท้ายกลุ่ม web เพื่อให้รู้ว่าใครล็อกอินอยู่)
 * - ใช้ได้ > ผ่าน และแขวนแถบเตือนให้หน้าเว็บเมื่อใกล้หมดอายุหรือทดลองใช้
 * - ถูกล็อก > ผู้ดูแลระบบไปหน้าตั้งค่า License คนอื่นเห็นหน้าแจ้งเตือน 503
 */
class LicenseGate
{
    /** หน้าที่ยังเปิดได้ตอนระบบถูกล็อก (ต้องมีหน้ากรอกคีย์ ไม่งั้นแก้ไม่ได้) */
    protected const ALLOWED = [
        'login', 'logout', 'up', 'install', 'install/*', 'update', 'update/*',
        'settings/license', 'settings/license/*', 'storage/*', 'app.webmanifest',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ((Guard::testing() && ! config('license.test_enforce')) || ! Installer::isInstalled() || $request->is(...self::ALLOWED)) {
            return $next($request);
        }

        $status = Guard::status();

        if ($status['state'] !== 'locked') {
            view()->share('licenseNotice', Guard::notice());

            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*', 'line/webhook', 'gate/hook/*', 'wallet/hook')) {
            return response()->json(['message' => 'ระบบถูกล็อก: '.$status['message']], 503);
        }

        $user = $request->user();
        if ($user?->isAdmin()) {
            return redirect()->to(route('settings.license').'#workspace')->with('license_locked', $status['message']);
        }

        return response()->view('license.locked', ['status' => $status], 503);
    }
}
