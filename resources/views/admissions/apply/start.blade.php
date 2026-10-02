@extends('layouts.public')
@section('title', 'สมัครเรียนออนไลน์')
@section('heading', 'สมัครเรียนออนไลน์ ปีการศึกษา '.$year)
@section('subheading', 'กรอกทีละขั้น บันทึกให้อัตโนมัติ ปิดแล้วกลับมากรอกต่อได้')

@section('content')
@php($available = array_values(array_diff($levels, $full)))

@if ($preview)
    <div class="alert alert-warning"><i class="bi bi-eye"></i> <b>ตัวอย่างหน้าสมัคร</b> — ผู้ปกครองจะเห็นหน้านี้{{ $closedReason ? ' (ตอนนี้ยังไม่เปิดรับจริง: '.$closedReason.')' : '' }}</div>
@endif

@if ($current && $current->isDraft())
    <div class="card mb-3 border-primary">
        <div class="card-body d-flex flex-wrap align-items-center gap-2">
            <i class="bi bi-pencil-square text-primary fs-4"></i>
            <div class="flex-grow-1">
                <div class="fw-semibold">กำลังกรอกใบสมัคร {{ $current->app_no }}</div>
                <div class="small text-muted">{{ $current->fullName() }} · ชั้น {{ $current->level }} · บันทึกล่าสุด {{ thai_datetime($current->updated_at) }}</div>
            </div>
            <a href="{{ route('apply.step', 'review') }}" class="btn btn-primary">กรอกต่อ <i class="bi bi-arrow-right"></i></a>
            <form method="POST" action="{{ route('apply.leave') }}">@csrf<button class="btn btn-link">ออก</button></form>
        </div>
    </div>
@elseif ($current)
    <div class="alert alert-info">ใบสมัคร {{ $current->app_no }} ส่งแล้ว — <a href="{{ route('apply.status') }}">ดูสถานะและพิมพ์เอกสาร</a></div>
@endif

@if ($config['intro'])
    <div class="card mb-3"><div class="card-body" style="white-space:pre-line">{{ $config['intro'] }}</div></div>
@endif

@if ($closedReason && ! $preview)
    <div class="card"><div class="empty"><i class="bi bi-door-closed"></i>{{ $closedReason }}</div></div>
@elseif (! $available)
    <div class="card"><div class="empty"><i class="bi bi-people-fill"></i>รับสมัครครบทุกระดับชั้นแล้ว ขอบคุณที่ให้ความสนใจ</div></div>
@else
    <div class="card mb-3">
        <div class="card-body">
            <div class="fw-semibold mb-2"><i class="bi bi-list-ol text-primary"></i> ขั้นตอนการสมัคร</div>
            <div class="d-flex flex-wrap gap-2 small">
                @foreach ($previewSteps as $i => $s)
                    <span class="badge bg-light text-dark border fw-normal py-2"><b>{{ $i + 1 }}</b> {{ \App\Support\AdmissionForm::STEPS[$s][0] }}</span>
                @endforeach
                <span class="badge bg-light text-dark border fw-normal py-2"><b>{{ count($previewSteps) + 1 }}</b> ตรวจสอบและยืนยัน</span>
            </div>
            @if ($config['fees'])
                <div class="small text-muted mt-2"><i class="bi bi-cash-coin"></i> ค่าสมัคร:
                    {{ collect($levels)->map(fn ($l) => $l.' '.(isset($config['fees'][$l]) ? baht($config['fees'][$l], 0).' บาท' : 'ไม่มีค่าสมัคร'))->implode(' · ') }}
                    — ชำระหลังส่งใบสมัคร ผ่านพร้อมเพย์/โอน แล้วแนบสลิป</div>
            @endif
            @if ($config['open_until'])
                <div class="small text-muted mt-1"><i class="bi bi-calendar-check"></i> รับสมัครถึงวันที่ {{ thai_date(\Illuminate\Support\Carbon::parse($config['open_until'])) }}</div>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('apply.begin') }}" class="card">
        @csrf
        <input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off">
        <div class="card-body">
            <div class="card-title-sm"><i class="bi bi-play-circle text-primary"></i> เริ่มสมัคร หรือ กลับมากรอกต่อ</div>
            <p class="small text-muted">ใช้ข้อมูล 3 อย่างนี้เปิดใบสมัครของท่านทุกครั้ง (กรอกค้างไว้ก็กลับมาต่อได้ ไม่ต้องเริ่มใหม่)</p>

            <label class="form-label">ระดับชั้นที่สมัคร *</label>
            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach ($levels as $l)
                    @php($isFull = in_array($l, $full, true))
                    <input type="radio" class="btn-check" name="level" value="{{ $l }}" id="lv{{ $loop->index }}" @checked(old('level', $available[0] ?? null) === $l) @disabled($isFull)>
                    <label class="btn btn-outline-primary px-4" for="lv{{ $loop->index }}">{{ $l }}@if ($isFull) <small>(เต็มแล้ว)</small>@endif</label>
                @endforeach
            </div>
            @error('level')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

            <div class="row g-2">
                <div class="col-md-5"><label class="form-label">เลขประจำตัวประชาชนผู้สมัคร *</label>
                    <input name="citizen_id" value="{{ old('citizen_id') }}" class="form-control @error('citizen_id') is-invalid @enderror" inputmode="numeric" maxlength="13" autocomplete="off" required>
                    @error('citizen_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-3"><label class="form-label">วันเกิดผู้สมัคร *</label>
                    <input type="date" name="birthdate" value="{{ old('birthdate') }}" class="form-control @error('birthdate') is-invalid @enderror" required>
                    @error('birthdate')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label class="form-label">เบอร์โทรผู้ปกครอง *</label>
                    <input name="parent_phone" value="{{ old('parent_phone') }}" class="form-control @error('parent_phone') is-invalid @enderror" inputmode="tel" placeholder="08xxxxxxxx" required>
                    @error('parent_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            </div>
            <label class="form-check mt-3 small">
                <input type="checkbox" name="consent" value="1" class="form-check-input @error('consent') is-invalid @enderror" @checked(old('consent')) required>
                ยินยอมให้โรงเรียนเก็บและใช้ข้อมูลในใบสมัครนี้ เพื่อการรับสมัครและจัดทำทะเบียนนักเรียนเท่านั้น
            </label>
            @error('consent')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="card-footer bg-transparent">
            <button class="btn btn-primary btn-lg w-100" @disabled($preview && $closedReason)>เริ่ม / กรอกต่อ <i class="bi bi-arrow-right"></i></button>
        </div>
    </form>
@endif
<div class="text-center mt-3"><a href="{{ route('apply.status') }}"><i class="bi bi-search"></i> ตรวจสอบสถานะ / พิมพ์ใบสมัคร / ชำระค่าสมัคร</a></div>
@endsection
