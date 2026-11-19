@extends('install.layout', ['title' => 'โรงเรียนและผู้ดูแล | ติดตั้ง ระบบบริหารโรงเรียน'])

@section('content')
@php
    $mode = old('mode', $school['mode'] ?? ($db['existing'] ? 'keep' : 'new'));
    $demo = (string) old('demo', isset($school['demo']) ? (int) $school['demo'] : 0);
@endphp
<div class="inst-head">
    <div class="eyebrow">ขั้นตอนที่ 4 จาก 5</div>
    <h1>ข้อมูลโรงเรียนและบัญชีผู้ดูแลระบบ</h1>
    <p>เชื่อมต่อ {{ $db['server'] }} แล้ว กรอกข้อมูลด้านล่าง โลโก้ ที่อยู่ และสีธีม ตั้งภายหลังได้ในหน้าตั้งค่า</p>
</div>

@if ($errors->any())
    <div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i><div>กรอกข้อมูลยังไม่ครบหรือไม่ถูกต้อง ตรวจช่องที่มีข้อความสีแดง</div></div>
@endif

<form method="POST" action="{{ route('install.school.save') }}" id="schoolForm" data-busy="กำลังเตรียมติดตั้ง" novalidate>
    @csrf
    @if ($db['existing'])
        <div class="inst-card">
            <h2><i class="bi bi-exclamation-triangle"></i>พบข้อมูล ระบบบริหารโรงเรียน เดิมในฐานข้อมูลนี้</h2>
            <div class="choice-grid">
                <label class="choice"><input type="radio" name="mode" value="keep" @checked($mode === 'keep')><span class="ic"><i class="bi bi-arrow-repeat"></i></span>
                    <span><b>ใช้ข้อมูลเดิมต่อ</b><small>ไม่ลบอะไร ปรับตารางให้ตรงเวอร์ชันนี้ แล้วเข้าด้วยบัญชีเดิม</small></span></label>
                <label class="choice danger"><input type="radio" name="mode" value="wipe" @checked($mode === 'wipe')><span class="ic"><i class="bi bi-trash3"></i></span>
                    <span><b>ลบข้อมูลเดิมทั้งหมด</b><small>ล้างทุกตารางแล้วติดตั้งใหม่ กู้คืนไม่ได้</small></span></label>
            </div>
            <div class="mt-3 {{ $mode === 'wipe' ? '' : 'd-none' }}" id="wipeBox">
                <label class="form-label" for="confirm">พิมพ์คำว่า <b>ลบทั้งหมด</b> เพื่อยืนยัน</label>
                <input id="confirm" name="confirm" class="form-control @error('confirm') is-invalid @enderror" value="{{ old('confirm') }}" autocomplete="off">
                @error('confirm')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    @else
        <input type="hidden" name="mode" value="new">
    @endif

    <div class="inst-card">
        <h2><i class="bi bi-globe2"></i>ที่อยู่เว็บของระบบ</h2>
        <input name="app_url" class="form-control @error('app_url') is-invalid @enderror" value="{{ old('app_url', $school['app_url'] ?? $appUrl) }}" inputmode="url">
        @error('app_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">ใช้สร้างลิงก์ในข้อความ LINE และ QR ถ้าโดเมนติด SSL แล้วให้ขึ้นต้นด้วย https:// (สแกน QR ด้วยกล้องและลงเวลาด้วย GPS ใช้ได้เฉพาะ https)</div>
    </div>

    <div id="newBox" class="{{ $mode === 'keep' ? 'd-none' : '' }}">
        <div class="inst-card">
            <h2><i class="bi bi-building"></i>โรงเรียน</h2>
            <div class="row g-3">
                <div class="col-sm-8"><label class="form-label req" for="school_name">ชื่อโรงเรียน</label><input id="school_name" name="school_name" class="form-control @error('school_name') is-invalid @enderror" value="{{ old('school_name', $school['school_name'] ?? '') }}" maxlength="150" placeholder="เช่น โรงเรียนบ้านหนองแวง">@error('school_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-sm-4"><label class="form-label" for="school_short">ชื่อย่อ</label><input id="school_short" name="school_short" class="form-control @error('school_short') is-invalid @enderror" value="{{ old('school_short', $school['school_short'] ?? '') }}" maxlength="60" placeholder="เว้นว่าง = ใช้ชื่อเต็ม">@error('school_short')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            </div>
        </div>

        <div class="inst-card">
            <h2><i class="bi bi-person-badge"></i>บัญชีผู้ดูแลระบบ</h2>
            <div class="row g-3">
                <div class="col-sm-6"><label class="form-label req" for="admin_name">ชื่อ-นามสกุล</label><input id="admin_name" name="admin_name" class="form-control @error('admin_name') is-invalid @enderror" value="{{ old('admin_name', $school['admin_name'] ?? '') }}" autocomplete="name">@error('admin_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-sm-6"><label class="form-label req" for="username">ชื่อผู้ใช้ (ใช้เข้าสู่ระบบ)</label><input id="username" name="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username', $school['username'] ?? 'admin') }}" autocomplete="username" autocapitalize="off" spellcheck="false">@error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">ภาษาอังกฤษหรือตัวเลข</div></div>
                <div class="col-sm-6"><label class="form-label req" for="password">รหัสผ่าน</label>
                    <div class="input-group has-validation"><input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" minlength="8"><button type="button" class="btn btn-light" data-eye="password" aria-label="แสดงรหัสผ่าน"><i class="bi bi-eye"></i></button>@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="form-text">อย่างน้อย 8 ตัว</div></div>
                <div class="col-sm-6"><label class="form-label req" for="password_confirmation">ยืนยันรหัสผ่าน</label><input id="password_confirmation" name="password_confirmation" type="password" class="form-control" autocomplete="new-password"></div>
            </div>
        </div>

        <div class="inst-card">
            <h2><i class="bi bi-stars"></i>เริ่มต้นแบบไหน</h2>
            <div class="choice-grid">
                <label class="choice"><input type="radio" name="demo" value="0" @checked($demo !== '1')><span class="ic"><i class="bi bi-building-check"></i></span>
                    <span><b>เริ่มใช้งานจริง</b><small>ข้อมูลว่าง มีบทบาทและสิทธิ์ตั้งต้นกับบัญชีผู้ดูแล แล้วเพิ่มปีการศึกษา ห้องเรียน ครู นักเรียนเอง</small></span></label>
                <label class="choice"><input type="radio" name="demo" value="1" @checked($demo === '1')><span class="ic"><i class="bi bi-magic"></i></span>
                    <span><b>มีข้อมูลตัวอย่าง</b><small>นักเรียน ครู ห้องเรียน ตารางสอน คะแนน การเงิน ห้องสมุด และบัญชีตัวอย่าง ไว้ทดลองหรือสาธิต</small></span></label>
            </div>
        </div>
    </div>

    <div class="inst-actions">
        <a href="{{ route('install.database') }}" class="btn btn-light btn-lg"><i class="bi bi-arrow-left"></i>ย้อนกลับ</a>
        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-rocket-takeoff"></i>เริ่มติดตั้ง</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
  const f = document.getElementById('schoolForm');
  f.querySelectorAll('[name=mode]').forEach((r) => r.addEventListener('change', () => {
    const m = f.querySelector('[name=mode]:checked')?.value;
    document.getElementById('newBox').classList.toggle('d-none', m === 'keep');
    document.getElementById('wipeBox')?.classList.toggle('d-none', m !== 'wipe');
  }));
})();
</script>
@endpush
