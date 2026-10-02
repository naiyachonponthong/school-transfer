@extends('layouts.app')
@section('title', 'ตรวจสลิปโอนเงิน')

@section('content')
<div class="page-head"><div><h1>ตรวจสลิปโอนเงิน</h1><div class="sub">ยืนยันแล้วระบบออกใบเสร็จและแจ้งผู้ปกครองให้อัตโนมัติ</div></div></div>
<ul class="nav nav-pills mb-3 gap-1">
    @foreach (['pending' => 'รอตรวจ', 'approved' => 'ยืนยันแล้ว', 'rejected' => 'ไม่ผ่าน', 'all' => 'ทั้งหมด'] as $k => $v)
        <li class="nav-item"><a href="?status={{ $k }}" class="nav-link {{ $status === $k ? 'active' : '' }}">{{ $v }} @if($k === 'pending' && $pendingCount)<span class="badge bg-danger">{{ $pendingCount }}</span>@endif</a></li>
    @endforeach
</ul>
<div class="row g-3">
    @forelse ($slips as $s)
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <a href="{{ $s->imageUrl() }}" target="_blank" class="d-block bg-light" style="height:260px;border-radius:16px 16px 0 0;overflow:hidden">
                    <img src="{{ $s->imageUrl() }}" alt="สลิป" style="width:100%;height:100%;object-fit:contain">
                </a>
                <div class="card-body small">
                    <div class="d-flex justify-content-between"><b class="fs-5">{{ baht($s->amount) }} ฿</b><span class="badge bg-{{ $s->statusColor() }} align-self-center">{{ $s->statusLabel() }}</span></div>
                    <div><a href="{{ route('invoices.show', $s->invoice) }}">{{ $s->invoice->invoice_no }}</a> · {{ $s->invoice->title }}</div>
                    <div>{{ $s->invoice->student->fullName() }} <span class="text-muted">{{ $s->invoice->student->classroom?->name() }}</span></div>
                    <div class="text-muted">ค้างชำระ {{ baht($s->invoice->balance()) }} · โอนเมื่อ {{ thai_datetime($s->transferred_at) }}</div>
                    <div class="text-muted">ส่งโดย {{ $s->uploader?->name }} @if($s->note)· {{ $s->note }}@endif</div>
                    @if ($s->reviewer)<div class="text-muted">ตรวจโดย {{ $s->reviewer->name }} {{ $s->review_note ? '· '.$s->review_note : '' }}</div>@endif
                </div>
                @if ($s->status === 'pending')
                    <div class="card-footer bg-transparent d-flex gap-2">
                        <form method="POST" action="{{ route('slips.reject', $s) }}" class="flex-fill" onsubmit="const n=prompt('เหตุผลที่ไม่ผ่าน เช่น ยอดไม่ตรง / สลิปไม่ชัด');if(n===null)return false;this.note.value=n">@csrf<input type="hidden" name="note"><button class="btn btn-outline-danger w-100">ไม่ผ่าน</button></form>
                        <form method="POST" action="{{ route('slips.approve', $s) }}" class="flex-fill">@csrf<button class="btn btn-success w-100"><i class="bi bi-check-lg"></i> ยืนยัน</button></form>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="col-12"><div class="card"><div class="empty"><i class="bi bi-receipt"></i>ไม่มีสลิป</div></div></div>
    @endforelse
</div>
<div class="mt-3">{{ $slips->links() }}</div>
@endsection
