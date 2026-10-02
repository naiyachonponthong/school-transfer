@extends('layouts.app')
@section('title', 'ใบสมัคร '.$a->app_no)

@section('content')
<div class="page-head">
    <div><h1>{{ $a->fullName() }}</h1><div class="sub">ใบสมัคร {{ $a->app_no }} · ชั้น {{ $a->level }} · {{ $a->submitted_at ? 'ส่งเมื่อ '.thai_datetime($a->submitted_at) : 'ยังไม่ส่ง (กรอกค้าง บันทึกล่าสุด '.thai_datetime($a->updated_at).')' }}</div></div>
    <div class="actions">
        <span class="badge bg-{{ $a->statusColor() }} fs-6 align-self-center">{{ $a->statusLabel() }}</span>
        @unless ($a->isDraft())
            <div class="dropdown">
                <button class="btn btn-light border dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-printer"></i> พิมพ์</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" target="_blank" href="{{ route('admissions.print', [$a, 'application']) }}"><i class="bi bi-file-earmark-text me-2"></i>ใบสมัคร</a></li>
                    <li><a class="dropdown-item {{ $a->fee_status === 'paid' ? '' : 'disabled' }}" target="_blank" href="{{ route('admissions.print', [$a, 'receipt']) }}"><i class="bi bi-receipt me-2"></i>ใบเสร็จค่าสมัคร</a></li>
                    <li><a class="dropdown-item {{ in_array($a->status, ['accepted', 'enrolled'], true) ? '' : 'disabled' }}" target="_blank" href="{{ route('admissions.print', [$a, 'enrollment']) }}"><i class="bi bi-file-earmark-person me-2"></i>ใบมอบตัว</a></li>
                </ul>
            </div>
        @endunless
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted fw-normal">ชื่อเล่น / เพศ</dt><dd class="col-sm-8">{{ $a->nickname ?: '-' }} · {{ ['M' => 'ชาย', 'F' => 'หญิง'][$a->gender] ?? '-' }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">วันเกิด</dt><dd class="col-sm-8">{{ $a->birthdate ? thai_date($a->birthdate, true).' (อายุ '.$a->birthdate->age.' ปี)' : '-' }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">เลขบัตรประชาชน</dt><dd class="col-sm-8">{{ $a->citizen_id }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">โรงเรียนเดิม / GPA</dt><dd class="col-sm-8">{{ $a->previous_school ?: '-' }} · {{ $a->gpa ? number_format($a->gpa, 2) : '-' }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">ผู้ปกครอง</dt><dd class="col-sm-8">{{ $a->parent_name }} ({{ $a->relation ?: '-' }}) · <a href="tel:{{ $a->parent_phone }}">{{ $a->parent_phone }}</a></dd>
                    <dt class="col-sm-4 text-muted fw-normal">ที่อยู่</dt><dd class="col-sm-8">{{ $a->address ?: '-' }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">หมายเหตุ</dt><dd class="col-sm-8">{{ $a->note ?: '-' }}</dd>
                    @if ($a->document)
                        <dt class="col-sm-4 text-muted fw-normal">เอกสาร</dt><dd class="col-sm-8 mb-0"><a href="{{ route('admissions.document', $a) }}" target="_blank"><i class="bi bi-paperclip"></i> เปิดไฟล์แนบ</a></dd>
                    @endif
                </dl>
            </div>
        </div>

        @if ($a->answers)
            <div class="card mt-3">
                <div class="card-header"><i class="bi bi-ui-checks"></i> ข้อมูลเพิ่มเติม <span class="small text-muted fw-normal ms-1">ตามคำถามในฟอร์มตอนที่สมัคร</span></div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        @foreach ($a->answers as $ans)
                            <dt class="col-sm-4 text-muted fw-normal">{{ $ans['label'] }}</dt>
                            <dd class="col-sm-8" style="white-space:pre-line">@if ($ans['type'] === 'file')<a href="{{ route('admissions.file', [$a, $ans['id']]) }}" target="_blank"><i class="bi bi-paperclip"></i> {{ \App\Support\AdmissionForm::display($ans) }}</a>@else{{ \App\Support\AdmissionForm::display($ans) }}@endif</dd>
                        @endforeach
                    </dl>
                </div>
            </div>
        @endif
    </div>
    <div class="col-lg-5">
        @if ($a->fee_amount > 0)
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-cash-coin"></i> ค่าสมัคร {{ baht($a->fee_amount) }} บาท <span class="badge bg-{{ $a->feeColor() }} ms-auto">{{ $a->feeLabel() }}</span></div>
                <div class="card-body small">
                    @if ($a->fee_slip)<a href="{{ route('admissions.file', [$a, 'slip']) }}" target="_blank" class="btn btn-sm btn-light border mb-2"><i class="bi bi-image"></i> ดูสลิป</a>@endif
                    @if ($a->fee_status === 'paid')
                        <div>ใบเสร็จ <b>{{ $a->fee_receipt_no }}</b> · {{ thai_datetime($a->fee_paid_at) }} · โดย {{ $a->feeVerifier?->name }}</div>
                    @else
                        <form method="POST" action="{{ route('admissions.fee', $a) }}" class="d-flex flex-wrap gap-2">
                            @csrf
                            <input name="fee_note" class="form-control form-control-sm" placeholder="หมายเหตุถึงผู้สมัคร (เช่น เหตุผลที่ตีกลับ)">
                            <button name="action" value="approve" class="btn btn-sm btn-success" data-confirm="{{ $a->fee_slip ? 'ยืนยันสลิปและออกใบเสร็จ?' : 'บันทึกว่ารับเงินสดแล้ว และออกใบเสร็จ?' }}"><i class="bi bi-check2"></i> {{ $a->fee_slip ? 'ยืนยันสลิป · ออกใบเสร็จ' : 'รับเงินสด · ออกใบเสร็จ' }}</button>
                            @if ($a->fee_status === 'pending')<button name="action" value="reject" class="btn btn-sm btn-outline-danger">ตีกลับสลิป</button>@endif
                        </form>
                    @endif
                </div>
            </div>
        @endif
        @unless ($a->isDraft())
            <form method="POST" action="{{ route('admissions.exam', $a) }}" class="card mb-3">
                @csrf @method('PUT')
                <div class="card-header"><i class="bi bi-door-open"></i> ห้องสอบ <span class="small text-muted fw-normal ms-1">พิมพ์ในส่วนที่ 2 ของใบสมัคร</span></div>
                <div class="card-body d-flex gap-2">
                    <input name="exam_room" value="{{ $a->exam_room }}" class="form-control form-control-sm" placeholder="ห้องสอบ เช่น 321">
                    <input name="exam_seat" value="{{ $a->exam_seat }}" class="form-control form-control-sm" placeholder="เลขที่นั่งสอบ">
                    <button class="btn btn-sm btn-light border text-nowrap">บันทึก</button>
                </div>
            </form>
        @endunless
        @if ($a->isDraft())
            <div class="alert alert-light border">ผู้สมัครยังกรอกไม่เสร็จ (ผ่านแล้ว {{ count($a->steps_done ?? []) }} ขั้น) — ยังพิจารณาไม่ได้</div>
        @elseif ($a->status === 'enrolled')
            <div class="card"><div class="card-body">มอบตัวแล้ว → <a href="{{ route('students.show', $a->student_id) }}">ดูข้อมูลนักเรียน</a></div></div>
        @else
            <form method="POST" action="{{ route('admissions.update', $a) }}" class="card mb-3">
                @csrf @method('PUT')
                <div class="card-header">ผลการพิจารณา</div>
                <div class="card-body">
                    <div class="d-grid gap-2 mb-2" style="grid-template-columns:1fr 1fr">
                        @foreach (['reviewing', 'accepted', 'rejected', 'submitted'] as $k)
                            <input type="radio" class="btn-check" name="status" value="{{ $k }}" id="st{{ $k }}" @checked($a->status === $k)>
                            <label class="btn btn-outline-{{ \App\Models\Admission::STATUSES[$k][1] === 'secondary' ? 'secondary' : \App\Models\Admission::STATUSES[$k][1] }}" for="st{{ $k }}">{{ \App\Models\Admission::STATUSES[$k][0] }}</label>
                        @endforeach
                    </div>
                    <input name="staff_note" value="{{ $a->staff_note }}" class="form-control" placeholder="ข้อความถึงผู้สมัคร (เห็นในหน้าตรวจสอบสถานะ)">
                </div>
                <div class="card-footer bg-transparent"><button class="btn btn-primary w-100">บันทึก</button></div>
            </form>
            @if ($a->status === 'accepted')
                <form method="POST" action="{{ route('admissions.enroll', $a) }}" class="card">
                    @csrf
                    <div class="card-header"><i class="bi bi-person-check text-success"></i> มอบตัว (สร้างนักเรียนในระบบ)</div>
                    <div class="card-body">
                        <label class="form-label">รหัสนักเรียน</label>
                        <input name="student_code" value="{{ $nextCode }}" class="form-control mb-2" required>
                        <label class="form-label">ห้องเรียน</label>
                        <select name="classroom_id" class="form-select"><option value="">- จัดห้องภายหลัง -</option>@foreach ($classrooms as $c)<option value="{{ $c->id }}">{{ $c->name() }}</option>@endforeach</select>
                        <div class="small text-muted mt-2">ระบบสร้างบัญชีผู้ปกครองให้ด้วย (เข้าด้วยเบอร์โทร รหัสผ่าน 6 หลักท้าย)</div>
                    </div>
                    <div class="card-footer bg-transparent"><button class="btn btn-success w-100"><i class="bi bi-check2-circle"></i> ยืนยันมอบตัว</button></div>
                </form>
            @endif
        @endif
    </div>
</div>
@endsection
