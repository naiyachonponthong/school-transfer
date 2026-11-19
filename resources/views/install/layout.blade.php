@php
    $labels = [1 => ['ตรวจเครื่อง', 'PHP และสิทธิ์ไฟล์'], 2 => ['License', 'คีย์ หรือทดลองใช้'], 3 => ['ฐานข้อมูล', 'เชื่อมต่อฐานข้อมูล'], 4 => ['โรงเรียนและผู้ดูแล', 'ชื่อโรงเรียน บัญชีผู้ดูแล'], 5 => ['ติดตั้ง', 'สร้างตารางและเปิดระบบ']];
    $step = $step ?? 1;
    // หน้านี้เปิดก่อนมีฐานข้อมูล และต้องไม่ล้มเพราะไฟล์หน้าตาของโปรเจ็คอยู่คนละที่: หาไฟล์ที่มีจริงก่อนใช้
    $pick = fn (array $paths) => collect($paths)->first(fn ($p) => is_file(public_path($p)));
    $localUi = is_file(public_path('vendor/bootstrap/bootstrap.min.css')); // ไม่มีชุดในเครื่อง ใช้ CDN ชุดเดียวกับ school-superapp-ui
    $appCss = $pick(['css/app.css', 'assets/css/app.css']);
    $instCss = $pick(['css/installer.css', 'assets/css/installer.css']);
    $icon = $pick(['img/icon-192.png', 'assets/icons/icon-192.png']);
    $hero = collect(['img', 'assets/img'])->flatMap(fn ($d) => array_map(fn ($f) => $d.'/'.basename($f), glob(public_path($d.'/*-hero.png')) ?: []))->first();
    $themeCss = class_exists(\App\Support\Theme::class) ? (string) \App\Support\Theme::css('#F26522') : '';
@endphp
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#243954">
    <title>{{ $title ?? 'ติดตั้ง ระบบบริหารโรงเรียน' }}</title>
    @if ($icon)<link rel="icon" type="image/png" href="{{ asset($icon) }}">@endif
    @if ($localUi)
        <link href="{{ asset('vendor/sarabun/sarabun.css') }}" rel="stylesheet">
        <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
        <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    @else
        <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    @endif
    @if ($appCss)<link href="{{ asset($appCss) }}?v={{ filemtime(public_path($appCss)) }}" rel="stylesheet">@endif
    @if ($instCss)<link href="{{ asset($instCss) }}?v={{ filemtime(public_path($instCss)) }}" rel="stylesheet">@endif
    {{-- Theme::css คืนได้ทั้งแบบมี :root{} ครอบ และแบบตัวแปรล้วน --}}
    @if ($themeCss !== '')<style id="theme-vars">{!! str_contains($themeCss, '{') ? $themeCss : ':root{'.$themeCss.'}' !!}</style>@endif
</head>
<body>
<div class="inst-wrap">
    <aside class="inst-side {{ $step ? '' : 'no-steps' }}">
        <div class="inst-brand"><span class="sb-logo"><i class="bi bi-mortarboard-fill"></i></span><div><b>ระบบบริหารโรงเรียน</b><small>{{ $brandSub ?? ($step ? 'ตัวติดตั้งระบบบริหารจัดการโรงเรียนครบวงจร' : 'อัปเดตระบบบริหารจัดการโรงเรียนครบวงจร') }}</small></div></div>
        @if ($hero)<img src="{{ asset($hero) }}" alt="" class="art">@endif
        @if ($step)
            <ol class="inst-steps">
                @foreach ($labels as $n => [$t, $s])
                    <li class="{{ $n < $step ? 'done' : ($n === $step ? 'cur' : '') }}"><span class="n">@if ($n < $step)<i class="bi bi-check-lg"></i>@else{{ $n }}@endif</span><div>{{ $t }}<small>{{ $s }}</small></div></li>
                @endforeach
            </ol>
        @endif
        <div class="inst-credit">@php($credit = \App\Support\Brand::credit()){{ $credit !== '' ? $credit.' · ' : '' }}เวอร์ชัน {{ config('school.version') }}</div>
    </aside>
    <main class="inst-main">
        <div class="inst-box">
            <div class="inst-top">
                <div class="inst-brand"><span class="sb-logo"><i class="bi bi-mortarboard-fill"></i></span><div><b>ระบบบริหารโรงเรียน</b><small class="text-muted">{{ $brandSub ?? ($step ? 'ตัวติดตั้งระบบบริหารจัดการโรงเรียนครบวงจร' : 'อัปเดตระบบ') }}</small></div></div>
                @if ($step)<div class="bar">@for ($n = 1; $n <= 4; $n++)<i class="{{ $n <= min($step, 4) ? 'on' : '' }}"></i>@endfor</div>@endif
            </div>
            @yield('content')
        </div>
    </main>
</div>
<script src="{{ $localUi ? asset('vendor/bootstrap/bootstrap.bundle.min.js') : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js' }}"></script>
<script>
window.INST = {
  csrf: document.querySelector('meta[name=csrf-token]').content,
  async post(url, data) {
    const r = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json', ...(data instanceof FormData ? {} : { 'Content-Type': 'application/json' }) }, body: data instanceof FormData ? data : JSON.stringify(data || {}) });
    let j = {}; try { j = await r.json(); } catch (e) { j = { ok: false, message: 'เซิร์ฟเวอร์ตอบกลับผิดรูปแบบ (HTTP ' + r.status + ')' }; }
    if (r.status === 422 && j.errors) j = { ok: false, message: Object.values(j.errors).flat().join(' ') };
    if (r.status === 419) j = { ok: false, message: j.message || 'หน้านี้เปิดค้างนานเกินไป กดรีเฟรชแล้วลองใหม่' };
    return j;
  },
  esc: (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])),
};
document.querySelectorAll('[data-eye]').forEach((b) => b.addEventListener('click', () => { const i = document.getElementById(b.dataset.eye); i.type = i.type === 'password' ? 'text' : 'password'; b.querySelector('i').className = 'bi bi-' + (i.type === 'password' ? 'eye' : 'eye-slash'); }));
document.querySelectorAll('form[data-busy]').forEach((f) => f.addEventListener('submit', () => { const b = f.querySelector('[type=submit]'); if (b) { b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm"></span> ' + f.dataset.busy; } }));
</script>
@stack('scripts')
</body>
</html>
