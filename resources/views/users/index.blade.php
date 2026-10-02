@extends('layouts.app')
@section('title', 'ผู้ใช้งาน')

@section('content')
<div class="page-head">
    <div><h1>ผู้ใช้งาน</h1><div class="sub">ครู บุคลากร และผู้ปกครอง</div></div>
    <div class="actions"><a href="{{ route('users.create', ['role' => $role === 'all' ? 'teacher' : $role]) }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> เพิ่มผู้ใช้</a></div>
</div>

<ul class="nav nav-pills mb-3 gap-1">
    @foreach (\App\Models\User::ROLES + ['all' => 'ทั้งหมด'] as $k => $v)
        <li class="nav-item"><a href="{{ route('users.index', ['role' => $k]) }}" class="nav-link {{ $role === $k ? 'active' : '' }}">{{ $v }} <span class="badge bg-light text-dark ms-1">{{ $k === 'all' ? $counts->sum() : ($counts[$k] ?? 0) }}</span></a></li>
    @endforeach
</ul>

<form class="mb-3" method="GET">
    <input type="hidden" name="role" value="{{ $role }}">
    <div class="input-group" style="max-width:360px">
        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
        <input name="q" value="{{ request('q') }}" class="form-control" placeholder="ชื่อ ชื่อผู้ใช้ เบอร์โทร">
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-cards table-hover align-middle">
            <thead><tr><th>ชื่อ</th><th>ชื่อผู้ใช้</th><th>เบอร์โทร</th><th>บทบาท</th><th>ข้อมูล</th><th>เข้าระบบล่าสุด</th><th></th></tr></thead>
            <tbody>
            @forelse ($users as $u)
                <tr class="{{ $u->is_active ? '' : 'opacity-50' }}">
                    <td class="tc-title"><div class="d-flex align-items-center gap-2"><span class="sb-avatar sm">{{ $u->initials() }}</span><div><div class="fw-semibold">{{ $u->name }}</div><div class="small text-muted">{{ $u->position }}</div></div></div></td>
                    <td class="small">{{ $u->username }}</td>
                    <td class="small">{{ $u->phone }}</td>
                    <td><span class="badge bg-{{ ['admin' => 'danger', 'teacher' => 'primary', 'parent' => 'success'][$u->role] ?? 'secondary' }}-subtle text-{{ ['admin' => 'danger', 'teacher' => 'primary', 'parent' => 'success'][$u->role] ?? 'secondary' }}-emphasis">{{ $u->roleLabel() }}</span> @unless($u->is_active)<span class="badge bg-secondary">ปิดใช้งาน</span>@endunless</td>
                    <td class="small text-muted">
                        @if ($u->isParent()) บุตรหลาน {{ $u->children_count }} คน
                        @else @if($u->homerooms_count) ประจำชั้น {{ $u->homerooms_count }} ห้อง · @endif สอน {{ $u->courses_count }} วิชา @endif
                    </td>
                    <td class="small text-muted">{{ $u->last_login_at ? \App\Support\Thai::ago($u->last_login_at) : 'ยังไม่เคย' }}</td>
                    <td class="text-end text-nowrap">
                        <form method="POST" action="{{ route('users.reset-password', $u) }}" class="d-inline" data-confirm="รีเซ็ตรหัสผ่านของ {{ $u->name }}?">@csrf<button class="btn btn-sm btn-light border" title="รีเซ็ตรหัสผ่าน"><i class="bi bi-key"></i></button></form>
                        <a href="{{ route('users.edit', $u) }}" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty"><i class="bi bi-people"></i>ไม่พบผู้ใช้</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $users->links() }}</div>
@endsection
