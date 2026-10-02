<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ school('school_name') }}</title>
    @include('partials.assets')
</head>
<body>
{{-- หน้าเดี่ยวเรื่องรหัสผ่าน (ไม่มีเมนู) --}}
<div class="container py-5" style="max-width:460px">
    <div class="text-center mb-4">
        <span class="sb-brand-logo mx-auto mb-2 text-white" style="width:56px;height:56px;font-size:1.6rem">
            @if (school('logo'))<img src="{{ asset('storage/'.school('logo')) }}" alt="">@else<i class="bi bi-mortarboard-fill"></i>@endif
        </span>
        <div class="fw-bold">{{ school('school_name') }}</div>
    </div>
    <div class="card"><div class="card-body p-4">
        @if (session('success'))<div class="alert alert-success small">{{ session('success') }}</div>@endif
        @yield('content')
    </div></div>
</div>
</body>
</html>
