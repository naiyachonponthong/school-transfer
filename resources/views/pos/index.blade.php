@extends('layouts.app')
@section('title', 'หน้าจอขาย')

@section('content')
<div class="page-head">
    <div><h1>หน้าจอขาย</h1><div class="sub">เลือกร้านที่จะขาย</div></div>
</div>
<div class="row g-3">
    @forelse ($shops as $shop)
        <div class="col-sm-6 col-lg-4"><a href="{{ route('pos.show', $shop) }}" class="card h-100 text-decoration-none text-body"><div class="card-body d-flex align-items-center gap-3">
            <div class="stat-icon tint-primary"><i class="bi bi-shop"></i></div>
            <div><div class="fw-semibold">{{ $shop->name }}</div><div class="small text-muted">{{ $shop->location ?: 'ร้านค้าในโรงเรียน' }}</div></div>
            <i class="bi bi-chevron-right ms-auto text-muted"></i>
        </div></a></div>
    @empty
        <div class="col-12"><div class="card"><div class="card-body"><div class="empty"><i class="bi bi-shop"></i>ยังไม่มีร้านที่คุณขายได้<br><span class="small">ให้ผู้จัดการกระเป๋าเงินเพิ่มร้านและกำหนดคุณเป็นผู้ขายที่เมนู "กระเป๋าเงิน"</span></div></div></div></div>
    @endforelse
</div>
@endsection
