@extends('layouts.auth')
@section('title', 'ตั้งรหัสผ่านใหม่')

@section('content')
<h1 class="h4 fw-bold mb-1">ตั้งรหัสผ่านใหม่</h1>
<p class="text-muted small mb-4">สวัสดี {{ $user->name }} — รหัสผ่านปัจจุบันถูกตั้งให้โดยโรงเรียน เพื่อความปลอดภัยของข้อมูลนักเรียน กรุณาตั้งรหัสผ่านของคุณเองก่อนใช้งาน</p>
<form method="POST" action="{{ route('password.change.save') }}">
    @csrf
    @include('auth._password-fields')
    <button class="btn btn-primary btn-lg w-100">บันทึกรหัสผ่านใหม่</button>
</form>
<form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">@csrf<button class="btn btn-link text-muted small">ออกจากระบบ</button></form>
@endsection
