@extends('layouts.app')
@section('title', 'จ่ายเงินร้านค้า')

@section('content')
<div class="page-head">
    <div><h1>จ่ายเงินยอดขายให้ร้านค้า</h1><div class="sub">ยอดขายที่ตัดจากกระเป๋าเงินและยังไม่ได้จ่ายให้ร้าน นับถึง {{ thai_date($until) }}</div></div>
    <form class="actions" method="GET">
        <input type="date" name="until" value="{{ $until->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control" data-autosubmit aria-label="นับยอดขายถึงวันที่">
        <a href="{{ route('wallets.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กระเป๋าเงิน</a>
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-info"><i class="bi bi-basket"></i></div><div><div class="stat-value">{{ baht($shops->sum('gross')) }}</div><div class="stat-label">ยอดขายค้างจ่าย</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-teal"><i class="bi bi-percent"></i></div><div><div class="stat-value">{{ baht($shops->sum('fee')) }}</div><div class="stat-label">ส่วนแบ่งของโรงเรียน</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-primary"><i class="bi bi-cash-stack"></i></div><div><div class="stat-value">{{ baht($shops->sum('net')) }}</div><div class="stat-label">ต้องจ่ายให้ร้าน</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-warning"><i class="bi bi-shop"></i></div><div><div class="stat-value">{{ $shops->where('count', '>', 0)->count() }}</div><div class="stat-label">ร้านที่มียอดค้างจ่าย</div></div></div></div></div>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-shop"></i> ยอดค้างจ่ายแยกร้าน</div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>ร้าน</th><th>ค้างตั้งแต่</th><th class="text-end">รายการ</th><th class="text-end">ยอดขาย</th><th class="text-end">ส่วนแบ่ง</th><th class="text-end">จ่ายให้ร้าน</th><th></th></tr></thead>
        <tbody>
        @forelse ($shops as $row)
            <tr class="{{ $row['count'] ? '' : 'text-muted' }}">
                <td><a href="{{ route('wallets.shop', $row['shop']) }}" class="fw-semibold">{{ $row['shop']->name }}</a><div class="small text-muted">{{ $row['shop']->payout_account ?: 'ยังไม่ได้ระบุช่องทางรับเงิน' }}</div></td>
                <td class="small">{{ $row['since'] ? thai_date($row['since']) : '-' }}</td>
                <td class="text-end">{{ number_format($row['count']) }}</td>
                <td class="text-end">{{ baht($row['gross']) }}</td>
                <td class="text-end small">{{ (float) $row['shop']->fee_percent > 0 ? baht($row['fee']).' ('.\App\Models\Shop::percentLabel($row['shop']->fee_percent).')' : '-' }}</td>
                <td class="text-end fw-semibold">{{ baht($row['net']) }}</td>
                <td class="text-end">@if ($row['count'])<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#settle{{ $row['shop']->id }}"><i class="bi bi-cash-stack"></i> จ่ายเงิน</button>@endif</td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty py-4"><i class="bi bi-shop"></i>ยังไม่มีร้านค้า</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-journal-text"></i> ทะเบียนใบจ่ายเงิน</div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>เลขที่</th><th>วันที่จ่าย</th><th>ร้าน</th><th>งวดยอดขาย</th><th class="text-end">ยอดขาย</th><th class="text-end">ส่วนแบ่ง</th><th class="text-end">จ่ายให้ร้าน</th><th>วิธีจ่าย</th><th>ผู้จ่าย</th></tr></thead>
        <tbody>
        @forelse ($settlements as $s)
            <tr class="{{ $s->isVoided() ? 'text-muted' : '' }}">
                <td class="text-nowrap"><a href="{{ route('wallets.settlements.show', $s) }}">{{ $s->doc_no }}</a>@if ($s->isVoided()) <span class="badge bg-secondary">ยกเลิก</span>@endif</td>
                <td class="small text-nowrap">{{ thai_datetime($s->paid_at) }}</td>
                <td>{{ $s->shop->name }}</td>
                <td class="small">{{ $s->periodLabel() }} · {{ number_format($s->sales_count) }} รายการ</td>
                <td class="text-end">{{ baht($s->gross) }}</td>
                <td class="text-end small">{{ (float) $s->fee > 0 ? baht($s->fee) : '-' }}</td>
                <td class="text-end {{ $s->isVoided() ? 'text-decoration-line-through' : 'fw-semibold' }}">{{ baht($s->net) }}</td>
                <td class="small">{{ $s->methodLabel() }}</td>
                <td class="small">{{ $s->payer?->name ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="9"><div class="empty py-4"><i class="bi bi-journal-text"></i>ยังไม่เคยจ่ายเงินให้ร้านค้า</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if ($settlements->hasPages())<div class="card-footer bg-transparent">{{ $settlements->links() }}</div>@endif
</div>

@foreach ($shops->where('count', '>', 0) as $row)
    <div class="modal fade" id="settle{{ $row['shop']->id }}" tabindex="-1">
        <div class="modal-dialog"><form method="POST" action="{{ route('wallets.settlements.store') }}" class="modal-content">
            @csrf
            <input type="hidden" name="shop_id" value="{{ $row['shop']->id }}">
            <input type="hidden" name="until" value="{{ $until->toDateString() }}">
            <div class="modal-header"><h5 class="modal-title">จ่ายเงินให้ {{ $row['shop']->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <div class="d-flex justify-content-between small"><span>ยอดขาย {{ number_format($row['count']) }} รายการ ถึง {{ thai_date($until) }}</span><span>{{ baht($row['gross']) }}</span></div>
                    @if ($row['fee'] > 0)<div class="d-flex justify-content-between small text-muted"><span>หักส่วนแบ่งของโรงเรียน {{ \App\Models\Shop::percentLabel($row['shop']->fee_percent) }}</span><span>−{{ baht($row['fee']) }}</span></div>@endif
                    <div class="d-flex justify-content-between fw-bold fs-5 border-top mt-2 pt-2"><span>จ่ายให้ร้าน</span><span>{{ baht($row['net']) }} บาท</span></div>
                    @if ($row['shop']->payout_account)<div class="small text-muted mt-1">ช่องทางรับเงิน: {{ $row['shop']->payout_account }}</div>@endif
                </div>
                <div class="col-md-5">
                    <label class="form-label">วิธีจ่าย</label>
                    <select name="method" class="form-select">
                        @foreach (\App\Models\ShopSettlement::METHODS as $k => $label)<option value="{{ $k }}" @selected($k === ($row['shop']->payout_account ? 'transfer' : 'cash'))>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-7"><label class="form-label">หมายเหตุ <span class="text-muted small">(ไม่บังคับ)</span></label><input name="note" class="form-control" maxlength="255" placeholder="เช่น เลขอ้างอิงการโอน"></div>
                <div class="col-12 small text-muted">ยอดจริงคิดจากรายการขาย ณ ตอนที่กดบันทึก จ่ายเป็นเงินสดจะนับเป็นเงินออกในใบนำส่งของวันนี้</div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> บันทึกการจ่ายเงิน</button></div>
        </form></div>
    </div>
@endforeach
@endsection
