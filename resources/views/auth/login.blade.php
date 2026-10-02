<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เข้าสู่ระบบ · {{ school('school_name') }}</title>
    @include('partials.assets')
</head>
<body>
<div class="login-wrap">
    <div class="login-hero">
        <div class="d-flex align-items-center gap-3 login-hero-brand">
            <span class="sb-brand-logo" style="width:48px;height:48px;font-size:1.5rem">
                @if (school('logo'))<img src="{{ asset('storage/'.school('logo')) }}" alt="">@else<i class="bi bi-mortarboard-fill"></i>@endif
            </span>
            <div>
                <div class="fs-5 fw-bold">{{ school('school_name') }}</div>
                <div class="opacity-75 small">ระบบบริหารจัดการโรงเรียน</div>
            </div>
        </div>
        <div class="login-hero-copy">
            <span class="school-hero-eyebrow">SCHOOL SUPER APP</span>
            <h2 class="fw-bold mb-3">ทุกงานของโรงเรียน<br>จบในที่เดียว ง่ายกว่าเดิม</h2>
            <p>เชื่อมครู นักเรียน และผู้ปกครองไว้ในพื้นที่เดียว</p>
        </div>
        <img class="login-hero-art" src="{{ asset('assets/img/school-community-hero.png') }}" alt="" aria-hidden="true" width="1536" height="1024" fetchpriority="high">
        <div class="login-hero-footer">
            <span><i class="bi bi-check2-square"></i> เช็คชื่อรวดเร็ว</span>
            <span><i class="bi bi-journal-check"></i> คะแนนอัตโนมัติ</span>
            <span><i class="bi bi-phone"></i> ผู้ปกครองติดตามได้</span>
        </div>
    </div>

    <div class="login-form">
        <div class="inner">
            <div class="d-lg-none text-center mb-4 mobile-login-brand">
                <span class="sb-brand-logo mx-auto mb-2 text-white" style="width:56px;height:56px;font-size:1.6rem"><i class="bi bi-mortarboard-fill"></i></span>
                <div class="fw-bold fs-5">{{ school('school_name') }}</div>
            </div>
            <h1 class="h3 fw-bold mb-1">เข้าสู่ระบบ</h1>
            <p class="text-muted mb-4">ครู บุคลากร และผู้ปกครอง ใช้หน้านี้เข้าระบบ</p>

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="username">ชื่อผู้ใช้ / เบอร์โทร / อีเมล</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
                        <input id="username" name="username" value="{{ old('username') }}" class="form-control @error('username') is-invalid @enderror" autofocus autocomplete="username" required>
                    </div>
                    @error('username')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">รหัสผ่าน</label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                        <input id="password" type="password" name="password" class="form-control" autocomplete="current-password" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password';this.innerHTML=p.type==='password'?'<i class=\'bi bi-eye\'></i>':'<i class=\'bi bi-eye-slash\'></i>'"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" checked>
                    <label class="form-check-label" for="remember">จดจำการเข้าสู่ระบบ</label>
                </div>
                <button class="btn btn-primary btn-lg w-100">เข้าสู่ระบบ</button>
            </form>
            <button type="button" class="btn btn-soft w-100 mt-3 d-none" data-install-app><i class="bi bi-download"></i> ติดตั้งแอป</button>
            @if (\App\Support\AdmissionForm::isOpen())
                <a href="{{ route('apply') }}" class="btn btn-soft w-100 mt-3"><i class="bi bi-person-plus"></i> สมัครเรียนออนไลน์</a>
            @endif
            <p class="text-muted small mt-4 mb-0"><i class="bi bi-info-circle"></i> ผู้ปกครอง: ใช้เบอร์โทรที่ให้ไว้กับโรงเรียน รหัสผ่านเริ่มต้นคือ 6 หลักท้ายของเบอร์โทร</p>
        </div>
    </div>
</div>
</body>
</html>
