@extends('layouts.app')
@section('title', 'บัตรนักเรียน')

@push('head')
<style>
    .id-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 12px; }
    .id-card { width: 100%; max-width: 340px; aspect-ratio: 85.6 / 54; border-radius: 14px; overflow: hidden; background: #fff; box-shadow: var(--sb-shadow); display: flex; flex-direction: column; break-inside: avoid; border: 1px solid var(--sb-border); }
    .id-card .top { background: linear-gradient(135deg, var(--sb-grad-from), var(--sb-grad-to)); color: #fff; padding: 6px 10px; display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 600; }
    .id-card .top img { width: 20px; height: 20px; border-radius: 5px; background: #fff; }
    .id-card .body { flex: 1; display: flex; gap: 10px; padding: 8px 10px; align-items: center; }
    .id-card .ph { width: 64px; height: 80px; border-radius: 8px; background: var(--sb-primary-50); display: grid; place-items: center; font-size: 1.6rem; color: var(--sb-primary-700); font-weight: 700; overflow: hidden; flex-shrink: 0; }
    .id-card .ph img { width: 100%; height: 100%; object-fit: cover; }
    .id-card .info { flex: 1; min-width: 0; font-size: 11px; line-height: 1.45; }
    .id-card .info b { font-size: 13px; display: block; }
    .id-card .qr svg { width: 84px; height: 84px; display: block; }
    @media print {
        .id-cards { grid-template-columns: repeat(2, 85.6mm); gap: 4mm; }
        .id-card { width: 85.6mm; height: 54mm; box-shadow: none; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
@endpush

@section('content')
<div class="page-head no-print">
    <div><h1>บัตรนักเรียน (QR)</h1><div class="sub">ใช้สแกนหน้าประตู ยืมหนังสือห้องสมุด และห้องพยาบาล</div></div>
    <div class="actions">
        <form method="GET"><select name="classroom" class="form-select" data-autosubmit>
            @foreach ($classrooms as $c)<option value="{{ $c->id }}" @selected($classroom?->id === $c->id)>{{ $c->name() }}</option>@endforeach
        </select></form>
        <button class="btn btn-primary" onclick="print()"><i class="bi bi-printer"></i> พิมพ์บัตร</button>
    </div>
</div>
<div class="alert alert-info small no-print"><i class="bi bi-info-circle"></i> ขนาดเท่าบัตรเครดิต (85.6 × 54 มม.) พิมพ์ลงกระดาษการ์ดแล้วเคลือบ · QR ใช้รหัสสุ่มเฉพาะตัว ปลอมจากรหัสนักเรียนไม่ได้</div>

<div class="id-cards">
    @foreach ($students as $s)
        <div class="id-card">
            <div class="top">
                @if (school('logo'))<img src="{{ asset('storage/'.school('logo')) }}" alt="">@else<i class="bi bi-mortarboard-fill"></i>@endif
                {{ school('school_name') }}
            </div>
            <div class="body">
                <div class="ph">@if($s->photoUrl())<img src="{{ $s->photoUrl() }}" alt="">@else{{ $s->initials() }}@endif</div>
                <div class="info">
                    <b>{{ $s->fullName() }}</b>
                    @if ($s->nickname)ชื่อเล่น {{ $s->nickname }}<br>@endif
                    รหัส {{ $s->student_code }}<br>
                    ชั้น {{ $s->classroom?->name() }} เลขที่ {{ $s->number }}<br>
                    @if ($s->blood_type)กรุ๊ปเลือด {{ $s->blood_type }}@endif
                </div>
                <div class="qr" data-qr="{{ $s->qr_token }}"></div>
            </div>
        </div>
    @endforeach
</div>
@if ($students->isEmpty())<div class="card"><div class="empty"><i class="bi bi-people"></i>ห้องนี้ยังไม่มีนักเรียน</div></div>@endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
@endpush
