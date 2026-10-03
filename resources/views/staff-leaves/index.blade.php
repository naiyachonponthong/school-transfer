@extends('layouts.app')
@section('title', 'ลางาน')

@section('content')
<div class="page-head">
    <div><h1>ลางาน</h1><div class="sub">ปีงบประมาณ {{ thai_date($fy[0]) }} – {{ thai_date($fy[1]) }} · อนุมัติแล้วระบบลงเวลาให้อัตโนมัติ</div></div>
</div>

@if ($pending->isNotEmpty())
    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-hourglass-split text-warning"></i> รออนุมัติ <span class="badge bg-danger">{{ $pending->count() }}</span></div>
        @foreach ($pending as $l)
            <div class="d-flex flex-wrap gap-3 align-items-center px-3 py-2 border-bottom">
                <span class="sb-avatar">@if($l->user->avatarUrl())<img src="{{ $l->user->avatarUrl() }}" alt="">@else{{ $l->user->initials() }}@endif</span>
                <div class="flex-grow-1 small">
                    <div class="fw-semibold">{{ $l->user->name }} · {{ $l->typeLabel() }} {{ $l->days() }} วัน</div>
                    <div class="text-muted">{{ thai_date($l->start_date) }}@if($l->days() > 1) – {{ thai_date($l->end_date) }}@endif · {{ $l->reason }}
                        @if ($l->attachment) · <a href="{{ route('files.show', ['staff-leave', $l->id]) }}" target="_blank"><i class="bi bi-paperclip"></i> ไฟล์แนบ</a>@endif</div>
                </div>
                <form method="POST" action="{{ route('staff-leaves.reject', $l) }}" onsubmit="const n=prompt('เหตุผล (ไม่บังคับ)');if(n===null)return false;this.note.value=n">@csrf<input type="hidden" name="note"><button class="btn btn-sm btn-outline-danger">ไม่อนุมัติ</button></form>
                <form method="POST" action="{{ route('staff-leaves.approve', $l) }}">@csrf<button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> อนุมัติ</button></form>
            </div>
        @endforeach
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-5">
        <form method="POST" action="{{ route('staff-leaves.store') }}" enctype="multipart/form-data" class="card mb-3">
            @csrf
            <div class="card-header"><i class="bi bi-plus-circle text-primary"></i> ยื่นใบลา</div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach (\App\Models\StaffLeave::TYPES as $k => [$label])
                        <input type="radio" class="btn-check" name="type" value="{{ $k }}" id="lt{{ $k }}" @checked($loop->first)>
                        <label class="btn btn-sm btn-outline-primary" for="lt{{ $k }}">{{ $label }}</label>
                    @endforeach
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6"><label class="form-label">ตั้งแต่</label><input type="date" name="start_date" value="{{ today()->toDateString() }}" class="form-control" required></div>
                    <div class="col-6"><label class="form-label">ถึง</label><input type="date" name="end_date" value="{{ today()->toDateString() }}" class="form-control" required></div>
                </div>
                <label class="form-label">เหตุผล</label>
                <textarea name="reason" rows="2" class="form-control mb-2" required></textarea>
                <label class="form-label">แนบไฟล์ (ใบรับรองแพทย์ / หนังสือเชิญ)</label>
                <input type="file" name="attachment" accept="image/*,.pdf" class="form-control">
            </div>
            <div class="card-footer bg-transparent"><button class="btn btn-primary w-100">ส่งใบลา</button></div>
        </form>

        <div class="card">
            <div class="card-body">
                <div class="card-title-sm"><i class="bi bi-pie-chart text-primary"></i> วันลาที่ใช้ปีงบนี้</div>
                @foreach (\App\Models\StaffLeave::TYPES as $k => [$label, $quota])
                    @php($u = $used[$k] ?? 0)
                    <div class="small mb-2">
                        <div class="d-flex justify-content-between"><span>{{ $label }}</span><b>{{ $u }}{{ $quota ? ' / '.$quota : '' }} วัน</b></div>
                        @if ($quota)<div class="behavior-meter mt-1" style="height:6px"><span style="width:{{ min(100, $u / $quota * 100) }}%;background:{{ $u > $quota * .8 ? '#ef4444' : 'var(--sb-primary)' }}"></span></div>@endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history"></i> ใบลาของฉัน</div>
            @forelse ($mine as $l)
                <div class="d-flex gap-2 align-items-center px-3 py-2 border-bottom small">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $l->typeLabel() }} {{ $l->days() }} วัน</div>
                        <div class="text-muted">{{ thai_date($l->start_date) }}@if($l->days() > 1) – {{ thai_date($l->end_date) }}@endif · {{ $l->reason }}</div>
                        @if ($l->review_note)<div class="text-muted">หมายเหตุ: {{ $l->review_note }}</div>@endif
                    </div>
                    <span class="badge bg-{{ $l->statusColor() }}">{{ $l->statusLabel() }}</span>
                    @if ($l->status === 'pending')
                        <form method="POST" action="{{ route('staff-leaves.destroy', $l) }}" data-confirm="ยกเลิกใบลานี้?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-muted p-0"><i class="bi bi-x-lg"></i></button></form>
                    @endif
                </div>
            @empty
                <div class="empty"><i class="bi bi-briefcase"></i>ยังไม่เคยลา</div>
            @endforelse
        </div>

        @if ($history->isNotEmpty())
            <div class="card mt-3">
                <div class="card-header"><i class="bi bi-people"></i> ประวัติการอนุมัติ (ทั้งโรงเรียน)</div>
                @foreach ($history as $l)
                    <div class="d-flex gap-2 align-items-center px-3 py-2 border-bottom small">
                        <div class="flex-grow-1"><b>{{ $l->user->name }}</b> · {{ $l->typeLabel() }} {{ thai_date($l->start_date) }} ({{ $l->days() }} วัน)</div>
                        <span class="badge bg-{{ $l->statusColor() }}">{{ $l->statusLabel() }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
