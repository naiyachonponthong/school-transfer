@extends('layouts.app')
@section('title', 'รายละเอียดใบลา')

@section('content')
<div class="page-head">
    <div>
        <a href="{{ route('leaves.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> กลับไปรายการใบลา</a>
        <h1 class="mt-2">รายละเอียดใบลา</h1>
        <div class="sub">ตรวจสอบข้อมูลก่อนพิจารณาใบลา</div>
    </div>
    <span class="badge bg-{{ $leave->statusColor() }} fs-6">{{ $leave->statusLabel() }}</span>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="stat-icon tint-{{ $leave->type === 'sick' ? 'purple' : 'info' }}"><i class="bi {{ $leave->type === 'sick' ? 'bi-thermometer-half' : 'bi-briefcase' }}"></i></div>
                    <div>
                        <div class="small text-muted">นักเรียน</div>
                        <a href="{{ route('students.show', $leave->student) }}" class="fw-semibold fs-5 text-body text-decoration-none">{{ $leave->student->fullName() }}</a>
                        <div class="small text-muted">{{ $leave->student->classroom?->name() ?? 'ไม่ระบุห้องเรียน' }}</div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-sm-4"><div class="small text-muted mb-1">ประเภทการลา</div><div class="fw-semibold">{{ $leave->typeLabel() }}</div></div>
                    <div class="col-sm-5"><div class="small text-muted mb-1">ช่วงวันที่ลา</div><div class="fw-semibold">{{ thai_date($leave->start_date) }}@if($leave->days() > 1) – {{ thai_date($leave->end_date) }}@endif</div></div>
                    <div class="col-sm-3"><div class="small text-muted mb-1">จำนวนวัน</div><div class="fw-semibold">{{ $leave->days() }} วัน</div></div>
                </div>

                <div class="border-top pt-3">
                    <div class="small text-muted mb-2">เหตุผลการลา</div>
                    <div style="white-space:pre-line">{{ $leave->reason ?: 'ไม่ได้ระบุเหตุผล' }}</div>
                </div>

                @if ($leave->attachment)
                    <div class="border-top mt-4 pt-3">
                        <div class="small text-muted mb-2">เอกสารแนบ</div>
                        <a href="{{ route('files.show', ['leave', $leave->id]) }}" target="_blank" rel="noopener noreferrer" class="btn btn-light border"><i class="bi bi-paperclip"></i> เปิดเอกสารแนบ <i class="bi bi-box-arrow-up-right small"></i></a>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body p-4">
                <h2 class="h6 mb-3">ข้อมูลการส่งใบลา</h2>
                <div class="small text-muted">ส่งโดย</div>
                <div class="fw-semibold mb-3">{{ $leave->requester?->name ?? '-' }}</div>
                <div class="small text-muted">ส่งเมื่อ</div>
                <div class="mb-3">{{ thai_datetime($leave->created_at) }}</div>

                @if ($leave->status === 'pending')
                    <div class="border-top pt-3">
                        <div class="small text-muted mb-3">โปรดตรวจสอบเหตุผลและวันที่ลาก่อนยืนยัน</div>
                        @include('leaves._review-buttons')
                    </div>
                @else
                    <div class="border-top pt-3">
                        <div class="small text-muted">พิจารณาโดย</div>
                        <div class="fw-semibold mb-3">{{ $leave->reviewer?->name ?? '-' }}</div>
                        <div class="small text-muted">พิจารณาเมื่อ</div>
                        <div>{{ $leave->reviewed_at ? thai_datetime($leave->reviewed_at) : '-' }}</div>
                        @if ($leave->review_note)
                            <div class="small text-muted mt-3">หมายเหตุการพิจารณา</div>
                            <div style="white-space:pre-line">{{ $leave->review_note }}</div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if ($leave->status === 'pending')
    @include('leaves._review-modal')
@endif
@endsection
