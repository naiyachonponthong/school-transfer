@extends('layouts.app')
@section('title', 'คู่มือการใช้งาน (ฉบับพิมพ์)')

@php use App\Support\Manual; @endphp

@section('content')
@php(view()->share('manualPrint', true)) {{-- ไม่ต้องมีปุ่มเปิดหน้าในระบบในฉบับพิมพ์ --}}
<style>@page { size: A4; margin: 15mm 15mm 18mm; }</style>
<div class="d-flex gap-2 mb-3 no-print">
    <a href="{{ route('manual.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
    <div class="align-self-center small text-muted">{{ count($topics) }} หัวข้อ · ตั้งเครื่องพิมพ์ A4 · หรือเลือก "บันทึกเป็น PDF"</div>
    <button onclick="print()" class="btn btn-primary ms-auto"><i class="bi bi-printer"></i> พิมพ์ / บันทึก PDF</button>
</div>

<div class="card" style="max-width:900px;margin:auto"><div class="card-body p-4">
    @unless ($single)
        <div class="text-center py-5">
            @if (school('logo'))<img src="{{ asset('storage/'.school('logo')) }}" alt="" style="height:90px" class="mb-3">@endif
            <div class="display-6 fw-bold">คู่มือการใช้งาน</div>
            <div class="fs-4">ระบบบริหารโรงเรียน</div>
            <div class="fs-5 mt-2">{{ school('school_name') }}</div>
            <div class="text-muted mt-3">สำหรับ{{ Manual::ROLE_LABELS[Manual::role(auth()->user())] }} · พิมพ์เมื่อ {{ thai_date(today()) }}</div>
        </div>
        <div class="manual-topic-print">
            <h2 class="h4 fw-bold mb-3">สารบัญ</h2>
            <ol class="row" style="column-gap:0">
                @foreach ($topics as $k => $t)<li class="col-6 mb-1"><a href="#t-{{ $k }}" class="text-decoration-none text-body">{{ $t[0] }}</a></li>@endforeach
            </ol>
        </div>
    @endunless
    @foreach ($topics as $k => $t)
        <div class="manual-topic-print" id="t-{{ $k }}">
            <h1 class="h3 fw-bold mt-3"><i class="bi {{ $t[1] }}"></i> {{ $t[0] }}</h1>
            <p class="text-muted">{{ $t[4] }}</p>
            <article class="manual-body">@include('manual.topics.'.$k)</article>
        </div>
    @endforeach
</div></div>
@php(view()->share('manualPrint', false))
@endsection

@push('scripts')
<script>document.querySelectorAll('.manual-faq').forEach((d) => { d.open = true; });</script>
@endpush
