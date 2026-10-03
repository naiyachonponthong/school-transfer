@extends('layouts.app')
@section('title', 'ปฏิทินโรงเรียน')

@push('head')
<style>
    .mcal { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); border-top: 1px solid #f0f1f4; border-left: 1px solid #f0f1f4; }
    .mcal > div { border-right: 1px solid #f0f1f4; border-bottom: 1px solid #f0f1f4; min-height: 104px; padding: 4px; font-size: .78rem; }
    .mcal .dow { min-height: auto; text-align: center; font-weight: 600; color: var(--sb-muted); background: #fafbfc; padding: 6px; }
    .mcal .out { background: #fafbfc; color: #c0c4cc; }
    .mcal .num { font-weight: 600; display: inline-block; width: 24px; height: 24px; line-height: 24px; text-align: center; border-radius: 50%; }
    .mcal .today .num { background: var(--sb-primary); color: #fff; }
    .mcal .ev { display: block; border-radius: 6px; padding: 1px 5px; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500; }
    @media (max-width: 767px) { .mcal > div { min-height: 64px; } .mcal .ev { font-size: 0; height: 6px; padding: 0; } }
</style>
@endpush

@section('content')
@php
    $admin = auth()->user()->hasPermission('academics.manage');
    $key = $month->format('Y-m');
@endphp
<div class="page-head">
    <div><h1>ปฏิทินโรงเรียน</h1><div class="sub">{{ \App\Support\Thai::monthYear($month->month, $month->year) }}</div></div>
    <div class="actions">
        <a href="?month={{ $month->copy()->subMonth()->format('Y-m') }}" class="btn btn-light border"><i class="bi bi-chevron-left"></i></a>
        <a href="?month={{ today()->format('Y-m') }}" class="btn btn-light border">วันนี้</a>
        <a href="?month={{ $month->copy()->addMonth()->format('Y-m') }}" class="btn btn-light border"><i class="bi bi-chevron-right"></i></a>
        @if ($admin)<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#evNew"><i class="bi bi-plus-lg"></i> เพิ่มกิจกรรม</button>@endif
    </div>
</div>
<div class="row g-3">
    <div class="col-xl-9">
        <div class="card overflow-hidden">
            <div class="mcal">
                @foreach (['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'] as $d)<div class="dow">{{ $d }}</div>@endforeach
                @foreach ($days as $d)
                    @php($ds = $d->toDateString())
                    <div class="{{ $d->format('Y-m') !== $key ? 'out' : '' }} {{ $d->isToday() ? 'today' : '' }}">
                        <span class="num">{{ $d->day }}</span>
                        @foreach ($events->filter(fn ($e) => $e->covers($ds)) as $e)
                            <span class="ev bg-{{ $e->typeColor() }}-subtle text-{{ $e->typeColor() }}-emphasis" title="{{ $e->title }}">{{ $e->title }}</span>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
        <div class="d-flex flex-wrap gap-3 small mt-2">
            @foreach (\App\Models\SchoolEvent::TYPES as [$label, $color, $icon])<span><span class="badge bg-{{ $color }}">&nbsp;</span> {{ $label }}</span>@endforeach
        </div>
    </div>
    <div class="col-xl-3">
        <div class="card">
            <div class="card-body pb-2">
                <div class="card-title-sm"><i class="bi bi-calendar-event text-primary"></i> กิจกรรมที่จะถึง</div>
                @forelse ($upcoming as $e)
                    <div class="d-flex gap-2 py-2 border-top align-items-start">
                        <span class="stat-icon tint-{{ $e->typeColor() }}" style="width:36px;height:36px;font-size:1rem"><i class="bi {{ $e->typeIcon() }}"></i></span>
                        <div class="flex-grow-1 small min-w-0">
                            <div class="fw-semibold">{{ $e->title }} @if($e->audience === 'staff')<span class="badge bg-light text-muted border">ครู</span>@endif</div>
                            <div class="text-muted">{{ thai_date($e->start_date) }}@if(! $e->end_date->eq($e->start_date)) – {{ thai_date($e->end_date) }}@endif</div>
                            @if ($e->description)<div class="text-muted">{{ $e->description }}</div>@endif
                        </div>
                        @if ($admin)
                            <form method="POST" action="{{ route('calendar.destroy', $e) }}" data-confirm="ลบกิจกรรมนี้?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-muted p-0"><i class="bi bi-x-lg"></i></button></form>
                        @endif
                    </div>
                @empty
                    <div class="text-muted small pb-2">ยังไม่มีกิจกรรม</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@if ($admin)
<div class="modal fade" id="evNew" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="{{ route('calendar.store') }}" class="modal-content">
        @csrf
        <div class="modal-header"><h5 class="modal-title">เพิ่มกิจกรรม</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-2">
            <div class="col-12"><label class="form-label">ชื่อกิจกรรม</label><input name="title" class="form-control" required></div>
            <div class="col-6"><label class="form-label">วันเริ่ม</label><input type="date" name="start_date" value="{{ today()->toDateString() }}" class="form-control" required></div>
            <div class="col-6"><label class="form-label">วันสิ้นสุด</label><input type="date" name="end_date" class="form-control"></div>
            <div class="col-6"><label class="form-label">ประเภท</label><select name="type" class="form-select">@foreach (\App\Models\SchoolEvent::TYPES as $k => [$l])<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            <div class="col-6"><label class="form-label">ใครเห็น</label><select name="audience" class="form-select"><option value="all">ทุกคน (รวมผู้ปกครอง)</option><option value="staff">เฉพาะครู</option></select></div>
            <div class="col-12"><label class="form-label">รายละเอียด</label><textarea name="description" rows="2" class="form-control"></textarea></div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">บันทึก</button></div>
    </form></div>
</div>
@endif
@endsection
