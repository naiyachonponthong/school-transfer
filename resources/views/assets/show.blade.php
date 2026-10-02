@extends('layouts.app')
@section('title', $asset->code.' '.$asset->name)

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $asset->name }} <span class="badge text-bg-{{ $asset->statusColor() }} align-middle">{{ $asset->statusLabel() }}</span></h1>
        <div class="sub">{{ $asset->code }} · {{ $asset->location ?: 'ไม่ระบุสถานที่' }}</div>
    </div>
    <div class="actions">
        <a href="{{ route('repairs.create', ['asset' => $asset->id]) }}" class="btn btn-primary"><i class="bi bi-tools"></i> แจ้งซ่อม</a>
        @if ($manager)
            <a href="{{ route('assets.labels', ['ids' => $asset->id]) }}" class="btn btn-light border"><i class="bi bi-qr-code"></i> สติกเกอร์</a>
            <a href="{{ route('assets.edit', $asset) }}" class="btn btn-light border"><i class="bi bi-pencil"></i> แก้ไข</a>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3"><div class="card-body">
            <div class="d-flex gap-3">
                @if ($asset->photo)<img src="{{ asset('storage/'.$asset->photo) }}" alt="" class="rounded" style="width:140px;height:140px;object-fit:cover">@endif
                <dl class="row mb-0 small flex-grow-1">
                    <dt class="col-5 fw-normal text-muted">ประเภท</dt><dd class="col-7">{{ $asset->category ?: '-' }}</dd>
                    <dt class="col-5 fw-normal text-muted">ยี่ห้อ / รุ่น</dt><dd class="col-7">{{ $asset->brand ?: '-' }}</dd>
                    <dt class="col-5 fw-normal text-muted">หมายเลขเครื่อง</dt><dd class="col-7">{{ $asset->serial_no ?: '-' }}</dd>
                    <dt class="col-5 fw-normal text-muted">ผู้รับผิดชอบ</dt><dd class="col-7">{{ $asset->responsible?->name ?? '-' }}</dd>
                    @if ($manager)
                        <dt class="col-5 fw-normal text-muted">วันที่ได้มา</dt><dd class="col-7">{{ $asset->acquired_on ? thai_date($asset->acquired_on, true) : '-' }}</dd>
                        <dt class="col-5 fw-normal text-muted">แหล่งงบ</dt><dd class="col-7">{{ $asset->budget_source ?: '-' }}</dd>
                        <dt class="col-5 fw-normal text-muted">ราคา</dt><dd class="col-7">{{ number_format($asset->price, 2) }} บาท</dd>
                        <dt class="col-5 fw-normal text-muted">อายุการใช้งาน</dt><dd class="col-7">{{ $asset->life() ? $asset->life().' ปี' : '-' }}</dd>
                        <dt class="col-5 fw-normal text-muted">ค่าเสื่อมสะสม</dt><dd class="col-7">{{ $asset->accumulatedDepreciation() !== null ? number_format($asset->accumulatedDepreciation(), 2).' บาท' : '-' }}</dd>
                        <dt class="col-5 fw-normal text-muted">มูลค่าสุทธิ</dt><dd class="col-7 fw-semibold">{{ $asset->bookValue() !== null ? number_format($asset->bookValue(), 2).' บาท' : '-' }}</dd>
                        @if ($asset->disposed_on)<dt class="col-5 fw-normal text-muted">วันที่จำหน่าย</dt><dd class="col-7">{{ thai_date($asset->disposed_on, true) }}</dd>@endif
                    @endif
                    @if ($asset->note)<dt class="col-5 fw-normal text-muted">หมายเหตุ</dt><dd class="col-7">{{ $asset->note }}</dd>@endif
                </dl>
            </div>
        </div></div>

        <div class="card">
            <div class="card-header"><i class="bi bi-tools"></i> ประวัติการซ่อม ({{ $asset->repairs->count() }} ครั้ง · รวม {{ number_format($asset->repairs->sum('cost'), 2) }} บาท)</div>
            <div class="list-group list-group-flush">
                @forelse ($asset->repairs as $r)
                    <a href="{{ route('repairs.show', $r) }}" class="list-group-item list-group-item-action small d-flex gap-2">
                        <span class="badge text-bg-{{ $r->statusColor() }} align-self-start">{{ $r->statusLabel() }}</span>
                        <span class="flex-grow-1">{{ $r->ticket_no }} · {{ $r->title }}<div class="text-muted">{{ thai_date($r->created_at) }} · แจ้งโดย {{ $r->reporter?->name ?? '-' }}</div></span>
                        @if ($r->cost)<span class="text-nowrap">{{ number_format($r->cost, 2) }} บ.</span>@endif
                    </a>
                @empty
                    <div class="list-group-item small text-muted">ยังไม่เคยแจ้งซ่อม</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        @if ($manager)
            <div class="card mb-3"><div class="card-body text-center">
                <div data-qr="{{ $asset->qrUrl() }}" data-cell="4" class="mx-auto" style="width:150px"></div>
                <div class="small text-muted mt-1">สแกนด้วยกล้องมือถือ → เปิดหน้านี้ / แจ้งซ่อม</div>
            </div></div>
            <div class="card">
                <div class="card-header"><i class="bi bi-clipboard-check"></i> ผลตรวจสอบพัสดุประจำปี</div>
                <div class="list-group list-group-flush">
                    @forelse ($asset->checks as $c)
                        <div class="list-group-item small d-flex justify-content-between"><span>ปี {{ $c->year }}</span><span class="badge text-bg-{{ \App\Models\AssetCheck::RESULTS[$c->result][1] }}">{{ \App\Models\AssetCheck::RESULTS[$c->result][0] }}</span></div>
                    @empty
                        <div class="list-group-item small text-muted">ยังไม่เคยตรวจสอบ</div>
                    @endforelse
                </div>
            </div>
            @if ($asset->repairs->isEmpty() && $asset->checks->isEmpty())
                <form method="POST" action="{{ route('assets.destroy', $asset) }}" class="mt-3 text-end" data-confirm="ลบครุภัณฑ์นี้? (ใช้เมื่อบันทึกผิดเท่านั้น ถ้าเลิกใช้ให้เปลี่ยนสถานะเป็นจำหน่าย)">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger">ลบ (บันทึกผิด)</button></form>
            @endif
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
@endpush
