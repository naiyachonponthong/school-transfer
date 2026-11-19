<?php

namespace App\Http\Middleware;

use App\Support\WebCron;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** เรียกงานตั้งเวลาหลังส่งหน้าเว็บแล้ว เมื่อโฮสต์ไม่มี cron */
class RunWebCron
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->isMethod('GET') && ! $request->is('api/*', 'install', 'install/*', 'update', 'update/*')) {
            WebCron::maybeRun();
        }
    }
}
