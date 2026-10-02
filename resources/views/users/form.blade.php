@extends('layouts.app')
@section('title', $user->exists ? 'แก้ไขผู้ใช้' : 'เพิ่มผู้ใช้')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="page-head"><h1>{{ $user->exists ? 'แก้ไข: '.$user->name : 'เพิ่มผู้ใช้' }}</h1></div>
        <form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="card">
            @csrf @if ($user->exists) @method('PUT') @endif
            <div class="card-body row g-3">
                <div class="col-12">
                    <label class="form-label">บทบาท</label>
                    <div class="btn-group w-100">
                        @foreach (\App\Models\User::ROLES as $k => $v)
                            <input type="radio" class="btn-check" name="role" value="{{ $k }}" id="r{{ $k }}" @checked(old('role', $user->role) === $k)>
                            <label class="btn btn-outline-primary" for="r{{ $k }}">{{ $v }}</label>
                        @endforeach
                    </div>
                </div>
                <div class="col-md-7"><label class="form-label">ชื่อ-สกุล</label><input name="name" value="{{ old('name', $user->name) }}" class="form-control" required></div>
                <div class="col-md-5"><label class="form-label">ตำแหน่ง</label><input name="position" value="{{ old('position', $user->position) }}" class="form-control" placeholder="เช่น ครูชำนาญการ"></div>
                <div class="col-md-6"><label class="form-label">ชื่อผู้ใช้ (ใช้เข้าระบบ)</label><input name="username" value="{{ old('username', $user->username) }}" class="form-control" required pattern="[A-Za-z0-9_\-]+" title="ภาษาอังกฤษ ตัวเลข _ -"></div>
                <div class="col-md-6"><label class="form-label">เบอร์โทร (เข้าระบบด้วยเบอร์ได้)</label><input name="phone" value="{{ old('phone', $user->phone) }}" class="form-control" inputmode="tel"></div>
                <div class="col-md-6"><label class="form-label">อีเมล</label><input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control"></div>
                <div class="col-md-6">
                    <label class="form-label">รหัสผ่าน {{ $user->exists ? '(เว้นว่าง = ไม่เปลี่ยน)' : '(เว้นว่าง = สุ่มให้)' }}</label>
                    <input type="text" name="password" class="form-control" autocomplete="new-password" minlength="6">
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="active" @checked(old('is_active', $user->is_active))>
                        <label class="form-check-label" for="active">เปิดใช้งาน</label>
                    </div>
                </div>
                @if ($user->exists && $user->isParent() && $user->children->isNotEmpty())
                    <div class="col-12">
                        <label class="form-label">บุตรหลาน</label>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($user->children as $c)<a href="{{ route('students.show', $c) }}" class="btn btn-sm btn-light border">{{ $c->fullName() }} ({{ $c->classroom?->name() }})</a>@endforeach
                        </div>
                    </div>
                @endif
            </div>
            <div class="card-footer bg-transparent d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-save"></i> บันทึก</button>
                <a href="{{ route('users.index', ['role' => $user->role]) }}" class="btn btn-light border">ยกเลิก</a>
            </div>
        </form>
        @if ($user->exists && $user->id !== auth()->id())
            <form method="POST" action="{{ route('users.destroy', $user) }}" data-confirm="ลบบัญชี {{ $user->name }}? (แนะนำให้ปิดใช้งานแทน)" class="text-end mt-2">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger">ลบบัญชี</button></form>
        @endif
    </div>
</div>
@endsection
