@extends('layouts.app')
@section('title', 'กระเป๋าเงิน')

@section('content')
<div class="page-head">
    <div><h1>กระเป๋าเงินนักเรียน</h1><div class="sub">เติมเงิน ตรวจสลิป ร้านค้า และรายงานการขาย</div></div>
    <div class="actions">
        <a href="{{ route('pos.index') }}" class="btn btn-light border"><i class="bi bi-shop"></i> หน้าจอขาย</a>
        <a href="{{ route('wallets.report') }}" class="btn btn-light border"><i class="bi bi-graph-up"></i> รายงาน</a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addShop"><i class="bi bi-plus-lg"></i> เพิ่มร้านค้า</button>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-primary"><i class="bi bi-wallet2"></i></div><div><div class="stat-value">{{ baht($outstanding) }}</div><div class="stat-label">เงินคงค้างในกระเป๋าทั้งหมด</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-success"><i class="bi bi-plus-circle"></i></div><div><div class="stat-value">{{ baht($topupToday) }}</div><div class="stat-label">เติมเงินวันนี้</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-info"><i class="bi bi-basket"></i></div><div><div class="stat-value">{{ baht($salesToday) }}</div><div class="stat-label">ยอดขายวันนี้</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-warning"><i class="bi bi-receipt-cutoff"></i></div><div><div class="stat-value">{{ $pending->count() }}</div><div class="stat-label">สลิปรอตรวจ</div></div></div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <form method="POST" action="{{ route('wallets.topup') }}" class="card mb-3">
            @csrf
            <div class="card-header"><i class="bi bi-cash-coin"></i> เติมเงินสด</div>
            <div class="card-body row g-3">
                <div class="col-12">
                    <label class="form-label">นักเรียน</label>
                    <select name="student_id" class="form-select @error('student_id') is-invalid @enderror" required>
                        <option value="">— เลือกหรือค้นหา —</option>
                        @foreach ($students as $s)
                            <option value="{{ $s->id }}" @selected((int) old('student_id') === $s->id)>{{ $s->student_code }} {{ $s->fullName() }} · {{ $s->classroom?->name() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-5">
                    <label class="form-label">จำนวนเงิน (บาท)</label>
                    <input type="number" name="amount" value="{{ old('amount') }}" class="form-control @error('amount') is-invalid @enderror" min="1" max="20000" step="0.01" inputmode="decimal" required>
                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-7">
                    <label class="form-label">หมายเหตุ <span class="text-muted small">(ไม่บังคับ)</span></label>
                    <input name="note" value="{{ old('note') }}" class="form-control" maxlength="200">
                </div>
            </div>
            <div class="card-footer bg-transparent text-end"><button class="btn btn-primary"><i class="bi bi-plus-lg"></i> เติมเงิน</button></div>
        </form>

        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history"></i> เติมเงินล่าสุด</div>
            @forelse ($recent as $t)
                <a href="{{ route('wallets.student', $t->wallet->student) }}" class="d-flex align-items-center gap-2 px-3 py-2 border-bottom small text-decoration-none text-body">
                    <div class="flex-grow-1">{{ $t->wallet->student->fullName() }}<div class="text-muted">{{ thai_datetime($t->created_at) }} · {{ $t->note }}</div></div>
                    <span class="fw-semibold text-success">+{{ baht($t->amount) }}</span>
                </a>
            @empty
                <div class="empty py-4"><i class="bi bi-clock-history"></i>ยังไม่มีการเติมเงิน</div>
            @endforelse
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-receipt-cutoff"></i> สลิปเติมเงินรอตรวจ <span class="ms-2 badge bg-warning text-dark">{{ $pending->count() }}</span></div>
            @forelse ($pending as $t)
                <div class="d-flex flex-wrap align-items-center gap-2 px-3 py-2 border-bottom">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $t->wallet->student->fullName() }} <span class="small text-muted fw-normal">{{ $t->wallet->student->classroom?->name() }}</span></div>
                        <div class="small text-muted">{{ baht($t->amount) }} บาท · ส่งโดย {{ $t->requester?->name ?? '-' }} · {{ thai_datetime($t->created_at) }}</div>
                    </div>
                    <a href="{{ route('files.show', ['wallet-slip', $t->id]) }}" target="_blank" class="btn btn-sm btn-light border"><i class="bi bi-image"></i> ดูสลิป</a>
                    <form method="POST" action="{{ route('wallets.topups.approve', $t) }}" data-confirm="อนุมัติเติมเงิน {{ baht($t->amount) }} บาท ให้ {{ $t->wallet->student->fullName() }}?">@csrf<button class="btn btn-sm btn-success">อนุมัติ</button></form>
                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reject{{ $t->id }}">ไม่อนุมัติ</button>
                </div>
            @empty
                <div class="empty py-4"><i class="bi bi-check2-circle"></i>ไม่มีสลิปรอตรวจ</div>
            @endforelse
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-shop"></i> ร้านค้า</div>
            <div class="table-responsive"><table class="table align-middle mb-0">
                <thead><tr><th>ร้าน</th><th>ผู้ขาย</th><th class="text-end">สินค้า</th><th class="text-end">ขายวันนี้</th><th></th></tr></thead>
                <tbody>
                @forelse ($shops as $shop)
                    <tr>
                        <td><span class="fw-semibold">{{ $shop->name }}</span>@if (! $shop->is_active) <span class="badge bg-secondary">ปิด</span>@endif<div class="small text-muted">{{ $shop->location }}</div></td>
                        <td class="small">{{ $shop->cashiers->pluck('name')->implode(', ') ?: '-' }}</td>
                        <td class="text-end">{{ $shop->products_count }}</td>
                        <td class="text-end">{{ baht($shopToday[$shop->id]->total ?? 0) }}<div class="small text-muted">{{ $shopToday[$shop->id]->n ?? 0 }} รายการ</div></td>
                        <td class="text-end"><a href="{{ route('wallets.shop', $shop) }}" class="btn btn-sm btn-light border">จัดการ</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="empty py-4"><i class="bi bi-shop"></i>ยังไม่มีร้านค้า กด "เพิ่มร้านค้า" เพื่อเริ่ม</div></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>

<div class="modal fade" id="addShop" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="{{ route('wallets.shops.store') }}" class="modal-content">
        @csrf
        <div class="modal-header"><h5 class="modal-title">เพิ่มร้านค้า</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
        <div class="modal-body row g-3">
            <div class="col-12"><label class="form-label">ชื่อร้าน</label><input name="name" class="form-control" maxlength="255" required placeholder="เช่น ร้านข้าวแกงป้าศรี"></div>
            <div class="col-12"><label class="form-label">ที่ตั้ง <span class="text-muted small">(ไม่บังคับ)</span></label><input name="location" class="form-control" maxlength="255" placeholder="เช่น โรงอาหาร ช่อง 3"></div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">เพิ่มร้านค้า</button></div>
    </form></div>
</div>
@foreach ($pending as $t)
    <div class="modal fade" id="reject{{ $t->id }}" tabindex="-1">
        <div class="modal-dialog"><form method="POST" action="{{ route('wallets.topups.reject', $t) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">ไม่อนุมัติสลิป {{ baht($t->amount) }} บาท</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body"><label class="form-label">เหตุผล (ผู้ปกครองจะเห็น)</label><input name="note" class="form-control" maxlength="200" required placeholder="เช่น ยอดในสลิปไม่ตรง"></div>
            <div class="modal-footer"><button class="btn btn-danger">ไม่อนุมัติ</button></div>
        </form></div>
    </div>
@endforeach
@endsection
