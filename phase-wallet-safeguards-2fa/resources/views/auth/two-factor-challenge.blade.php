@extends('layouts.auth')
@section('title', 'ยืนยันตัวตน')

@section('content')
<h1 class="h4 fw-bold mb-1">ยืนยันตัวตนขั้นที่ 2</h1>
<p class="text-muted small mb-4">เปิดแอปสร้างรหัสบนมือถือ (เช่น Google Authenticator) แล้วกรอกรหัส 6 หลักของบัญชีนี้</p>
<form method="POST" action="{{ route('two-factor.verify') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label" for="code">รหัสยืนยัน</label>
        <input id="code" name="code" class="form-control form-control-lg text-center fs-4 @error('code') is-invalid @enderror" inputmode="numeric" maxlength="20" autocomplete="one-time-code" required autofocus style="letter-spacing:.3em">
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">มือถือไม่อยู่กับตัว: กรอกรหัสสำรองที่เก็บไว้ตอนเปิดใช้ (รูปแบบ XXXXX-XXXXX ใช้ได้รหัสละครั้ง)</div>
    </div>
    <button class="btn btn-primary btn-lg w-100">ยืนยัน</button>
</form>
<a href="{{ route('login') }}" class="btn btn-link w-100 mt-2">กลับไปหน้าเข้าสู่ระบบ</a>
<p class="text-muted small mt-3 mb-0"><i class="bi bi-info-circle"></i> ทำมือถือหายและไม่มีรหัสสำรอง ให้ติดต่อผู้ดูแลระบบของโรงเรียนเพื่อล้างการยืนยันตัวตน 2 ขั้น</p>
@endsection
