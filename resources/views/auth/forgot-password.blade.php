@extends('layouts.auth')
@section('title', 'ลืมรหัสผ่าน')

@section('content')
<h1 class="h4 fw-bold mb-1">ลืมรหัสผ่าน</h1>
<p class="text-muted small mb-4">ระบบจะส่งรหัสยืนยัน 6 หลักไปทาง LINE ที่คุณเชื่อมกับบัญชีไว้</p>
<form method="POST" action="{{ route('password.forgot.send') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label" for="username">ชื่อผู้ใช้ / เบอร์โทร / อีเมล</label>
        <input id="username" name="username" value="{{ old('username') }}" class="form-control form-control-lg @error('username') is-invalid @enderror" required autofocus autocomplete="username">
        @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <button class="btn btn-primary btn-lg w-100">ส่งรหัสยืนยันทาง LINE</button>
</form>
<div class="small text-muted mt-4">
    <i class="bi bi-info-circle"></i> ยังไม่ได้เชื่อม LINE? ติดต่อครูประจำชั้นหรือฝ่ายทะเบียนเพื่อรีเซ็ตรหัสผ่าน (เมื่อเข้าระบบแล้ว ระบบจะให้ตั้งรหัสใหม่เอง)
</div>
<a href="{{ route('login') }}" class="btn btn-link w-100 mt-2">กลับไปหน้าเข้าสู่ระบบ</a>
@endsection
