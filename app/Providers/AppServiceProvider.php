<?php

namespace App\Providers;

use App\Models\LeaveRequest;
use App\Models\Term;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Carbon::setLocale('th');

        // ขึ้นเซิร์ฟเวอร์จริงแล้ว (APP_ENV=production): บังคับ https ทุกลิงก์ที่สร้างขึ้น
        // และตั้งให้คุกกี้เซสชันส่งผ่าน https เท่านั้น กันการดักข้อมูลระหว่างทาง
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
            config(['session.secure' => true]);
        }

        // ตัวเลขบนเมนู/กระดิ่ง คำนวณครั้งเดียวต่อ request แล้วแชร์ให้ทุก view
        View::composer('*', function ($view) {
            $request = request();
            if (! $request->attributes->has('nav_shared')) {
                $user = auth()->user();
                $pendingLeaves = 0;
                if ($user?->isStaff()) {
                    $q = LeaveRequest::where('status', 'pending');
                    if (! $user->isAdmin()) {
                        $q->whereHas('student', fn ($s) => $s->whereIn('classroom_id', $user->myClassrooms()->pluck('id')));
                    }
                    $pendingLeaves = $q->count();
                }
                $request->attributes->set('nav_shared', [
                    'currentTerm' => Term::current(),
                    'navPendingLeaves' => $pendingLeaves,
                    'navUnread' => $user ? \App\Support\Notifications::unreadCount($user) : 0,
                ]);
            }
            $view->with($request->attributes->get('nav_shared'));
        });
    }
}
