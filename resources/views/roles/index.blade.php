@extends('layouts.app')
@section('title', 'ตำแหน่งงานและสิทธิ์')

@section('content')
<div class="page-head">
    <div><h1>ตำแหน่งงานและสิทธิ์</h1><div class="sub">บุคลากรหนึ่งคนถือได้หลายตำแหน่ง (กำหนดที่หน้าผู้ใช้งาน) · ผู้ดูแลระบบได้ทุกสิทธิ์เสมอ · บุคลากรที่ยังไม่กำหนดตำแหน่งใช้สิทธิ์ของ "ครู"</div></div>
    <div class="actions">
        <a class="btn btn-light border" href="{{ route('users.index') }}"><i class="bi bi-person-gear"></i> ผู้ใช้งาน</a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRole"><i class="bi bi-plus-lg"></i> เพิ่มตำแหน่ง</button>
    </div>
</div>

<form method="POST" action="{{ route('roles.update') }}" id="matrix">@csrf @method('PUT')</form>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th style="min-width:260px">สิทธิ์</th>
                    @foreach ($roles as $role)
                        <th class="text-center text-nowrap">
                            {{ $role->name }}
                            <div class="fw-normal small text-muted">{{ $role->users_count }} คน</div>
                            @if (! $role->is_system && $role->users_count === 0)
                                <form method="POST" action="{{ route('roles.destroy', $role) }}" data-confirm="ลบตำแหน่ง {{ $role->name }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger p-0">ลบ</button></form>
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($groups as $group => $permissions)
                    <tr class="table-light"><td colspan="{{ $roles->count() + 1 }}" class="fw-semibold small">{{ $group }}</td></tr>
                    @foreach ($permissions as $key => $label)
                        <tr>
                            <td class="small">{{ $label }}</td>
                            @foreach ($roles as $role)
                                <td class="text-center">
                                    <input form="matrix" type="checkbox" class="form-check-input" name="permissions[{{ $role->id }}][]" value="{{ $key }}"
                                        aria-label="{{ $role->name }}: {{ $label }}" @checked(in_array($key, $role->permissions ?? [], true))>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent text-end"><button form="matrix" class="btn btn-primary"><i class="bi bi-check-lg"></i> บันทึกสิทธิ์</button></div>
</div>

<div class="modal fade" id="addRole" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="{{ route('roles.store') }}" class="modal-content">
        @csrf
        <div class="modal-header"><h5 class="modal-title">เพิ่มตำแหน่ง</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><label class="form-label">ชื่อตำแหน่ง</label><input name="name" class="form-control" placeholder="เช่น หัวหน้ากลุ่มสาระ" required></div>
        <div class="modal-footer"><button class="btn btn-primary">เพิ่ม</button></div>
    </form></div>
</div>
@endsection
