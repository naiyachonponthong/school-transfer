@extends('layouts.public')
@section('title', 'ตรวจสอบสถานะใบสมัคร')
@section('heading', 'ตรวจสอบสถานะใบสมัคร')
@section('subheading', 'ดูผล · พิมพ์ใบสมัคร/ใบเสร็จ/ใบมอบตัว · ชำระค่าสมัคร')

@section('content')
@if ($draft)
    <div class="alert alert-info d-flex flex-wrap align-items-center gap-2">
        <span><i class="bi bi-pencil-square"></i> ใบสมัคร {{ $draft->app_no }} ยังกรอกไม่เสร็จ</span>
        <a href="{{ route('apply.step', 'review') }}" class="btn btn-sm btn-primary ms-auto">กรอกต่อ</a>
    </div>
@endif

@if (! $a)
    <form method="GET" class="card mb-3">
        <div class="card-body row g-2 align-items-end">
            <div class="col-md-5"><label class="form-label">เลขที่ใบสมัคร</label><input name="app_no" value="{{ request('app_no') }}" class="form-control" placeholder="A25700001" required></div>
            <div class="col-md-5"><label class="form-label">เบอร์โทรผู้ปกครอง</label><input name="phone" value="{{ request('phone') }}" class="form-control" inputmode="tel" required></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">ตรวจสอบ</button></div>
        </div>
        <div class="card-footer bg-transparent small text-muted">ลืมเลขที่ใบสมัคร? ไปที่ <a href="{{ route('apply') }}">หน้าสมัครเรียน</a> แล้วกรอกเลขบัตร วันเกิด และเบอร์โทรเดิม</div>
    </form>
    @if ($searched)<div class="alert alert-warning">ไม่พบใบสมัคร กรุณาตรวจสอบเลขที่และเบอร์โทรอีกครั้ง</div>@endif
@else
    <div class="card mb-3">
        <div class="card-body text-center py-4">
            <div class="text-muted small">ใบสมัครเลขที่ {{ $a->app_no }} · ชั้น {{ $a->level }} · ส่งเมื่อ {{ $a->submitted_at ? thai_datetime($a->submitted_at) : '-' }}</div>
            <div class="fs-4 fw-bold my-1">{{ $a->fullName() }}</div>
            <span class="badge bg-{{ $a->statusColor() }} fs-6 px-3 py-2">{{ $a->statusLabel() }}</span>
            @if ($a->staff_note)<div class="mt-3">{{ $a->staff_note }}</div>@endif
            @if ($a->exam_room || $a->exam_seat || $a->exam_no)
                <div class="mt-3"><span class="badge bg-light text-dark border fs-6 fw-normal text-wrap"><i class="bi bi-door-open"></i>
                    @if ($a->exam_no) เลขประจำตัวสอบ <b>{{ $a->exam_no }}</b> · @endif ห้องสอบ <b>{{ $a->exam_room ?: '-' }}</b> · เลขที่นั่งสอบ <b>{{ $a->exam_seat ?: '-' }}</b></span></div>
                @if ($a->exam_no && ($round = $a->round()) && $round->exam_date && ! $round->isPublished())
                    <div class="small text-muted mt-1">สอบวันที่ {{ thai_date($round->exam_date) }} · นำส่วนที่ 2 ของใบสมัครและบัตรประชาชนมาในวันสอบ</div>
                @endif
            @endif
            @if ($a->exam_total !== null && in_array($a->status, ['accepted', 'reserve', 'rejected', 'enrolled'], true))
                <div class="mt-2 small">คะแนนสอบรวม <b>{{ rtrim(rtrim(number_format($a->exam_total, 2), '0'), '.') }}</b>@if ($a->exam_rank) · อันดับที่ <b>{{ $a->exam_rank }}</b>@endif</div>
            @endif
            @if ($a->status === 'reserve')<div class="mt-3 text-warning-emphasis fw-semibold">อยู่ในรายชื่อสำรอง{{ $a->reserve_no ? ' ลำดับที่ '.$a->reserve_no : '' }} — โรงเรียนจะติดต่อเมื่อมีที่ว่างจากผู้ไม่มารายงานตัว</div>@endif
            @if ($a->status === 'accepted')<div class="mt-3 text-success fw-semibold">ขอแสดงความยินดี กรุณาพิมพ์ใบมอบตัว กรอกให้ครบ แล้วนำมามอบตัวตามวันที่โรงเรียนประกาศ</div>@endif
        </div>
        <div class="card-footer bg-transparent d-flex flex-wrap justify-content-center gap-2">
            <a href="{{ route('apply.print', 'application') }}" target="_blank" class="btn btn-light border"><i class="bi bi-printer"></i> พิมพ์ใบสมัคร</a>
            @if ($a->fee_status === 'paid')<a href="{{ route('apply.print', 'receipt') }}" target="_blank" class="btn btn-light border"><i class="bi bi-receipt"></i> ใบเสร็จค่าสมัคร</a>@endif
            @if (in_array($a->status, ['accepted', 'enrolled'], true))<a href="{{ route('apply.print', 'enrollment') }}" target="_blank" class="btn btn-primary"><i class="bi bi-file-earmark-person"></i> พิมพ์ใบมอบตัว</a>@endif
        </div>
    </div>

    @if ($a->feeDue())
        @php($pp = school('promptpay_id') ? \App\Support\PromptPay::payload(school('promptpay_id'), $a->fee_amount) : null)
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-cash-coin"></i> ค่าสมัคร {{ baht($a->fee_amount) }} บาท
                <span class="badge bg-{{ $a->feeColor() }} ms-auto">{{ $a->feeLabel() }}</span></div>
            <div class="card-body">
                @if ($a->fee_note && $a->fee_status === 'unpaid')<div class="alert alert-danger py-2 small">{{ $a->fee_note }}</div>@endif
                <div class="row g-3 align-items-center">
                    @if ($pp)
                        <div class="col-md-5 text-center">
                            <div class="mx-auto bg-white p-2 rounded-3 border" style="width:210px" data-qr="{{ $pp }}" data-cell="4"></div>
                            <div class="small mt-1">สแกนจ่ายพร้อมเพย์ {{ school('promptpay_id') }}<br>ยอด {{ baht($a->fee_amount) }} บาท</div>
                        </div>
                    @endif
                    <div class="{{ $pp ? 'col-md-7' : 'col-12' }}">
                        @if (school('bank_info'))<div class="small mb-2" style="white-space:pre-line"><b>โอนเข้าบัญชี</b>
{{ school('bank_info') }}</div>@endif
                        @if ($config['fee_note'])<div class="small text-muted mb-2" style="white-space:pre-line">{{ $config['fee_note'] }}</div>@endif
                        <form method="POST" action="{{ route('apply.slip') }}" enctype="multipart/form-data" class="d-flex gap-2">
                            @csrf
                            <input type="file" name="slip" accept="image/*,.pdf" class="form-control @error('slip') is-invalid @enderror" required>
                            <button class="btn btn-primary text-nowrap"><i class="bi bi-upload"></i> {{ $a->fee_status === 'pending' ? 'ส่งใหม่' : 'แนบสลิป' }}</button>
                        </form>
                        @error('slip')<div class="text-danger small">{{ $message }}</div>@enderror
                        @if ($a->fee_status === 'pending')<div class="small text-info mt-2"><i class="bi bi-hourglass-split"></i> ได้รับสลิปแล้ว รอเจ้าหน้าที่ตรวจ</div>@endif
                    </div>
                </div>
            </div>
        </div>
    @endif
    <div class="text-center"><form method="POST" action="{{ route('apply.leave') }}">@csrf<button class="btn btn-link btn-sm">ออกจากใบสมัครนี้</button></form></div>
@endif
<div class="text-center mt-2"><a href="{{ route('apply') }}">← กลับไปหน้าสมัครเรียน</a></div>
@endsection

@push('scripts')
{{-- app.js วาด QR ให้ทุก [data-qr] เมื่อมีไลบรารีนี้ --}}
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
@endpush
