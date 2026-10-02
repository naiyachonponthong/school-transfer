@extends('layouts.app')
@section('title', 'จองห้อง / รถ / อุปกรณ์')

@section('content')
<div class="page-head">
    <div><h1>จองห้อง / รถ / อุปกรณ์</h1><div class="sub">{{ \App\Support\Thai::fullDate($date) }}</div></div>
    <div class="actions">
        @if ($manager)<a href="{{ route('bookings.resources') }}" class="btn btn-light border"><i class="bi bi-gear"></i> รายการที่จองได้</a>@endif
        <a href="{{ route('bookings.create', ['date' => $date->toDateString()]) }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> จอง</a>
    </div>
</div>

@if ($pending->isNotEmpty())
    <div class="card mb-3 border-warning">
        <div class="card-header"><i class="bi bi-hourglass-split"></i> รออนุมัติ {{ $pending->count() }} รายการ</div>
        <div class="list-group list-group-flush">
            @foreach ($pending as $b)
                <div class="list-group-item d-flex flex-wrap gap-2 align-items-center">
                    <div class="flex-grow-1 small">
                        <b>{{ $b->resource->name }}</b> · {{ thai_date($b->starts_at) }} {{ $b->timeRange() }}
                        <div>{{ $b->title }}{{ $b->destination ? ' · ไป '.$b->destination : '' }}{{ $b->attendees ? ' · '.$b->attendees.' คน' : '' }} <span class="text-muted">โดย {{ $b->user?->name }}</span></div>
                    </div>
                    <form method="POST" action="{{ route('bookings.review', $b) }}" class="d-flex gap-1">
                        @csrf
                        <input name="review_note" class="form-control form-control-sm" placeholder="หมายเหตุ (ถ้ามี)" style="width:160px">
                        <button name="decision" value="approved" class="btn btn-sm btn-success">อนุมัติ</button>
                        <button name="decision" value="rejected" class="btn btn-sm btn-outline-danger">ไม่อนุมัติ</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endif

<form method="GET" class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <a href="{{ route('bookings.index', ['date' => $date->copy()->subDay()->toDateString(), 'type' => request('type')]) }}" class="btn btn-light border"><i class="bi bi-chevron-left"></i></a>
    <input type="date" name="date" value="{{ $date->toDateString() }}" class="form-control" style="width:auto" data-autosubmit>
    <a href="{{ route('bookings.index', ['date' => $date->copy()->addDay()->toDateString(), 'type' => request('type')]) }}" class="btn btn-light border"><i class="bi bi-chevron-right"></i></a>
    @unless ($date->isToday())<a href="{{ route('bookings.index', ['type' => request('type')]) }}" class="btn btn-link">วันนี้</a>@endunless
    <select name="type" class="form-select ms-auto" style="width:auto" data-autosubmit><option value="">ทุกประเภท</option>@foreach (\App\Models\BookableResource::TYPES as $k => [$label])<option value="{{ $k }}" @selected(request('type') === $k)>{{ $label }}</option>@endforeach</select>
</form>

<div class="row g-3">
    <div class="col-lg-8">
        @forelse ($resources as $r)
            @php($list = $bookings->get($r->id, collect()))
            <div class="card mb-2">
                <div class="card-body py-2 d-flex gap-3 align-items-start">
                    <span class="app-ico flex-shrink-0" style="width:42px;height:42px"><i class="bi {{ $r->icon() }}"></i></span>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $r->name }} <span class="small text-muted fw-normal">{{ $r->capacity ? $r->capacity.' คน' : '' }}{{ $r->requires_approval ? ' · ต้องอนุมัติ' : '' }}</span></div>
                        @forelse ($list as $b)
                            <div class="small d-flex gap-2 align-items-center mt-1">
                                <span class="badge text-bg-{{ $b->statusColor() }}">{{ $b->starts_at->isSameDay($date) ? $b->starts_at->format('H:i') : '…' }}–{{ $b->ends_at->isSameDay($date) ? $b->ends_at->format('H:i') : '…' }}</span>
                                <span class="flex-grow-1">{{ $b->title }} <span class="text-muted">· {{ $b->user?->name }}</span>@if($b->status === 'pending') <span class="text-warning-emphasis">(รออนุมัติ)</span>@endif</span>
                                @if (($b->user_id === auth()->id() || $manager) && $b->ends_at->isFuture())
                                    <form method="POST" action="{{ route('bookings.cancel', $b) }}" data-confirm="ยกเลิกการจองนี้?">@csrf<button class="btn btn-sm btn-link text-danger p-0">ยกเลิก</button></form>
                                @endif
                            </div>
                        @empty
                            <div class="small text-success mt-1">ว่างทั้งวัน</div>
                        @endforelse
                    </div>
                    <a href="{{ route('bookings.create', ['resource' => $r->id, 'date' => $date->toDateString()]) }}" class="btn btn-sm btn-soft">จอง</a>
                </div>
            </div>
        @empty
            <div class="card"><div class="empty"><i class="bi bi-calendar2-x"></i>ยังไม่มีห้อง/รถ/อุปกรณ์ให้จอง{{ $manager ? ' — เพิ่มได้ที่ "รายการที่จองได้"' : '' }}</div></div>
        @endforelse
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-person"></i> การจองของฉัน</div>
            <div class="list-group list-group-flush">
                @forelse ($mine as $b)
                    <a href="{{ route('bookings.index', ['date' => $b->starts_at->toDateString()]) }}" class="list-group-item list-group-item-action small">
                        <span class="badge text-bg-{{ $b->statusColor() }} float-end">{{ $b->statusLabel() }}</span>
                        <b>{{ $b->resource->name }}</b><div class="text-muted">{{ thai_date($b->starts_at) }} {{ $b->timeRange() }} · {{ $b->title }}</div>
                    </a>
                @empty
                    <div class="list-group-item small text-muted">ไม่มีการจองที่กำลังจะถึง</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
