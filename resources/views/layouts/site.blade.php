@php
    $siteName = school('school_name');
    $menuPages = \App\Support\Website::menuPages();
    $logo = school('logo') ? asset('storage/'.school('logo')) : null;
    $pageTitle = trim($__env->yieldContent('title'));
    $desc = trim($__env->yieldContent('description')) ?: (school('website_tagline') ?: 'เว็บไซต์'.$siteName);
    $phone = school('school_phone');
    $tel = $phone ? preg_replace('/[^0-9+]/', '', $phone) : null;
    $lineUrl = school('website_line') ?: (school('line_oa_id') ? 'https://line.me/R/ti/p/'.school('line_oa_id') : null);
    $footerBg = \App\Support\Artwork::uploaded('website_footer');
@endphp
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $pageTitle ? $pageTitle.' · ' : '' }}{{ $siteName }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit($desc, 160) }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $pageTitle ?: $siteName }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit($desc, 200) }}">
    <meta property="og:image" content="{{ trim($__env->yieldContent('og_image')) ?: ($logo ?? \App\Support\Artwork::url('dashboard')) }}">
    <link rel="canonical" href="{{ url()->current() }}">
    @include('partials.assets')
    <link rel="stylesheet" href="{{ asset('assets/css/website.css') }}?v={{ @filemtime(public_path('assets/css/website.css')) }}">
    @stack('head')
</head>
<body class="site" id="top">
@unless (\App\Support\Website::enabled())
    <div class="site-preview-bar"><i class="bi bi-eye"></i> โหมดตัวอย่าง: เว็บไซต์ยังปิดอยู่ คนทั่วไปยังไม่เห็นหน้านี้ เปิดได้ที่ ตั้งค่า &gt; เว็บไซต์โรงเรียน</div>
@endunless

<div class="site-topbar">
    <div class="site-wrap site-topbar-inner">
        <span class="d-none d-md-inline text-truncate">{{ school('website_tagline') ?: $siteName }}</span>
        <span class="site-topbar-contact">
            @if ($phone)<a href="tel:{{ $tel }}"><i class="bi bi-telephone"></i> {{ $phone }}</a>@endif
            @if (school('website_email'))<a href="mailto:{{ school('website_email') }}" class="d-none d-sm-inline"><i class="bi bi-envelope"></i> {{ school('website_email') }}</a>@endif
        </span>
        <span class="site-topbar-social">
            @if (school('website_facebook'))<a href="{{ school('website_facebook') }}" target="_blank" rel="noopener" aria-label="Facebook"><i class="bi bi-facebook"></i></a>@endif
            @if ($lineUrl)<a href="{{ $lineUrl }}" target="_blank" rel="noopener" aria-label="LINE"><i class="bi bi-line"></i></a>@endif
            @if (school('website_youtube'))<a href="{{ school('website_youtube') }}" target="_blank" rel="noopener" aria-label="YouTube"><i class="bi bi-youtube"></i></a>@endif
        </span>
    </div>
</div>

<header class="site-header">
    <div class="site-wrap site-header-inner">
        <a href="{{ route('web.home') }}" class="site-brand">
            <span class="site-logo">@if ($logo)<img src="{{ $logo }}" alt="ตราโรงเรียน">@else<i class="bi bi-mortarboard-fill"></i>@endif</span>
            <span><b>{{ $siteName }}</b>@if (school('school_affiliation'))<small>{{ school('school_affiliation') }}</small>@endif</span>
        </a>
        <nav class="site-nav" id="siteNav" aria-label="เมนูหลัก">
            <a href="{{ route('web.home') }}" class="{{ request()->routeIs('web.home') || request()->is('/') ? 'active' : '' }}"><b>หน้าแรก</b><small>HOME</small></a>
            @if ($menuPages->isNotEmpty())
                <div class="dropdown">
                    <a href="#" class="dropdown-toggle {{ request()->routeIs('web.page') ? 'active' : '' }}" data-bs-toggle="dropdown" aria-expanded="false"><b>เกี่ยวกับโรงเรียน</b><small>ABOUT</small></a>
                    <div class="dropdown-menu">
                        @foreach ($menuPages as $p)<a class="dropdown-item" href="{{ route('web.page', $p->slug) }}">{{ $p->title }}</a>@endforeach
                    </div>
                </div>
            @endif
            <div class="dropdown">
                <a href="#" class="dropdown-toggle {{ request()->routeIs('web.news*', 'web.post') ? 'active' : '' }}" data-bs-toggle="dropdown" aria-expanded="false"><b>ข่าวสาร</b><small>NEWS</small></a>
                <div class="dropdown-menu">
                    <a class="dropdown-item" href="{{ route('web.news') }}">ข่าวทั้งหมด</a>
                    @foreach (\App\Models\WebPost::CATEGORIES as $key => [$label, $icon])
                        <a class="dropdown-item" href="{{ route('web.news.category', $key) }}"><i class="bi bi-{{ $icon }}"></i> {{ $label }}</a>
                    @endforeach
                </div>
            </div>
            <a href="{{ route('web.personnel') }}" class="{{ request()->routeIs('web.personnel') ? 'active' : '' }}"><b>บุคลากร</b><small>PERSONNEL</small></a>
            <a href="{{ route('web.contact') }}" class="{{ request()->routeIs('web.contact') ? 'active' : '' }}"><b>ติดต่อเรา</b><small>CONTACT</small></a>
            <a href="{{ auth()->check() ? route('home') : route('login') }}" class="site-nav-login"><b>{{ auth()->check() ? 'เข้าระบบโรงเรียน' : 'เข้าสู่ระบบ' }}</b><small>LOGIN</small></a>
        </nav>
        <div class="site-actions">
            <div class="site-cta d-none d-lg-flex">
                <a href="{{ auth()->check() ? route('home') : route('login') }}" class="btn btn-primary"><i class="bi bi-{{ auth()->check() ? 'grid-3x3-gap-fill' : 'box-arrow-in-right' }}"></i>{{ auth()->check() ? 'เข้าระบบโรงเรียน' : 'เข้าสู่ระบบ' }}</a>
                @if ($phone)<small>โทร {{ $phone }}</small>@endif
            </div>
            <button class="btn btn-light site-menu-btn" type="button" aria-label="เปิดเมนู" aria-controls="siteNav" aria-expanded="false" id="siteMenuBtn"><i class="bi bi-list"></i></button>
        </div>
    </div>
</header>

<main class="site-main">
    @include('partials.flash')
    @yield('content')
</main>

<footer class="site-footer {{ $footerBg ? 'has-photo' : '' }}" @if ($footerBg) style="--footer-photo:url('{{ $footerBg }}')" @endif>
    <div class="site-wrap">
        <div class="site-footer-top">
            <div class="site-brand">
                <span class="site-logo">@if ($logo)<img src="{{ $logo }}" alt="">@else<i class="bi bi-mortarboard-fill"></i>@endif</span>
                <span><b>{{ $siteName }}</b>@if (school('school_affiliation'))<small>{{ school('school_affiliation') }}</small>@endif</span>
            </div>
            <p class="mb-0">{{ \Illuminate\Support\Str::limit(school('website_intro') ?: (school('website_tagline') ?: 'มุ่งมั่นพัฒนาผู้เรียนให้มีคุณภาพ'), 180) }}</p>
            <div class="site-social">
                @if (school('website_facebook'))<a href="{{ school('website_facebook') }}" target="_blank" rel="noopener" aria-label="Facebook"><i class="bi bi-facebook"></i></a>@endif
                @if ($lineUrl)<a href="{{ $lineUrl }}" target="_blank" rel="noopener" aria-label="LINE"><i class="bi bi-line"></i></a>@endif
                @if (school('website_youtube'))<a href="{{ school('website_youtube') }}" target="_blank" rel="noopener" aria-label="YouTube"><i class="bi bi-youtube"></i></a>@endif
                @if (school('website_email'))<a href="mailto:{{ school('website_email') }}" aria-label="อีเมล"><i class="bi bi-envelope"></i></a>@endif
            </div>
        </div>
        <div class="site-footer-grid">
            <div>
                <h3>เมนูหลัก</h3>
                <a href="{{ route('web.home') }}">หน้าแรก</a>
                <a href="{{ route('web.news') }}">ข่าวสาร</a>
                <a href="{{ route('web.personnel') }}">ทำเนียบบุคลากร</a>
                <a href="{{ route('web.contact') }}">ติดต่อเรา</a>
            </div>
            <div>
                <h3>เกี่ยวกับโรงเรียน</h3>
                @forelse ($menuPages->take(6) as $p)<a href="{{ route('web.page', $p->slug) }}">{{ $p->title }}</a>@empty<a href="{{ route('web.contact') }}">ข้อมูลโรงเรียน</a>@endforelse
            </div>
            <div>
                <h3>บริการ</h3>
                <a href="{{ route('apply') }}">สมัครเรียนออนไลน์</a>
                <a href="{{ route('login') }}">ครูและบุคลากร</a>
                <a href="{{ route('login') }}">ผู้ปกครองและนักเรียน</a>
                <a href="{{ route('web.news.category', 'procurement') }}">ข่าวจัดซื้อจัดจ้าง</a>
            </div>
            <div>
                <h3>ที่ตั้งและเวลาทำการ</h3>
                @if (school('school_address'))<p><i class="bi bi-geo-alt"></i> {{ school('school_address') }}</p>@endif
                @if ($phone)<p><i class="bi bi-telephone"></i> <a href="tel:{{ $tel }}">{{ $phone }}</a></p>@endif
                @if (school('website_hours'))<p><i class="bi bi-clock"></i> {{ school('website_hours') }}</p>@endif
            </div>
        </div>
    </div>
</footer>
<div class="site-copy">
    <div class="site-wrap">© {{ now()->year + 543 }} {{ $siteName }} สงวนลิขสิทธิ์@if (($credit = \App\Support\Brand::credit()) !== '') · {{ $credit }}@endif</div>
</div>

<div class="site-float" aria-label="ทางลัด">
    <a href="#top" class="to-top" id="toTop" aria-label="กลับขึ้นด้านบน" hidden><i class="bi bi-arrow-up"></i></a>
    @if ($lineUrl)<a href="{{ $lineUrl }}" target="_blank" rel="noopener" class="line" aria-label="ติดต่อทาง LINE"><i class="bi bi-line"></i></a>@endif
    @if (school('website_facebook'))<a href="{{ school('website_facebook') }}" target="_blank" rel="noopener" class="fb" aria-label="Facebook"><i class="bi bi-messenger"></i></a>@endif
</div>

<script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}" defer></script>
<script>
(function () {
  const btn = document.getElementById('siteMenuBtn');
  btn.addEventListener('click', function () {
    const open = document.body.classList.toggle('site-nav-open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    btn.innerHTML = open ? '<i class="bi bi-x-lg"></i>' : '<i class="bi bi-list"></i>';
  });
  const top = document.getElementById('toTop');
  const onScroll = () => { top.hidden = window.scrollY < 500; document.body.classList.toggle('site-scrolled', window.scrollY > 40); };
  window.addEventListener('scroll', onScroll, { passive: true }); onScroll();
})();
</script>
@stack('scripts')
</body>
</html>
