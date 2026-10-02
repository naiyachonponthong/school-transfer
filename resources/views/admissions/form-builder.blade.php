@extends('layouts.app')
@section('title', 'ตั้งค่าฟอร์มรับสมัคร')

@section('content')
@php
    $locked = ['ระดับชั้นที่สมัคร', 'คำนำหน้า ชื่อ นามสกุล', 'เพศ', 'วันเกิด', 'เลขบัตรประชาชน', 'ชื่อผู้ปกครอง', 'เบอร์โทรผู้ปกครอง'];
@endphp
<div class="page-head">
    <div>
        <h1>ตั้งค่าฟอร์มรับสมัคร</h1>
        <div class="sub">
            @if ($isOpen)<span class="badge bg-success">เปิดรับสมัครอยู่</span>@else<span class="badge bg-secondary">ยังไม่เปิดรับ</span>@endif
            · ลิงก์สำหรับผู้ปกครอง <a href="{{ route('apply') }}" target="_blank">{{ route('apply') }}</a>
        </div>
    </div>
    <div class="actions">
        <a href="{{ route('admissions.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> ใบสมัคร</a>
        <a href="{{ route('apply', ['preview' => 1]) }}" target="_blank" class="btn btn-light border"><i class="bi bi-eye"></i> ดูฟอร์มจริง</a>
    </div>
</div>

@error('config')<div class="alert alert-danger">{{ $message }}</div>@enderror

<form method="POST" action="{{ route('admissions.form.update') }}" id="builderForm"
      data-config='@json($config)' data-types='@json(\App\Support\AdmissionForm::TYPES)'
      data-sensitive='@json(\App\Support\AdmissionForm::SENSITIVE)' data-answered='@json($answered)'
      data-max="{{ \App\Support\AdmissionForm::MAX_QUESTIONS }}">
    @csrf @method('PUT')
    <input type="hidden" name="config" id="configInput">

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-calendar-range"></i> การเปิดรับสมัคร</div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label class="form-check form-switch mt-md-4"><input type="checkbox" class="form-check-input" name="admission_open" value="1" @checked(old('admission_open', $open))> <b>เปิดรับสมัคร</b></label>
                    </div>
                    <div class="col-md-4"><label class="form-label">เปิดรับตั้งแต่ <span class="text-muted small fw-normal">(ไม่บังคับ)</span></label><input type="date" class="form-control" id="openFrom"></div>
                    <div class="col-md-4"><label class="form-label">ถึงวันที่ <span class="text-muted small fw-normal">(ไม่บังคับ)</span></label><input type="date" class="form-control" id="openUntil"></div>
                    <div class="col-12">
                        <label class="form-label">ระดับชั้นที่เปิดรับ <span class="text-muted small fw-normal">คั่นด้วยจุลภาค เช่น ป.1,ม.1,ม.4</span></label>
                        <input name="admission_levels" id="levelsInput" value="{{ old('admission_levels', implode(',', $levels)) }}" class="form-control @error('admission_levels') is-invalid @enderror" required>
                        @error('admission_levels')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">รับใบสมัครไม่เกิน (คน) <span class="text-muted small fw-normal">เว้นว่าง = ไม่จำกัด · ครบแล้วชั้นนั้นขึ้นว่า "เต็มแล้ว" (ไม่นับใบที่ไม่ผ่าน)</span></label>
                        <div class="d-flex flex-wrap gap-2" id="capsBox"></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">ข้อความหัวฟอร์ม <span class="text-muted small fw-normal">เช่น คุณสมบัติผู้สมัคร วันสอบ เอกสารที่ต้องเตรียม</span></label>
                        <textarea id="intro" rows="3" class="form-control" maxlength="2000"></textarea>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-cash-coin"></i> ค่าสมัครและเอกสาร</div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <label class="form-label">ค่าสมัคร (บาท) <span class="text-muted small fw-normal">เว้นว่างหรือ 0 = ไม่มีค่าสมัคร · ผู้สมัครชำระหลังส่งใบสมัครด้วยพร้อมเพย์/โอน แล้วแนบสลิป</span></label>
                        <div class="d-flex flex-wrap gap-2" id="feesBox"></div>
                        <div class="form-text">QR พร้อมเพย์และบัญชีธนาคารใช้ตามที่ตั้งไว้ใน <a href="{{ route('settings') }}">ตั้งค่าโรงเรียน</a>{{ school('promptpay_id') ? ' (พร้อมเพย์ '.school('promptpay_id').')' : ' — ยังไม่ได้ตั้งพร้อมเพย์' }}</div>
                    </div>
                    <div class="col-12"><label class="form-label">ข้อความเรื่องการชำระเงิน <span class="text-muted small fw-normal">(ไม่บังคับ) เช่น ชำระภายใน 3 วันหลังสมัคร</span></label>
                        <textarea id="feeNote" rows="2" class="form-control" maxlength="1000"></textarea></div>
                    <div class="col-12">
                        <label class="form-check form-switch"><input type="checkbox" class="form-check-input" id="examSlip"> พิมพ์ <b>ส่วนที่ 2 สำหรับผู้สมัคร</b> (ห้องสอบ/เลขที่นั่งสอบ) ท้ายใบสมัคร</label>
                    </div>
                    <div class="col-12"><label class="form-label">ข้อความรับรองในใบมอบตัว</label>
                        <textarea id="pledge" rows="4" class="form-control small" maxlength="3000"></textarea></div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-lock"></i> ช่องหลัก <span class="small text-muted fw-normal ms-1">บังคับกรอกเสมอ แก้ไม่ได้</span></div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        @foreach ($locked as $f)<span class="badge bg-light text-dark border fw-normal"><i class="bi bi-lock-fill text-muted"></i> {{ $f }}</span>@endforeach
                    </div>
                    <div class="small text-muted">ใช้ตอน "มอบตัว" สร้างข้อมูลนักเรียนและบัญชีผู้ปกครองให้อัตโนมัติ และใช้ตรวจสถานะใบสมัคร (เลขที่ใบสมัคร + เบอร์โทร)</div>
                </div>
                <div class="card-header border-top"><i class="bi bi-toggles"></i> ช่องเสริม</div>
                <div class="list-group list-group-flush" id="optionalBox">
                    @foreach (\App\Support\AdmissionForm::OPTIONAL_FIELDS as $key => [$label])
                        <div class="list-group-item d-flex flex-wrap align-items-center gap-2">
                            <span class="flex-grow-1">{{ $label }}</span>
                            <div class="btn-group btn-group-sm" role="group" aria-label="{{ $label }}">
                                @foreach (['show' => 'แสดง', 'required' => 'บังคับกรอก', 'hidden' => 'ซ่อน'] as $mode => $t)
                                    <input type="radio" class="btn-check" name="opt_{{ $key }}" id="opt_{{ $key }}_{{ $mode }}" value="{{ $mode }}" data-opt="{{ $key }}" @checked($config['optional'][$key] === $mode)>
                                    <label class="btn btn-outline-primary" for="opt_{{ $key }}_{{ $mode }}">{{ $t }}</label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header flex-wrap gap-2">
                    <i class="bi bi-ui-checks"></i> คำถามของโรงเรียน <span class="small text-muted fw-normal" id="qCount"></span>
                    <div class="dropdown ms-auto">
                        <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-plus-lg"></i> เพิ่มคำถาม</button>
                        <ul class="dropdown-menu dropdown-menu-end" id="addMenu"></ul>
                    </div>
                </div>
                <div class="card-body" id="qList"></div>
                <div class="card-footer bg-white small text-muted">
                    <i class="bi bi-lightning"></i> เพิ่มเร็ว:
                    <span id="templates" class="d-inline-flex flex-wrap gap-1"></span>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card builder-preview">
                <div class="card-header"><i class="bi bi-phone"></i> ตัวอย่างคำถาม
                    <select class="form-select form-select-sm w-auto ms-auto" id="previewLevel" aria-label="ดูตัวอย่างของระดับชั้น"></select>
                </div>
                <div class="card-body" id="preview"></div>
            </div>
            <div class="alert alert-light border small mt-3">
                <i class="bi bi-shield-lock"></i> <b>PDPA:</b> ถามเท่าที่จำเป็นต่อการรับสมัคร ข้อมูลอ่อนไหว (ศาสนา สุขภาพ ความพิการ ฯลฯ) ต้องมีเหตุผลที่จำเป็นจริงและแจ้งวัตถุประสงค์ในคำอธิบายคำถาม
            </div>
            <div class="alert alert-light border small">
                <i class="bi bi-clock-history"></i> แก้ฟอร์มได้ตลอด ใบสมัครที่ส่งแล้วเก็บคำถาม–คำตอบตามตอนที่ส่งไว้ ไม่เปลี่ยนตาม
            </div>
        </div>
    </div>

    <div class="builder-savebar">
        <div class="d-flex align-items-center gap-3 bg-white border rounded-4 p-2 ps-3 shadow-sm">
            <span class="small" id="dirtyState"></span>
            <button class="btn btn-primary ms-auto px-4"><i class="bi bi-save"></i> บันทึกฟอร์ม</button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/admission-builder.js') }}?v={{ filemtime(public_path('assets/js/admission-builder.js')) }}" defer></script>
@endpush
