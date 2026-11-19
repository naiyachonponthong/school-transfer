<?php

namespace App\Http\Middleware;

use App\Http\Controllers\WebsiteController;
use App\Support\Website;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * หน้า / : คนทั่วไปที่ยังไม่เข้าสู่ระบบเห็นเว็บไซต์โรงเรียน (เมื่อเปิดใช้งาน)
 * คนที่เข้าสู่ระบบแล้วยังได้แดชบอร์ดที่ / เหมือนเดิม ลิงก์ QR และแอปบนมือถือจึงไม่เปลี่ยน
 * ต้องอยู่ในกลุ่ม web (ก่อน middleware auth ของ route) จึงตอบได้ก่อนถูกพาไปหน้าเข้าสู่ระบบ
 */
class ServeWebsiteHome
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->path() === '/' && ! $request->user() && rescue(fn () => Website::enabled(), false, false)) {
            return response(app(WebsiteController::class)->home($request));
        }

        return $next($request);
    }
}
