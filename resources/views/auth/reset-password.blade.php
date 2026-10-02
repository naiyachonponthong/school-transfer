@extends('layouts.auth')
@section('title', 'ตั้งรหัสผ่านใหม่')

@section('content')
<h1 class="h4 fw-bold mb-1">ตั้งรหัสผ่านใหม่</h1>
<p class="text-muted small mb-4">กรอกรหัสยืนยัน 6 หลักที่ได้รับทาง LINE</p>
<form method="POST" action="{{ route('password.reset.save') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label" for="username">ชื่อผู้ใช้ / เบอร์โทร / อีเมล</label>
        <input id="username" name="username" value="{{ old('username', $username) }}" class="form-control" required autocomplete="username">
    </div>
    <div class="mb-3">
        <label class="form-label" for="code">รหัสยืนยัน</label>
        <input id="code" name="code" class="form-control form-control-lg text-center fs-4 @error('code') is-invalid @enderror" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required autofocus style="letter-spacing:.4em">
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    @include('auth._password-fields')
    <button class="btn btn-primary btn-lg w-100">ตั้งรหัสผ่านใหม่</button>
</form>
<a href="{{ route('password.forgot') }}" class="btn btn-link w-100 mt-2">ขอรหัสยืนยันใหม่</a>
@endsection
