@extends('layouts.app')
@section('title', 'กระเป๋าเงิน '.$student->fullName())

@section('content')
<div class="page-head">
    <div><h1>{{ $student->fullName() }}</h1><div class="sub">{{ $student->student_code }} · {{ $student->classroom?->name() }} · คงเหลือ <b>{{ baht($wallet->balance) }}</b> บาท{{ $wallet->is_frozen ? ' · ระงับการใช้จ่ายอยู่' : '' }}{{ $wallet->daily_limit !== null ? ' · วงเงินต่อวัน '.baht($wallet->daily_limit) : '' }}</div></div>
    <div class="actions">
        <a href="{{ route('wallets.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กระเป๋าเงิน</a>
        <button class="btn btn-light border" data-bs-toggle="modal" data-bs-target="#adjust"><i class="bi bi-sliders"></i> ปรับยอด / ถอนคืน</button>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-list-ul"></i> สมุดรายการ</div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>เวลา</th><th>ประเภท</th><th>รายละเอียด</th><th class="text-end">จำนวน</th><th class="text-end">คงเหลือ</th></tr></thead>
        <tbody>
        @forelse ($transactions as $t)
            @php
                [$label, $color] = \App\Models\WalletTransaction::TYPES[$t->type] ?? [$t->type, 'secondary'];
            @endphp
            <tr>
                <td class="small text-nowrap">{{ thai_datetime($t->created_at) }}</td>
                <td><span class="badge bg-{{ $color }}">{{ $label }}</span></td>
                <td class="small">{{ $t->sale ? $t->sale->itemsLabel().' · ' : '' }}{{ $t->note }}</td>
                <td class="text-end fw-semibold {{ $t->amount >= 0 ? 'text-success' : '' }}">{{ $t->amount >= 0 ? '+' : '' }}{{ baht($t->amount) }}</td>
                <td class="text-end">{{ baht($t->balance_after) }}</td>
            </tr>
        @empty
            <tr><td colspan="5"><div class="empty py-4"><i class="bi bi-wallet2"></i>ยังไม่มีรายการ</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if ($transactions->hasPages())<div class="card-footer bg-transparent">{{ $transactions->links() }}</div>@endif
</div>

<div class="modal fade" id="adjust" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="{{ route('wallets.adjust', $student) }}" class="modal-content">
        @csrf
        <div class="modal-header"><h5 class="modal-title">ปรับยอด / ถอนเงินคืน</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
        <div class="modal-body row g-3">
            <div class="col-12">
                <label class="form-label">ประเภท</label>
                <select name="type" class="form-select">
                    <option value="withdraw">ถอนเงินคืนผู้ปกครอง (จบ/ย้ายออก)</option>
                    <option value="adjust_in">ปรับยอดเพิ่ม (แก้ข้อผิดพลาด)</option>
                    <option value="adjust_out">ปรับยอดลด (แก้ข้อผิดพลาด)</option>
                </select>
            </div>
            <div class="col-md-5"><label class="form-label">จำนวนเงิน (บาท)</label><input type="number" name="amount" class="form-control" min="0.01" step="0.01" inputmode="decimal" required></div>
            <div class="col-md-7"><label class="form-label">เหตุผล</label><input name="note" class="form-control" maxlength="200" required></div>
            <div class="col-12 small text-muted">ทุกรายการถูกบันทึกในสมุดรายการและประวัติการแก้ไข ลบหรือแก้ย้อนหลังไม่ได้</div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">บันทึก</button></div>
    </form></div>
</div>
@endsection
