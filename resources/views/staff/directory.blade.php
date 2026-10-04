@extends('layouts.app')
@section('title', 'ทะเบียนติดต่อ')

@section('content')
<div class="page-head">
    <div><h1>ทะเบียนติดต่อ</h1><div class="sub">ครูและบุคลากร {{ $staff->count() }} คน{{ $dept ? ' · '.$dept->name : '' }}{{ $q !== '' ? ' · ค้นหา "'.$q.'"' : '' }}</div></div>
    <div class="actions"><a href="{{ route('org.index') }}" class="btn btn-light border"><i class="bi bi-diagram-3"></i> โครงสร้างองค์กร</a></div>
</div>

<form method="GET" class="card mb-3"><div class="card-body d-flex flex-wrap gap-2">
    <input name="q" value="{{ $q }}" class="form-control" style="max-width:280px" placeholder="ค้นชื่อ ตำแหน่ง รหัส หรือเบอร์โทร" aria-label="ค้นหาบุคลากร">
    <select name="department" class="form-select" style="max-width:280px" aria-label="หน่วยงาน">
        <option value="">ทุกหน่วยงาน</option>
        @foreach ($departments as $row)
            <option value="{{ $row['dept']->id }}" @selected($dept?->id === $row['dept']->id)>{{ str_repeat('— ', $row['depth']) }}{{ $row['dept']->name }}</option>
        @endforeach
    </select>
    <button class="btn btn-primary" aria-label="ค้นหา"><i class="bi bi-search"></i></button>
    @if ($q !== '' || $dept)<a href="{{ route('staff.directory') }}" class="btn btn-light border">ล้าง</a>@endif
</div></form>

<div class="row g-3">
    @forelse ($staff as $u)
        @php
            $primary = $u->departments->firstWhere('pivot.is_primary', true) ?? $u->departments->first();
        @endphp
        <div class="col-sm-6 col-lg-4 col-xxl-3"><div class="card h-100"><div class="card-body d-flex gap-3">
            @if ($u->avatarUrl())
                <img src="{{ $u->avatarUrl() }}" alt="" class="rounded-3 flex-shrink-0" style="width:52px;height:52px;object-fit:cover">
            @else
                <div class="rounded-3 flex-shrink-0 d-flex align-items-center justify-content-center fw-semibold tint-primary" style="width:52px;height:52px" aria-hidden="true">{{ $u->initials() }}</div>
            @endif
            <div class="min-w-0">
                <div class="fw-semibold text-truncate">{{ $u->name }}</div>
                <div class="small text-muted text-truncate">{{ $u->position ?: $u->roleLabel() }}</div>
                <div class="small text-muted text-truncate">{{ $primary?->name ?? 'ยังไม่ได้สังกัดหน่วยงาน' }}@if ($u->departments->count() > 1) <span title="{{ $u->departments->pluck('name')->implode(' · ') }}">+{{ $u->departments->count() - 1 }}</span>@endif</div>
                @if ($u->phone && ! $u->hide_phone)
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $u->phone) }}" class="small d-inline-block mt-1"><i class="bi bi-telephone"></i> {{ $u->phone }}</a>
                @endif
                @if ($u->email)
                    <a href="mailto:{{ $u->email }}" class="small d-block text-truncate"><i class="bi bi-envelope"></i> {{ $u->email }}</a>
                @endif
            </div>
        </div></div></div>
    @empty
        <div class="col-12"><div class="card"><div class="card-body"><div class="empty"><i class="bi bi-person-lines-fill"></i>ไม่พบบุคลากรตามที่ค้นหา</div></div></div></div>
    @endforelse
</div>
@endsection
