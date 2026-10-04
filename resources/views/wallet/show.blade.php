@extends('layouts.app')
@section('title', 'กระเป๋าเงิน')

@section('content')
<div class="page-head">
    <div><h1>กระเป๋าเงิน</h1><div class="sub">{{ $ownerName }}{{ $ownerSub ? ' · '.$ownerSub : '' }}</div></div>
</div>

@if ($children->count() > 1)
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach ($children as $c)
            <a href="{{ route('parent.wallet', $c) }}" class="btn btn-sm {{ $c->id === $student?->id ? 'btn-primary' : 'btn-light border' }}">{{ $c->nickname ?: $c->first_name }}</a>
        @endforeach
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3"><div class="card-body text-center py-4">
            <div class="small text-muted">ยอดเงินคงเหลือ</div>
            <div class="fw-bold" style="font-size:2.6rem;line-height:1.2">{{ baht($wallet->balance) }}</div>
            <div class="small text-muted">บาท · ใช้ไปวันนี้ {{ baht($spentToday) }}{{ $wallet->daily_limit !== null ? ' จากวงเงิน '.baht($wallet->daily_limit) : '' }}</div>
            @if ($wallet->is_frozen)<div class="badge bg-danger mt-2"><i class="bi bi-lock"></i> ระงับการใช้จ่ายอยู่</div>@endif
        </div></div>

        @if ($canManage)
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-plus-circle"></i> เติมเงิน</div>
                <div class="card-body">
                    @if ($biller)
                        @if ($autoQr)
                            <div class="text-center" id="autoBox" data-status="{{ $urls['status'] }}">
                                <div class="fw-semibold mb-1">สแกนจ่าย {{ baht($auto->amount) }} บาท ด้วยแอปธนาคาร</div>
                                <div class="mx-auto bg-white p-2 rounded-3 border" style="width:220px" data-qr="{{ $autoQr }}" data-cell="4"></div>
                                <div class="small mt-2">อ้างอิง {{ $auto->reference }} · QR นี้ใช้ได้ครั้งเดียว</div>
                                <div class="small text-muted" id="autoMsg" role="status" aria-live="polite"><span class="spinner-border spinner-border-sm"></span> รอการชำระ เงินจะเข้ากระเป๋าเองภายในไม่กี่วินาทีหลังจ่าย</div>
                                <a href="{{ $urls['base'] }}" class="btn btn-light border btn-sm mt-2">เปลี่ยนจำนวน</a>
                            </div>
                        @elseif ($auto)
                            <div class="text-center text-success py-2"><i class="bi bi-check-circle fs-3"></i><div class="fw-semibold">เติมเงิน {{ baht($auto->amount) }} บาท เข้ากระเป๋าแล้ว</div>
                                <a href="{{ $urls['base'] }}" class="btn btn-light border btn-sm mt-2">เติมอีกครั้ง</a></div>
                        @else
                            <div class="small text-muted mb-2">สแกนจ่ายด้วยแอปธนาคาร เงินเข้ากระเป๋าทันที เลือกจำนวนเงิน</div>
                            <form method="POST" action="{{ $urls['auto'] }}">
                                @csrf
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    @foreach ([50, 100, 200, 300, 500, 1000] as $a)
                                        <button name="amount" value="{{ $a }}" class="btn btn-light border">{{ $a }} บาท</button>
                                    @endforeach
                                </div>
                            </form>
                            <form method="POST" action="{{ $urls['auto'] }}" class="input-group">
                                @csrf
                                <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror" min="1" max="20000" step="1" inputmode="numeric" placeholder="จำนวนเงินอื่น" aria-label="จำนวนเงินอื่น" required>
                                <button class="btn btn-primary">สร้าง QR</button>
                            </form>
                        @endif
                        <hr>
                        <div class="small text-muted mb-2">หรือโอนเองแล้วแนบสลิป (รอฝ่ายการเงินตรวจ)</div>
                    @endif
                    @if (! $promptpay)
                        <div class="small text-muted"><i class="bi bi-info-circle"></i> {{ $biller ? 'เติมเงินสดได้ที่ห้องการเงินเช่นกัน' : 'โรงเรียนยังไม่ได้ตั้งพร้อมเพย์สำหรับรับโอน เติมเงินสดได้ที่ห้องการเงิน' }}</div>
                    @elseif (! $qr)
                        <div class="small text-muted mb-2">เลือกจำนวนเงินที่ต้องการเติม</div>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach ([50, 100, 200, 300, 500, 1000] as $a)
                                <a href="{{ $urls['base'] }}?amount={{ $a }}" class="btn btn-light border">{{ $a }} บาท</a>
                            @endforeach
                        </div>
                        <form method="GET" action="{{ $urls['base'] }}" class="input-group">
                            <input type="number" name="amount" class="form-control" min="1" max="20000" step="1" inputmode="numeric" placeholder="จำนวนเงินอื่น" aria-label="จำนวนเงินอื่น" required>
                            <button class="btn btn-primary">ตกลง</button>
                        </form>
                        <div class="form-text">หรือเติมเงินสดได้ที่ห้องการเงิน เงินเข้าทันที</div>
                    @else
                        <div class="text-center">
                            <div class="fw-semibold mb-1">สแกนจ่าย {{ baht($amount) }} บาท ด้วยแอปธนาคาร</div>
                            <div class="mx-auto bg-white p-2 rounded-3 border" style="width:220px" data-qr="{{ $qr }}" data-cell="4"></div>
                            <div class="small mt-2">พร้อมเพย์ {{ $promptpay }} · {{ school('school_name') }}</div>
                        </div>
                        <form method="POST" action="{{ $urls['topup'] }}" enctype="multipart/form-data" class="mt-3">
                            @csrf
                            <input type="hidden" name="amount" value="{{ $amount }}">
                            <label class="form-label">โอนแล้วแนบสลิป</label>
                            <input type="file" name="slip" accept="image/*" class="form-control @error('slip') is-invalid @enderror" required>
                            @error('slip')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="d-flex gap-2 mt-3">
                                <a href="{{ $urls['base'] }}" class="btn btn-light border">เปลี่ยนจำนวน</a>
                                <button class="btn btn-primary flex-grow-1"><i class="bi bi-upload"></i> ส่งสลิป</button>
                            </div>
                            <div class="form-text">เงินจะเข้ากระเป๋าเมื่อฝ่ายการเงินตรวจสลิปแล้ว</div>
                        </form>
                    @endif
                </div>
                @if ($topups->isNotEmpty())
                    <div class="border-top">
                        @foreach ($topups as $t)
                            @php
                                [$label, $color] = \App\Models\WalletTopup::STATUSES[$t->status];
                            @endphp
                            <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom small">
                                <div class="flex-grow-1">{{ baht($t->amount) }} บาท<div class="text-muted">{{ thai_datetime($t->created_at) }}{{ $t->status === 'rejected' && $t->note ? ' · '.$t->note : '' }}</div></div>
                                <span class="badge bg-{{ $color }}">{{ $label }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <form method="POST" action="{{ $urls['settings'] }}" class="card">
                @csrf @method('PUT')
                <div class="card-header"><i class="bi bi-shield-check"></i> ควบคุมการใช้จ่าย</div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <label class="form-label">วงเงินใช้จ่ายต่อวัน (บาท)</label>
                        <input type="number" name="daily_limit" value="{{ old('daily_limit', $wallet->daily_limit !== null ? (float) $wallet->daily_limit : '') }}" class="form-control @error('daily_limit') is-invalid @enderror" min="1" max="20000" step="1" inputmode="numeric" placeholder="เว้นว่าง = ไม่จำกัด">
                        @error('daily_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12"><div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_frozen" value="1" id="frozen" @checked($wallet->is_frozen)>
                        <label class="form-check-label" for="frozen">ระงับการใช้จ่าย (เช่น บัตรหาย)</label>
                    </div></div>
                </div>
                <div class="card-footer bg-transparent text-end"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> บันทึก</button></div>
            </form>
        @endif
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-list-ul"></i> รายการล่าสุด</div>
            @forelse ($transactions as $t)
                @php
                    [$label] = \App\Models\WalletTransaction::TYPES[$t->type] ?? [$t->type];
                @endphp
                <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                    <div class="flex-grow-1 min-w-0">
                        <div class="text-truncate">{{ $t->sale ? $t->sale->itemsLabel() : $label }}</div>
                        <div class="small text-muted">{{ thai_datetime($t->created_at) }} · {{ $t->sale ? ($t->type === 'void' ? 'คืนเงิน · ' : '').$t->sale->shop->name : $t->note }}</div>
                    </div>
                    <div class="text-end">
                        <div class="fw-semibold {{ $t->amount >= 0 ? 'text-success' : '' }}">{{ $t->amount >= 0 ? '+' : '' }}{{ baht($t->amount) }}</div>
                        <div class="small text-muted">เหลือ {{ baht($t->balance_after) }}</div>
                    </div>
                </div>
            @empty
                <div class="empty py-5"><i class="bi bi-wallet2"></i>ยังไม่มีรายการ</div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<script>
// หน้า QR สแกนจ่ายอัตโนมัติ: ถามสถานะทุก 4 วินาที เงินเข้าแล้วโหลดหน้าใหม่
(function () {
    const box = document.getElementById('autoBox');
    if (!box) return;
    const timer = setInterval(async () => {
        try {
            const data = await (await fetch(box.dataset.status, { headers: { Accept: 'application/json' } })).json();
            if (data.status !== 'pending') { clearInterval(timer); location.reload(); }
        } catch (e) {}
    }, 4000);
})();
</script>
@endpush
