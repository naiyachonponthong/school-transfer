@extends('layouts.app')
@section('title', 'ยืนยันการจ่าย')

@section('content')
<div class="page-head">
    <div><h1>ยืนยันการจ่าย</h1><div class="sub">{{ $shop?->name ?? 'สแกนจ่ายจากกระเป๋าเงิน' }}</div></div>
</div>

<div style="max-width:560px;margin:0 auto">
@if (! $order)
    <div class="card"><div class="card-body"><div class="empty"><i class="bi bi-hourglass-bottom"></i>QR นี้หมดอายุหรือไม่ถูกต้อง<br><span class="small">ให้ร้านสร้าง QR ใหม่แล้วสแกนอีกครั้ง</span>
        <div class="mt-3"><a href="{{ route('wallet.scan') }}" class="btn btn-primary"><i class="bi bi-qr-code-scan"></i> สแกนใหม่</a></div>
    </div></div></div>
@elseif ($order['status'] === 'paid')
    <div class="card"><div class="card-body text-center py-4">
        <i class="bi bi-check-circle-fill text-success" style="font-size:4rem"></i>
        <div class="fs-4 fw-bold">ชำระแล้ว {{ baht($order['total']) }} บาท</div>
        <div class="text-muted">{{ $shop->name }} · {{ $order['customer']['name'] ?? '' }}</div>
        <div class="mt-3"><a href="{{ route('home') }}" class="btn btn-light border">กลับหน้าหลัก</a></div>
    </div></div>
@else
    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-shop"></i> {{ $shop->name }}</div>
        <div class="card-body">
            @foreach ($order['items'] as $item)
                <div class="d-flex gap-2 py-1 border-bottom"><div class="flex-grow-1">{{ $item['name'] }}@if ($item['qty'] > 1) <span class="text-muted">× {{ $item['qty'] }}</span>@endif</div><div class="fw-semibold">{{ baht($item['price'] * $item['qty']) }}</div></div>
            @endforeach
            <div class="d-flex align-items-baseline mt-3"><span class="fw-semibold">รวม</span><span class="ms-auto fw-bold" style="font-size:2.2rem;line-height:1">{{ baht($order['total']) }}</span><span class="ms-1">บาท</span></div>
        </div>
    </div>

    @if ($payers->isEmpty())
        <div class="card"><div class="card-body"><div class="empty"><i class="bi bi-wallet2"></i>บัญชีนี้ไม่มีกระเป๋าเงินที่ใช้จ่ายได้</div></div></div>
    @else
        <form method="POST" action="{{ route('wallet.pay.confirm', $token) }}" class="card">
            @csrf
            <div class="card-header"><i class="bi bi-wallet2"></i> จ่ายจากกระเป๋า</div>
            <div class="card-body">
                @foreach ($payers as $i => $p)
                    @php
                        $short = (float) $p['wallet']->balance < (float) $order['total'];
                    @endphp
                    <label class="d-flex align-items-center gap-2 border rounded-3 p-2 mb-2">
                        <input type="radio" class="form-check-input mt-0" name="payer" value="{{ $p['key'] }}" @checked($i === 0) required>
                        <span class="flex-grow-1">{{ $p['name'] }}<span class="d-block small {{ $short || $p['wallet']->is_frozen ? 'text-danger' : 'text-muted' }}">คงเหลือ {{ baht($p['wallet']->balance) }} บาท{{ $p['wallet']->is_frozen ? ' · ระงับการใช้จ่ายอยู่' : ($short ? ' · ไม่พอ' : '') }}</span></span>
                    </label>
                @endforeach
            </div>
            <div class="card-footer bg-transparent d-flex gap-2">
                <a href="{{ route('home') }}" class="btn btn-light border">ไม่จ่าย</a>
                <button class="btn btn-primary btn-lg flex-grow-1"><i class="bi bi-check2-circle"></i> ยืนยันจ่าย {{ baht($order['total']) }} บาท</button>
            </div>
        </form>
    @endif
@endif
</div>
@endsection
