@extends('layouts.app')
@section('title', 'สติกเกอร์ QR ครุภัณฑ์')

@push('head')
<style>
    .asset-labels { display: grid; grid-template-columns: repeat(auto-fill, 62mm); gap: 3mm; }
    .asset-label { width: 62mm; height: 29mm; border: 1px dashed #bbb; border-radius: 2mm; padding: 1.5mm 2mm; display: flex; gap: 2mm; align-items: center; overflow: hidden; break-inside: avoid; background: #fff; }
    .asset-label .qr { width: 24mm; flex-shrink: 0; }
    .asset-label .qr svg { width: 24mm; height: 24mm; display: block; }
    .asset-label .t { font-size: 8.5pt; line-height: 1.25; min-width: 0; }
    .asset-label .code { font-weight: 700; font-size: 10pt; }
    @media print { .asset-label { border-color: #ddd; } }
</style>
@endpush

@section('content')
<div class="page-head no-print">
    <div><h1>สติกเกอร์ QR ครุภัณฑ์</h1><div class="sub">{{ $assets->count() }} ดวง · ขนาด 62 × 29 มม. พิมพ์บนกระดาษสติกเกอร์ A4 แล้วตัดตามเส้น</div></div>
    <div class="actions"><a href="{{ route('assets.index') }}" class="btn btn-light border">กลับ</a><button onclick="print()" class="btn btn-primary"><i class="bi bi-printer"></i> พิมพ์</button></div>
</div>
<div class="asset-labels">
    @forelse ($assets as $a)
        <div class="asset-label">
            <div class="qr" data-qr="{{ $a->qrUrl() }}" data-cell="2"></div>
            <div class="t">
                <div class="code">{{ $a->code }}</div>
                <div>{{ \Illuminate\Support\Str::limit($a->name, 40) }}</div>
                <div class="text-muted">{{ school('school_short') ?: school('school_name') }}</div>
                <div class="text-muted">สแกนเพื่อแจ้งซ่อม</div>
            </div>
        </div>
    @empty
        <div class="text-muted">ไม่มีครุภัณฑ์ตามที่เลือก</div>
    @endforelse
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
@endpush
