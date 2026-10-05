@extends('layouts.app')
@section('title', 'ยืนยันตัวตน 2 ขั้น')

@section('content')
@php($required = $user->mustUseTwoFactor())
<div class="page-head">
    <div><h1>ยืนยันตัวตน 2 ขั้น</h1><div class="sub">เข้าสู่ระบบด้วยรหัสผ่าน แล้วตามด้วยรหัส 6 หลักจากแอปบนมือถือ คนที่รู้รหัสผ่านอย่างเดียวจึงเข้าบัญชีนี้ไม่ได้{{ $required ? ' · โรงเรียนกำหนดให้บัญชีนี้ต้องเปิดใช้' : '' }}</div></div>
    <div class="actions"><a href="{{ route('profile') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> ข้อมูลส่วนตัว</a></div>
</div>

@if (session('recovery_codes'))
    <div class="card border-warning mb-3">
        <div class="card-header"><i class="bi bi-key"></i> รหัสสำรอง — แสดงครั้งเดียว จดหรือพิมพ์เก็บไว้ตอนนี้</div>
        <div class="card-body">
            <p class="small text-muted mb-3">ใช้แทนรหัสจากแอปเมื่อมือถือไม่อยู่กับตัวหรือหาย รหัสละครั้งเดียว เก็บไว้ในที่ปลอดภัยและไม่ใช่ในมือถือเครื่องเดียวกัน</p>
            <div class="row g-2 font-monospace fs-5">
                @foreach (session('recovery_codes') as $code)<div class="col-6 col-md-3">{{ $code }}</div>@endforeach
            </div>
        </div>
        <div class="card-footer bg-transparent no-print"><button type="button" class="btn btn-light border" onclick="print()"><i class="bi bi-printer"></i> พิมพ์</button></div>
    </div>
@endif

@if ($user->hasTwoFactor())
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-shield-check text-success"></i> เปิดใช้อยู่</div>
                <div class="card-body">
                    <div>เปิดใช้เมื่อ {{ thai_datetime($user->two_factor_confirmed_at) }}</div>
                    <div class="{{ $recoveryLeft <= 2 ? 'text-danger' : 'text-muted' }} small mt-1">รหัสสำรองที่ยังใช้ได้ {{ $recoveryLeft }} รหัส{{ $recoveryLeft <= 2 ? ' — ควรออกชุดใหม่' : '' }}</div>
                </div>
                <div class="card-footer bg-transparent d-flex flex-wrap gap-2 no-print">
                    <button class="btn btn-light border" data-bs-toggle="modal" data-bs-target="#newCodes"><i class="bi bi-arrow-repeat"></i> ออกรหัสสำรองชุดใหม่</button>
                    <button class="btn btn-light border text-danger" data-bs-toggle="modal" data-bs-target="#disable2fa">ปิดการยืนยันตัวตน 2 ขั้น</button>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-phone"></i> เปลี่ยนมือถือ หรือทำมือถือหาย</div>
                <div class="card-body small">
                    <ul class="mb-0 ps-3">
                        <li><b>เปลี่ยนเครื่อง</b>: ปิดการยืนยันตัวตน 2 ขั้นที่หน้านี้ แล้วเปิดใหม่โดยสแกน QR ด้วยเครื่องใหม่</li>
                        <li><b>มือถือหาย</b>: เข้าสู่ระบบด้วยรหัสสำรอง แล้วปิดและเปิดใหม่ด้วยเครื่องใหม่</li>
                        <li><b>ไม่มีทั้งมือถือและรหัสสำรอง</b>: ให้ผู้ดูแลระบบล้างให้ที่เมนูผู้ใช้งาน</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="newCodes" tabindex="-1">
        <div class="modal-dialog"><form method="POST" action="{{ route('two-factor.recovery') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">ออกรหัสสำรองชุดใหม่</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body">
                <div class="small text-muted mb-2">รหัสสำรองชุดเดิมจะใช้ไม่ได้ทันที</div>
                <label class="form-label" for="codesPassword">รหัสผ่านของคุณ</label>
                <input type="password" id="codesPassword" name="current_password" class="form-control" autocomplete="current-password" required>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">ออกรหัสชุดใหม่</button></div>
        </form></div>
    </div>
    <div class="modal fade" id="disable2fa" tabindex="-1">
        <div class="modal-dialog"><form method="POST" action="{{ route('two-factor.disable') }}" class="modal-content">
            @csrf @method('DELETE')
            <div class="modal-header"><h5 class="modal-title">ปิดการยืนยันตัวตน 2 ขั้น</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body">
                <div class="small text-muted mb-2">หลังปิดแล้วบัญชีนี้เข้าได้ด้วยรหัสผ่านอย่างเดียว{{ $required ? ' · โรงเรียนกำหนดให้บัญชีนี้ต้องเปิดใช้ ระบบจะให้ตั้งใหม่ทันที' : '' }}</div>
                <label class="form-label" for="disablePassword">รหัสผ่านของคุณ</label>
                <input type="password" id="disablePassword" name="current_password" class="form-control" autocomplete="current-password" required>
            </div>
            <div class="modal-footer"><button class="btn btn-danger">ปิดการยืนยันตัวตน 2 ขั้น</button></div>
        </form></div>
    </div>
@else
    <form method="POST" action="{{ route('two-factor.enable') }}" class="card">
        @csrf
        <div class="card-header"><i class="bi bi-shield-lock"></i> เปิดใช้ใน 3 ขั้น</div>
        <div class="card-body row g-4">
            <div class="col-md-4">
                <div class="fw-semibold mb-1">1. ติดตั้งแอปสร้างรหัส</div>
                <p class="small text-muted mb-0">ติดตั้ง Google Authenticator หรือ Microsoft Authenticator จาก App Store / Play Store ลงมือถือของคุณ (ใช้ได้โดยไม่ต้องมีอินเทอร์เน็ต)</p>
            </div>
            <div class="col-md-4">
                <div class="fw-semibold mb-1">2. สแกน QR ด้วยแอป</div>
                <div class="bg-white p-2 rounded-3 border d-inline-block" style="width:200px" data-qr="{{ $uri }}" data-cell="4"></div>
                <div class="small text-muted mt-2">สแกนไม่ได้ ให้เลือก "ป้อนคีย์การตั้งค่า" ในแอป แล้วพิมพ์คีย์นี้</div>
                <code class="d-block text-break user-select-all">{{ trim(chunk_split($secret, 4, ' ')) }}</code>
            </div>
            <div class="col-md-4">
                <label class="fw-semibold mb-1" for="setupCode">3. กรอกรหัส 6 หลักที่แอปแสดง</label>
                <input id="setupCode" name="code" class="form-control form-control-lg text-center fs-4 @error('code') is-invalid @enderror" inputmode="numeric" maxlength="7" autocomplete="one-time-code" required style="letter-spacing:.3em">
                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">รหัสเปลี่ยนทุก 30 วินาที หลังยืนยันแล้วระบบจะแสดงรหัสสำรองให้เก็บไว้</div>
            </div>
        </div>
        <div class="card-footer bg-transparent"><button class="btn btn-primary"><i class="bi bi-shield-check"></i> ยืนยันและเปิดใช้</button></div>
    </form>
@endif
@endsection

@push('scripts')
{{-- app.js วาด QR ให้ทุก [data-qr] เมื่อมีไลบรารีนี้ --}}
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
@endpush
