@extends('layouts.app')
@section('title', 'ทะเบียนบุคลากร')

@section('content')
@php($expiring = $profiles->filter->licenseExpiring())
<div class="page-head">
    <div><h1>ทะเบียนบุคลากร</h1><div class="sub">{{ $staff->count() }} คน · กรอกประวัติแล้ว {{ $profiles->count() }} · ใบอนุญาตใกล้หมดอายุ/หมดแล้ว {{ $expiring->count() }}</div></div>
    <div class="actions">
        <a href="{{ route('staff.directory') }}" class="btn btn-light border"><i class="bi bi-person-lines-fill"></i> ทะเบียนติดต่อ</a>
        <a href="{{ route('org.index') }}" class="btn btn-light border"><i class="bi bi-diagram-3"></i> โครงสร้างองค์กร</a>
        <form method="POST" action="{{ route('org.codes') }}" data-confirm="ออกรหัสบุคลากรให้คนที่ยังไม่มีรหัส?">@csrf<button class="btn btn-light border"><i class="bi bi-123"></i> ออกรหัสอัตโนมัติ</button></form>
        <form method="GET"><input type="number" name="year" value="{{ $year }}" min="2000" max="2100" class="form-control" style="width:110px" data-autosubmit aria-label="ปี ค.ศ. ของชั่วโมงอบรม" title="ปี ค.ศ. ของชั่วโมงอบรม"></form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>รหัส</th><th>ชื่อ</th><th>ตำแหน่ง / วิทยฐานะ</th><th>หน่วยงาน</th><th>อายุงาน</th><th>ใบอนุญาตประกอบวิชาชีพ</th><th class="text-end">อบรมปี {{ $year }}</th></tr></thead>
            <tbody>
            @foreach ($staff as $u)
                @php($p = $profiles[$u->id] ?? null)
                <tr>
                    <td class="small font-monospace">{{ $u->staff_code ?: '-' }}</td>
                    <td><a href="{{ route('staff.show', $u) }}" class="fw-semibold">{{ $u->name }}</a></td>
                    <td class="small">{{ $u->position ?: '-' }}{{ $p?->rank ? ' · '.$p->rank : '' }}</td>
                    <td class="small">{{ ($u->departments->firstWhere('pivot.is_primary', true) ?? $u->departments->first())?->name ?? '-' }}{{ $u->departments->count() > 1 ? ' +'.($u->departments->count() - 1) : '' }}</td>
                    <td class="small">{{ $p && $p->yearsOfService() !== null ? $p->yearsOfService().' ปี' : '-' }}</td>
                    <td class="small">
                        @if ($p?->license_expires_on)
                            {{ $p->license_no ?: '' }} หมดอายุ {{ thai_date($p->license_expires_on) }}
                            @if ($p->licenseDaysLeft() < 0)<span class="badge bg-danger">หมดอายุแล้ว</span>
                            @elseif ($p->licenseExpiring())<span class="badge bg-warning text-dark">อีก {{ $p->licenseDaysLeft() }} วัน</span>@endif
                        @else
                            <span class="text-muted">ยังไม่ได้กรอก</span>
                        @endif
                    </td>
                    <td class="text-end">{{ rtrim(rtrim(number_format((float) ($hours[$u->id] ?? 0), 1), '0'), '.') }} ชม.</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
