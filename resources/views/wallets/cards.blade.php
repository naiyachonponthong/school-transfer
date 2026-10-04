@extends('layouts.app')
@section('title', 'บัตรแตะของนักเรียน')

@section('content')
<div class="page-head">
    <div><h1>บัตรแตะของนักเรียน</h1><div class="sub">ผูกหมายเลขบัตร RFID/NFC กับนักเรียน ใช้แตะจ่ายที่ร้านและสแกนเข้า-ออกได้เหมือน QR บนบัตร</div></div>
    <div class="actions">
        <a href="{{ route('wallets.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กระเป๋าเงิน</a>
        <form method="GET">
            <select name="classroom" class="form-select" data-autosubmit aria-label="ห้องเรียน">
                @foreach ($classrooms as $c)
                    <option value="{{ $c->id }}" @selected($classroom?->id === $c->id)>{{ $c->name() }}</option>
                @endforeach
            </select>
        </form>
    </div>
</div>

<div class="card mb-3"><div class="card-body small text-muted">
    <i class="bi bi-info-circle"></i> เสียบเครื่องอ่านบัตรแบบ USB (ชนิดที่ส่งหมายเลขบัตรเหมือนพิมพ์คีย์บอร์ด) คลิกช่องของนักเรียนคนแรก แล้ว<b>แตะบัตรทีละใบ</b> ระบบจะเลื่อนไปช่องถัดไปให้เอง เสร็จแล้วกด "บันทึก" · ล้างช่องให้ว่างเพื่อยกเลิกบัตรใบนั้น (เช่น บัตรหาย)
</div></div>

<form method="POST" action="{{ route('wallets.cards.save') }}" class="card" id="cardsForm">
    @csrf
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th style="width:60px">เลขที่</th><th>นักเรียน</th><th style="width:120px">รหัส</th><th style="min-width:220px">หมายเลขบัตร</th></tr></thead>
        <tbody>
        @forelse ($students as $s)
            <tr>
                <td>{{ $s->number }}</td>
                <td>{{ $s->fullName() }}</td>
                <td class="small font-monospace">{{ $s->student_code }}</td>
                <td><input name="cards[{{ $s->id }}]" value="{{ old('cards.'.$s->id, $s->card_uid) }}" class="form-control form-control-sm font-monospace card-input" maxlength="40" autocomplete="off" placeholder="แตะบัตร…" aria-label="หมายเลขบัตรของ {{ $s->fullName() }}"></td>
            </tr>
        @empty
            <tr><td colspan="4"><div class="empty py-4"><i class="bi bi-credit-card-2-front"></i>ห้องนี้ยังไม่มีนักเรียน</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if ($students->isNotEmpty())
        <div class="card-footer bg-transparent d-flex align-items-center gap-3">
            <span class="small text-muted">ผูกบัตรแล้ว {{ $students->whereNotNull('card_uid')->count() }} จาก {{ $students->count() }} คน</span>
            <button class="btn btn-primary ms-auto"><i class="bi bi-check-lg"></i> บันทึก</button>
        </div>
    @endif
</form>
@endsection

@push('scripts')
<script>
// เครื่องอ่านบัตรส่ง Enter ต่อท้ายหมายเลข: เลื่อนไปช่องถัดไปแทนการส่งฟอร์ม
document.getElementById('cardsForm').addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' || !e.target.classList.contains('card-input')) return;
    e.preventDefault();
    const inputs = Array.from(document.querySelectorAll('.card-input'));
    const next = inputs[inputs.indexOf(e.target) + 1];
    if (next) { next.focus(); next.select(); } else { e.target.blur(); }
});
</script>
@endpush
