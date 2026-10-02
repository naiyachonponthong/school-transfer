@extends('layouts.app')
@section('title', 'คู่มือการใช้งาน')

@php
    use App\Support\Manual;
    $hl = fn (string $s) => preg_replace('/('.preg_quote(e($q), '/').')/iu', '<mark>$1</mark>', e($s));
@endphp

@section('content')
<x-page-banner title="คู่มือการใช้งาน" :subtitle="'อธิบายทีละขั้นตอนทุกเมนู · '.count($topics).' หัวข้อสำหรับ'.Manual::ROLE_LABELS[$role]" eyebrow="HELP CENTER">
    <a href="{{ route('manual.print') }}" target="_blank" class="btn btn-light"><i class="bi bi-printer"></i> พิมพ์คู่มือทั้งเล่ม</a>
</x-page-banner>

<form method="GET" class="card mb-3">
    <div class="card-body">
        <div class="input-group input-group-lg">
            <span class="input-group-text bg-transparent"><i class="bi bi-search"></i></span>
            <input name="q" value="{{ $q }}" class="form-control" placeholder="ค้นหาในคู่มือ เช่น ลืมรหัสผ่าน, ตัดเกรด, มส, พิมพ์ป้ายสัน, สลิป" autofocus>
            <button class="btn btn-primary px-4">ค้นหา</button>
        </div>
        <div class="small text-muted mt-2"><i class="bi bi-lightbulb"></i> ทุกหน้าในระบบมีปุ่ม <i class="bi bi-question-circle"></i> มุมขวาบน กดแล้วเปิดคู่มือของหน้านั้นทันที</div>
    </div>
</form>

@if ($q !== '')
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-search"></i> ผลการค้นหา "{{ $q }}" <span class="small text-muted fw-normal ms-1">{{ count($results) }} หัวข้อ</span>
            <a href="{{ route('manual.index') }}" class="small ms-auto">ล้างการค้นหา</a></div>
        <div class="list-group list-group-flush">
            @forelse ($results as $key => $r)
                <a href="{{ route('manual.show', $key) }}" class="list-group-item list-group-item-action manual-hit">
                    <div class="fw-bold"><i class="bi {{ $topics[$key][1] }} text-primary"></i> {!! $hl($topics[$key][0]) !!}</div>
                    @foreach ($r['hits'] as $h)<div class="small text-muted">{!! $hl($h) !!}</div>@endforeach
                </a>
            @empty
                <div class="list-group-item text-muted">ไม่พบ — ลองคำอื่น หรือดู <a href="{{ route('manual.show', 'faq') }}">คำถามที่พบบ่อย</a></div>
            @endforelse
        </div>
    </div>
@endif

@if ($startHere)
    <h2 class="h6 fw-bold text-muted mb-2"><i class="bi bi-flag"></i> แนะนำให้อ่านก่อน</h2>
    <div class="row g-2 mb-4">
        @foreach ($startHere as $i => $k)
            <div class="col-md-6 col-xl-3">
                <a href="{{ route('manual.show', $k) }}" class="manual-card" style="{{ $i === 0 ? 'border-color:var(--sb-primary);background:var(--sb-primary-100)' : '' }}">
                    <span class="mi"><i class="bi {{ $topics[$k][1] }}"></i></span>
                    <span><span class="mt d-block">{{ $topics[$k][0] }}</span><span class="ms d-block">{{ $topics[$k][4] }}</span></span>
                </a>
            </div>
        @endforeach
    </div>
@endif

@foreach ($groups as $group => $items)
    <h2 class="h6 fw-bold text-muted mb-2 mt-3">{{ $group }}</h2>
    <div class="row g-2 mb-3">
        @foreach ($items as $k => $t)
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('manual.show', $k) }}" class="manual-card">
                    <span class="mi"><i class="bi {{ $t[1] }}"></i></span>
                    <span><span class="mt d-block">{{ $t[0] }}</span><span class="ms d-block">{{ $t[4] }}</span></span>
                </a>
            </div>
        @endforeach
    </div>
@endforeach
@endsection
