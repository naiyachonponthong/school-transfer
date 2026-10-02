@props(['route', 'params' => [], 'admin' => false, 'manager' => false])
{{-- ปุ่ม "เปิดหน้านี้ในระบบ" — แสดงเฉพาะเมื่อผู้อ่านเปิดหน้านั้นได้จริง (ดูจาก middleware role: ของ route) และซ่อนในฉบับพิมพ์ --}}
@php
    $user = auth()->user();
    $target = \Illuminate\Support\Facades\Route::getRoutes()->getByName($route);
    $allowed = $target && $user && ! view()->shared('manualPrint') && (! $admin || $user->isAdmin()) && (! $manager || $user->canManageFacilities());
    foreach ($allowed ? $target->gatherMiddleware() : [] as $m) {
        if (is_string($m) && str_starts_with($m, 'role:')) {
            $allowed = $allowed && in_array($user->role, explode(',', substr($m, 5)), true);
        }
    }
@endphp
@if ($allowed)
    <a href="{{ route($route, $params) }}" class="btn btn-sm btn-outline-primary manual-go no-print"><i class="bi bi-box-arrow-up-right"></i> {{ $slot }}</a>
@endif
