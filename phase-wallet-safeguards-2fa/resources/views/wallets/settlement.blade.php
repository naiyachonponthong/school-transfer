@extends('layouts.app')
@section('title', 'ใบจ่ายเงินร้านค้า '.$settlement->doc_no)

@section('content')
<div class="d-flex flex-wrap gap-2 mb-3 no-print">
    @if ($canManage)
        <a href="{{ route('wallets.settlements') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> จ่ายเงินร้านค้า</a>
    @else
        <a href="{{ route('pos.show', $settlement->shop) }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> หน้าจอขาย</a>
    @endif
    <span class="badge text-bg-{{ $settlement->isVoided() ? 'secondary' : 'success' }} align-self-center fs-6">{{ $settlement->isVoided() ? 'ยกเลิกแล้ว' : 'จ่ายแล้ว' }}</span>
    <button onclick="print()" class="btn btn-light border ms-auto"><i class="bi bi-printer"></i> พิมพ์</button>
    @if ($canManage && ! $settlement->isVoided())
        <button class="btn btn-light border text-danger" data-bs-toggle="modal" data-bs-target="#voidSettlement">ยกเลิกใบนี้</button>
    @endif
</div>

<div class="card doc-page" style="max-width:820px;margin:auto"><div class="card-body p-4">
    <div class="d-flex justify-content-between small"><span>{{ school('school_name') }}</span><span>เลขที่ {{ $settlement->doc_no }}</span></div>
    <div class="text-center mb-3">
        <h2 class="h6 fw-bold mb-0">ใบจ่ายเงินยอดขายร้านค้า</h2>
        @if ($settlement->isVoided())<div class="fw-bold text-danger border border-danger px-2 d-inline-block mt-1">ยกเลิก · {{ thai_date($settlement->voided_at) }} · {{ $settlement->void_reason }}</div>@endif
    </div>
    <div class="row small mb-3">
        <div class="col-7">
            <div>จ่ายให้ร้าน <b>{{ $settlement->shop->name }}</b>{{ $settlement->shop->location ? ' ('.$settlement->shop->location.')' : '' }}</div>
            @if ($settlement->shop->payout_account)<div>ช่องทางรับเงิน {{ $settlement->shop->payout_account }}</div>@endif
            <div>ยอดขายงวดวันที่ {{ $settlement->periodLabel() }}</div>
        </div>
        <div class="col-5 text-end">
            <div>วันที่จ่าย {{ thai_datetime($settlement->paid_at) }}</div>
            <div>วิธีจ่าย {{ $settlement->methodLabel() }}</div>
            @if ($settlement->note)<div>{{ $settlement->note }}</div>@endif
        </div>
    </div>

    <table class="table table-bordered table-sm small">
        <thead class="table-light text-center"><tr><th style="width:50px">ที่</th><th>วันที่ขาย</th><th style="width:140px">จำนวนรายการ</th><th style="width:160px">ยอดขาย (บาท)</th></tr></thead>
        <tbody>
        @forelse ($days as $i => $day)
            <tr><td class="text-center">{{ $i + 1 }}</td><td>{{ \App\Support\Thai::fullDate($day['date']) }}</td><td class="text-center">{{ number_format($day['count']) }}</td><td class="text-end">{{ baht($day['total']) }}</td></tr>
        @empty
            <tr><td colspan="4" class="text-center text-muted">ใบนี้ถูกยกเลิก รายการขายกลับไปเป็นยอดค้างจ่ายของร้านแล้ว</td></tr>
        @endforelse
        </tbody>
        <tfoot>
            <tr><td colspan="2" class="text-end">รวมยอดขาย</td><td class="text-center">{{ number_format($settlement->sales_count) }}</td><td class="text-end">{{ baht($settlement->gross) }}</td></tr>
            @if ((float) $settlement->fee > 0)
                <tr><td colspan="3" class="text-end">หักส่วนแบ่งของโรงเรียน {{ \App\Models\Shop::percentLabel($settlement->fee_percent) }}</td><td class="text-end">−{{ baht($settlement->fee) }}</td></tr>
            @endif
            <tr class="fw-bold"><td colspan="3" class="text-end">จ่ายให้ร้าน</td><td class="text-end">{{ baht($settlement->net) }}</td></tr>
            <tr><td colspan="4" class="text-center">( {{ \App\Support\Thai::bahtText((float) $settlement->net) }} )</td></tr>
        </tfoot>
    </table>

    <div class="row g-3 mt-4">
        <x-sign class="col-6" role="ผู้จ่ายเงิน (ฝ่ายการเงิน)" :name="$settlement->payer?->name" />
        <x-sign class="col-6" role="ผู้รับเงิน (ร้านค้า)" />
    </div>
</div></div>

@if ($canManage && ! $settlement->isVoided())
    <div class="modal fade" id="voidSettlement" tabindex="-1">
        <div class="modal-dialog"><form method="POST" action="{{ route('wallets.settlements.void', $settlement) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">ยกเลิกใบจ่ายเงิน {{ $settlement->doc_no }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body">
                <div class="small text-muted mb-2">ยอดขาย {{ baht($settlement->gross) }} บาทของใบนี้จะกลับไปเป็นยอดค้างจ่ายของร้าน เลขที่ใบเก็บไว้เป็นหลักฐาน ถ้าจ่ายเงินให้ร้านไปแล้วต้องเรียกเงินคืนหรือออกใบใหม่ให้ตรงกับที่จ่ายจริง</div>
                <label class="form-label">เหตุผล</label><input name="reason" class="form-control" maxlength="200" required>
            </div>
            <div class="modal-footer"><button class="btn btn-danger">ยกเลิกใบจ่ายเงิน</button></div>
        </form></div>
    </div>
@endif
@endsection
