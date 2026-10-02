<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ school('school_name') }}</title>
    @include('partials.assets')
</head>
<body>
<div class="m-hero" style="margin:0;border-radius:0 0 28px 28px;padding-bottom:4.5rem">
    <div class="container" style="max-width:820px">
        <div class="top">
            <span class="sb-logo" style="background:rgba(255,255,255,.2);box-shadow:none">@if (school('logo'))<img src="{{ asset('storage/'.school('logo')) }}" alt="">@else<i class="bi bi-mortarboard-fill"></i>@endif</span>
            <div><div class="fw-bold">{{ school('school_name') }}</div><div class="small opacity-75">{{ school('school_address') }}</div></div>
            <a href="{{ route('login') }}" class="btn btn-sm btn-light ms-auto">เข้าสู่ระบบ</a>
        </div>
        <div class="hello">@yield('heading')<small>@yield('subheading')</small></div>
    </div>
</div>
<div class="container pb-5" style="max-width:820px;margin-top:-3.2rem;position:relative;z-index:2">
    @include('partials.flash')
    @yield('content')
</div>
<script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
