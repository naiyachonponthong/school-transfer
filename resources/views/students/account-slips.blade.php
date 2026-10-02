@extends('layouts.app')
@section('title', 'ใบแจกรหัสผ่านนักเรียน')

@push('head')
<style>
    .slip-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 12px; }
    .slip { border: 1.5px dashed #c9ced6; border-radius: 14px; padding: 14px 16px; background: #fff; break-inside: avoid; page-break-inside: avoid; }
    .slip .who { font-weight: 700; }
    .slip .cred { display: grid; grid-template-columns: auto 1fr; gap: 2px 10px; margin-top: 8px; font-size: .95rem; }
    .slip .cred code { font-size: 1.05rem; color: #111; letter-spacing: .04em; }
    @media print {
        .slip-grid { grid-template-columns: repeat(2, 1fr); gap: 8px; }
        .sb-content { padding: 0 !important; }
    }
</style>
@endpush

@section('content')
<div class="page-head no-print">
    <div>
        <h1>ใบแจกรหัสผ่านนักเรียน</h1>
        <div class="sub">{{ count($creds) }} คน · รหัสผ่านแสดงครั้งเดียวเท่านั้น พิมพ์หรือจดไว้ก่อนออกจากหน้านี้</div>
    </div>
    <div class="actions">
        <a href="{{ route('students.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
        <button class="btn btn-primary" onclick="print()"><i class="bi bi-printer"></i> พิมพ์ใบแจก</button>
    </div>
</div>

<div class="alert alert-warning no-print"><i class="bi bi-shield-lock"></i> ระบบเก็บรหัสผ่านแบบเข้ารหัส ครูจึงดูย้อนหลังไม่ได้ ถ้านักเรียนลืมให้กด "รีเซ็ตรหัสผ่าน" ที่หน้าข้อมูลนักเรียน · นักเรียนควรเปลี่ยนรหัสผ่านเองหลังเข้าสู่ระบบครั้งแรก</div>

<div class="slip-grid">
    @foreach ($creds as $c)
        <div class="slip">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-mortarboard-fill text-primary"></i>
                <span class="small text-muted">{{ school('school_short') ?: school('school_name') }}</span>
                <span class="ms-auto badge bg-light text-dark border">{{ $c['classroom'] }}{{ $c['number'] ? ' · เลขที่ '.$c['number'] : '' }}</span>
            </div>
            <div class="who mt-2">{{ $c['name'] }}</div>
            <div class="cred">
                <span class="text-muted">เว็บไซต์</span><span class="small">{{ url('/') }}</span>
                <span class="text-muted">ชื่อผู้ใช้</span><code>{{ $c['username'] }}</code>
                <span class="text-muted">รหัสผ่าน</span><code>{{ $c['password'] }}</code>
            </div>
        </div>
    @endforeach
</div>
@endsection
