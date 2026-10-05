@extends('layouts.app')
@section('title', 'คืนเงินผู้พ้นสภาพ')

@section('content')
<div class="page-head">
    <div><h1>คืนเงินคงเหลือของผู้พ้นสภาพ</h1><div class="sub">นักเรียนที่จบ ย้าย หรือพ้นสภาพ และบุคลากรที่ปิดบัญชีแล้ว ที่ยังมีเงินในกระเป๋า · {{ $wallets->count() }} คน รวม {{ baht($total) }} บาท</div></div>
    <div class="actions no-print">
        <button type="button" class="btn btn-light border" onclick="print()"><i class="bi bi-printer"></i> พิมพ์ใบรับเงินคืน</button>
        <a href="{{ route('wallets.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กระเป๋าเงิน</a>
    </div>
</div>

<form method="POST" action="{{ route('wallets.leavers.withdraw') }}" data-confirm="ถอนเงินคืนทั้งหมดของรายการที่เลือก? ยอดในกระเป๋าจะเป็น 0 และแก้ย้อนหลังไม่ได้">
    @csrf
    <div class="card">
        <div class="card-header"><i class="bi bi-box-arrow-left"></i> กระเป๋าที่ต้องคืนเงิน</div>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr>
                <th class="no-print" style="width:40px"><input type="checkbox" class="form-check-input" id="checkAll" aria-label="เลือกทั้งหมด" @disabled($wallets->isEmpty())></th>
                <th>ชื่อ</th><th>สถานะ</th><th>ติดต่อ</th><th class="text-end">คงเหลือ (บาท)</th><th class="d-none d-print-table-cell">ลงชื่อผู้รับเงิน</th>
            </tr></thead>
            <tbody>
            @forelse ($wallets as $w)
                @php
                    $student = $w->student;
                    $guardian = $student?->guardians->first();
                @endphp
                <tr>
                    <td class="no-print"><input type="checkbox" class="form-check-input leaver" name="wallets[]" value="{{ $w->id }}" aria-label="เลือก {{ $w->ownerName() }}"></td>
                    <td><a href="{{ $w->adminUrl() }}" class="fw-semibold">{{ $w->ownerName() }}</a><div class="small text-muted">{{ $student ? $student->student_code.' · '.($student->classroom?->name() ?? '-') : ($w->user?->position ?: 'ครู/บุคลากร') }}</div></td>
                    <td class="small">
                        @if ($student)
                            {{ \App\Models\Student::STATUSES[$student->status] ?? $student->status }}@if ($student->left_on)<div class="text-muted">{{ thai_date($student->left_on) }}</div>@endif
                        @else
                            ปิดบัญชีแล้ว
                        @endif
                    </td>
                    <td class="small">{{ $student ? ($guardian ? $guardian->name.($guardian->phone ? ' · '.$guardian->phone : '') : '-') : ($w->user?->phone ?: '-') }}</td>
                    <td class="text-end fw-semibold">{{ baht($w->balance) }}</td>
                    <td class="d-none d-print-table-cell" style="min-width:180px">&nbsp;</td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty py-4"><i class="bi bi-check2-circle"></i>ไม่มีเงินค้างคืน ผู้พ้นสภาพทุกคนยอดในกระเป๋าเป็น 0 แล้ว</div></td></tr>
            @endforelse
            </tbody>
            @if ($wallets->isNotEmpty())<tfoot><tr><th class="no-print"></th><th colspan="3">รวม</th><th class="text-end">{{ baht($total) }}</th><th class="d-none d-print-table-cell"></th></tr></tfoot>@endif
        </table></div>
        @if ($wallets->isNotEmpty())
            <div class="card-body border-top row g-3 no-print">
                <div class="col-md-3">
                    <label class="form-label">วิธีคืนเงิน</label>
                    <select name="method" class="form-select">
                        @foreach (\App\Models\WalletTransaction::REFUND_METHODS as $k => $label)<option value="{{ $k }}" @selected(old('method') === $k)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">เหตุผล (บันทึกในสมุดรายการของทุกคนที่เลือก)</label><input name="note" value="{{ old('note', 'คืนเงินคงเหลือเมื่อพ้นสภาพ') }}" class="form-control" maxlength="200" required></div>
                <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary w-100" id="withdrawBtn" disabled><i class="bi bi-box-arrow-left"></i> ถอนคืนที่เลือก</button></div>
                <div class="col-12 small text-muted">พิมพ์ใบรับเงินคืนให้ผู้ปกครองลงชื่อก่อน แล้วจึงกดถอนคืน · คืนเป็นเงินสดจะนับเป็นเงินออกในใบนำส่งของวันนี้ · ผู้ปกครองได้รับแจ้งเตือนเมื่อคืนเงินแล้ว</div>
            </div>
        @endif
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const all = document.getElementById('checkAll'), boxes = [...document.querySelectorAll('.leaver')], btn = document.getElementById('withdrawBtn');
    if (!btn) return;
    const sync = () => { btn.disabled = !boxes.some((b) => b.checked); all.checked = boxes.every((b) => b.checked); };
    all.addEventListener('change', () => { boxes.forEach((b) => { b.checked = all.checked; }); sync(); });
    boxes.forEach((b) => b.addEventListener('change', sync));
})();
</script>
@endpush
