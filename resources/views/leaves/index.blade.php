@extends('layouts.app')
@section('title', 'ใบลา')

@section('content')
<div class="page-head">
    <div>
        <h1>ใบลานักเรียน</h1>
        <div class="sub">อนุมัติแล้วระบบลงเช็คชื่อ "ลา" ให้อัตโนมัติ ไม่ต้องไปแก้ซ้ำ</div>
    </div>
    <div class="actions">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLeave"><i class="bi bi-plus-lg"></i> บันทึกการลา (ผู้ปกครองโทรแจ้ง)</button>
    </div>
</div>

<ul class="nav nav-pills mb-3 gap-1">
    @foreach (['pending' => 'รออนุมัติ', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ไม่อนุมัติ', 'all' => 'ทั้งหมด'] as $k => $v)
        <li class="nav-item"><a href="{{ request()->fullUrlWithQuery(['status' => $k, 'page' => null]) }}" class="nav-link {{ $status === $k ? 'active' : '' }}">{{ $v }} @if($k === 'pending' && $pendingCount)<span class="badge bg-danger ms-1">{{ $pendingCount }}</span>@endif</a></li>
    @endforeach
    @unless (auth()->user()->isAdmin())
        <li class="nav-item ms-auto"><a href="{{ request()->fullUrlWithQuery(['all' => request()->boolean('all') ? null : 1]) }}" class="nav-link">{{ request()->boolean('all') ? 'แสดงเฉพาะห้องของฉัน' : 'แสดงทุกห้อง' }}</a></li>
    @endunless
</ul>

<div class="d-flex flex-column gap-2">
    @forelse ($leaves as $l)
        <div class="card">
            <div class="card-body d-flex flex-wrap gap-3 align-items-center">
                <div class="stat-icon tint-{{ $l->type === 'sick' ? 'purple' : 'info' }}"><i class="bi {{ $l->type === 'sick' ? 'bi-thermometer-half' : 'bi-briefcase' }}"></i></div>
                <div class="flex-grow-1" style="min-width:220px">
                    <div class="fw-semibold"><a href="{{ route('leaves.show', $l) }}" class="text-body">{{ $l->student->fullName() }}</a> <span class="text-muted fw-normal small">{{ $l->student->classroom?->name() }}</span></div>
                    <div class="small">
                        <span class="badge bg-light text-dark border">{{ $l->typeLabel() }}</span>
                        {{ thai_date($l->start_date) }}@if($l->days() > 1) – {{ thai_date($l->end_date) }} ({{ $l->days() }} วัน)@endif
                    </div>
                    @if ($l->reason)<div class="small text-muted mt-1"><i class="bi bi-chat-left-quote"></i> {{ $l->reason }}</div>@endif
                    <div class="small text-muted mt-1">ส่งโดย {{ $l->requester?->name ?? '-' }} · {{ \App\Support\Thai::ago($l->created_at) }}
                        @if ($l->attachment) · <a href="{{ route('files.show', ['leave', $l->id]) }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-paperclip"></i> ไฟล์แนบ</a>@endif
                    </div>
                </div>
                <a href="{{ route('leaves.show', $l) }}" class="btn btn-light border"><i class="bi bi-eye"></i> ดูรายละเอียด</a>
                @if ($l->status === 'pending')
                    @include('leaves._review-buttons', ['leave' => $l])
                @else
                    <div class="text-end small">
                        <span class="badge bg-{{ $l->statusColor() }}">{{ $l->statusLabel() }}</span>
                        <div class="text-muted mt-1">{{ $l->reviewer?->name }} · {{ thai_date($l->reviewed_at) }}</div>
                        @if ($l->review_note)<div class="text-muted">{{ $l->review_note }}</div>@endif
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="card"><div class="empty"><i class="bi bi-inbox"></i>ไม่มีใบลา</div></div>
    @endforelse
</div>
<div class="mt-3">{{ $leaves->links() }}</div>

@include('leaves._review-modal')

<div class="modal fade" id="addLeave" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('leaves.store') }}" class="modal-content" data-confirm="ยืนยันบันทึกและอนุมัติใบลานี้? ระบบจะลงเช็คชื่อให้อัตโนมัติ">
            @csrf
            <div class="modal-header"><h5 class="modal-title">บันทึกการลา</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="form-label">รหัสหรือชื่อนักเรียน</label>
                    <select name="student_id" class="form-select" required>
                        <option value="">- เลือก -</option>
                        @foreach (\App\Models\Student::active()->with('classroom')->orderBy('classroom_id')->orderBy('number')->get() as $s)
                            <option value="{{ $s->id }}">{{ $s->classroom?->name() }} #{{ $s->number }} {{ $s->fullName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <div class="btn-group w-100">
                        @foreach (\App\Models\LeaveRequest::TYPES as $k => $v)
                            <input type="radio" class="btn-check" name="type" value="{{ $k }}" id="lt{{ $k }}" @checked($loop->first)>
                            <label class="btn btn-outline-primary" for="lt{{ $k }}">{{ $v }}</label>
                        @endforeach
                    </div>
                </div>
                <div class="col-6"><label class="form-label">ตั้งแต่</label><input type="date" name="start_date" value="{{ today()->toDateString() }}" class="form-control" required></div>
                <div class="col-6"><label class="form-label">ถึง</label><input type="date" name="end_date" value="{{ today()->toDateString() }}" class="form-control" required></div>
                <div class="col-12"><label class="form-label">เหตุผล</label><input name="reason" class="form-control"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">บันทึกและอนุมัติ</button></div>
        </form>
    </div>
</div>
@endsection
