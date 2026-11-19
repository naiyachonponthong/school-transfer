<?php

namespace App\Http\Middleware;

use App\Support\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ยังไม่ติดตั้ง > พาไป /install
 * ติดตั้งแล้ว > ปิด /install
 * โค้ดใหม่กว่าฐานข้อมูล > พาไป /update (ยกเว้นหน้าเข้าสู่ระบบ ให้ผู้ดูแลระบบล็อกอินมาอัปเดตได้)
 */
class InstallGate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests()) {
            return $next($request); // phpunit ใช้ฐานข้อมูลทดสอบของตัวเอง
        }
        $installing = $request->is('install', 'install/*');

        if (! Installer::isInstalled()) {
            // ติดตั้งใหม่: .env สร้างโดย preboot
            if (Installer::installPending()) {
                return $installing ? $next($request) : $this->away($request, route('install.index'), 'ระบบยังไม่ได้ติดตั้ง');
            }
            // ระบบเดิมที่ยังไม่มีไฟล์ล็อก: รับเข้า ถ้าฐานข้อมูลไม่พร้อมแจ้งเตือน (ไม่เปิดตัวติดตั้งให้คนอื่นตั้งค่าทับ)
            if (! Installer::tryAdopt()) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json(['message' => 'เชื่อมต่อฐานข้อมูลไม่ได้'], 503);
                }

                return response()->view('install.blocked', ['step' => 0], 503);
            }
        }

        if ($installing) {
            // หน้าสรุปหลังติดตั้งเปิดได้อีกระยะหนึ่ง ที่เหลือปิดถาวร
            return $request->is('install/done') ? $next($request) : redirect()->route('login');
        }

        if (Installer::needsUpdate() && ! $request->is('update', 'update/*', 'login', 'logout', 'app.webmanifest', 'storage/*')) {
            return $this->away($request, route('update.index'), 'ระบบรออัปเดต');
        }

        return $next($request);
    }

    protected function away(Request $request, string $url, string $message): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $message], 503);
        }

        return redirect()->to($url);
    }
}
