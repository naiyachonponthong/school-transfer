@extends('install.layout', ['title' => 'อัปเดตระบบ | ระบบบริหารโรงเรียน', 'step' => 0])

@section('content')
<div class="inst-head">
    <div class="eyebrow">อัปเดตระบบ</div>
    <h1>มีเวอร์ชันใหม่รอติดตั้ง</h1>
    <p>ไฟล์ชุดใหม่อัปโหลดแล้ว ต้องปรับฐานข้อมูลก่อนใช้งานต่อ ข้อมูลโรงเรียนไม่หาย</p>
</div>

@if ($errors->any())
    <div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i><div>{{ $errors->first() }}</div></div>
@endif

<div class="inst-card">
    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
        <span class="ver-pill">{{ $from === '0.0.0' ? 'เวอร์ชันเดิม' : 'v'.$from }}</span><i class="bi bi-arrow-right"></i><span class="ver-pill to">v{{ $to }}</span>
    </div>
    <div class="text-muted small">{{ count($pending) ? 'ปรับโครงสร้างฐานข้อมูล '.count($pending).' รายการ' : 'ไม่มีการเปลี่ยนตาราง ปรับเวอร์ชันอย่างเดียว' }}</div>
</div>

@if (! $user)
    <div class="inst-card">
        <h2><i class="bi bi-person-lock"></i>เข้าสู่ระบบด้วยบัญชีผู้ดูแลระบบ</h2>
        <p class="text-muted">เฉพาะผู้ดูแลระบบที่กดอัปเดตได้ ผู้ใช้อื่นรอสักครู่</p>
        <a href="{{ route('login') }}" class="btn btn-primary btn-lg w-100"><i class="bi bi-box-arrow-in-right"></i>เข้าสู่ระบบ</a>
    </div>
@elseif (! $canUpdate)
    <div class="alert alert-warning"><i class="bi bi-hourglass-split"></i><div><b>รอผู้ดูแลระบบกดอัปเดต</b> คุณเข้าสู่ระบบเป็น {{ $user->name }} แจ้งผู้ดูแลระบบให้เข้าระบบเพื่ออัปเดต</div></div>
    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-light"><i class="bi bi-box-arrow-right"></i>ออกจากระบบ</button></form>
@else
    <form method="POST" action="{{ route('update.run') }}" class="inst-card" data-busy="กำลังอัปเดต อย่าปิดหน้านี้">
        @csrf
        <h2><i class="bi bi-cloud-arrow-up"></i>อัปเดตตอนนี้</h2>
        <div class="form-check mb-3"><input type="hidden" name="backup" value="0"><input class="form-check-input" type="checkbox" name="backup" value="1" id="bk" checked><label class="form-check-label" for="bk">สำรองฐานข้อมูลก่อนอัปเดต (แนะนำ) ดาวน์โหลดได้ที่เมนู สำรองข้อมูล</label></div>
        <button type="submit" class="btn btn-primary btn-lg w-100" id="doUpdate"><i class="bi bi-arrow-up-circle"></i>อัปเดตเป็น v{{ $to }}</button>
    </form>
@endif
@endsection
