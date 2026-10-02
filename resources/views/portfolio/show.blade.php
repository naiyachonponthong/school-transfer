@extends('layouts.app')
@section('title', 'แฟ้มผลงาน '.$student->fullName())

@section('content')
@php
    $me = auth()->user();
@endphp
<div class="card child-card mb-3">
    <div class="head">
        <span class="sb-avatar">@if($student->photoUrl())<img src="{{ $student->photoUrl() }}" alt="">@else{{ $student->initials() }}@endif</span>
        <div class="flex-grow-1">
            <div class="fw-bold fs-5">แฟ้มสะสมผลงาน</div>
            <div class="small opacity-75">{{ $student->fullName() }} · ห้อง {{ $student->classroom?->name() }}</div>
        </div>
        <button class="btn btn-light btn-sm no-print" onclick="print()"><i class="bi bi-printer"></i> พิมพ์</button>
    </div>
    <div class="card-body row text-center g-2">
        @foreach (\App\Models\StudentWork::CATEGORIES as $k => [$label, $icon, $color])
            <div class="col-3"><div class="fs-4 fw-bold text-{{ $color }}">{{ $counts[$k] ?? 0 }}</div><div class="small text-muted">{{ $label }}</div></div>
        @endforeach
        <div class="col-12 small">ชั่วโมงกิจกรรม/จิตอาสาที่รับรองแล้ว <b class="text-primary">{{ rtrim(rtrim(number_format($hours, 1), '0'), '.') ?: 0 }} ชั่วโมง</b></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4 no-print">
        <form method="POST" action="{{ route('portfolio.store', $student) }}" enctype="multipart/form-data" class="card">
            @csrf
            <div class="card-header"><i class="bi bi-plus-circle text-primary"></i> เพิ่มผลงาน</div>
            <div class="card-body">
                <div class="d-grid gap-2 mb-2" style="grid-template-columns:1fr 1fr">
                    @foreach (\App\Models\StudentWork::CATEGORIES as $k => [$label, $icon])
                        <input type="radio" class="btn-check" name="category" value="{{ $k }}" id="wc{{ $k }}" @checked($loop->first)>
                        <label class="btn btn-sm btn-outline-primary" for="wc{{ $k }}"><i class="bi {{ $icon }}"></i> {{ $label }}</label>
                    @endforeach
                </div>
                <input name="title" class="form-control mb-2" placeholder="ชื่อผลงาน / รางวัล" required>
                <textarea name="description" rows="2" class="form-control mb-2" placeholder="รายละเอียด"></textarea>
                <div class="row g-2 mb-2">
                    <div class="col-7"><select name="level" class="form-select form-select-sm"><option value="">ระดับ -</option>@foreach (\App\Models\StudentWork::LEVELS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                    <div class="col-5"><input type="number" step="0.5" name="hours" class="form-control form-control-sm" placeholder="ชั่วโมง"></div>
                    <div class="col-12"><input type="date" name="date" value="{{ today()->toDateString() }}" class="form-control form-control-sm"></div>
                </div>
                <input type="file" name="image" accept="image/*" class="form-control form-control-sm mb-2">
                @if ($me->isStaff())<label class="form-check small"><input type="checkbox" name="share" value="1" class="form-check-input"> แชร์รางวัลลงฟีดข่าวโรงเรียน</label>@endif
                @unless ($me->isStaff())<div class="small text-muted">ผลงานที่ผู้ปกครองเพิ่มจะแสดงหลังครูรับรอง</div>@endunless
            </div>
            <div class="card-footer bg-transparent"><button class="btn btn-primary w-100">บันทึก</button></div>
        </form>
    </div>
    <div class="col-lg-8">
        <div class="row g-3">
            @forelse ($works as $w)
                <div class="col-md-6">
                    <div class="card h-100 overflow-hidden">
                        @if ($w->imageUrl())<img src="{{ $w->imageUrl() }}" alt="" style="height:170px;object-fit:cover;width:100%">@endif
                        <div class="card-body">
                            <div class="d-flex gap-2 align-items-start">
                                <span class="stat-icon tint-{{ $w->categoryColor() }}" style="width:38px;height:38px;font-size:1rem"><i class="bi {{ $w->categoryIcon() }}"></i></span>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-bold">{{ $w->title }}</div>
                                    <div class="small text-muted">{{ $w->categoryLabel() }}@if($w->level) · {{ \App\Models\StudentWork::LEVELS[$w->level] }}@endif @if($w->date)· {{ thai_date($w->date) }}@endif @if($w->hours)· {{ $w->hours }} ชม.@endif</div>
                                </div>
                                @unless ($w->verified)<span class="badge bg-warning-subtle text-warning-emphasis">รอรับรอง</span>@endunless
                            </div>
                            @if ($w->description)<div class="small mt-2" style="white-space:pre-line">{{ $w->description }}</div>@endif
                            <div class="d-flex gap-2 mt-2 no-print">
                                @if (! $w->verified && $me->isStaff())
                                    <form method="POST" action="{{ route('portfolio.verify', $w) }}">@csrf<button class="btn btn-sm btn-success">รับรอง</button></form>
                                @endif
                                @if ($me->isAdmin() || $w->recorded_by === $me->id)
                                    <form method="POST" action="{{ route('portfolio.destroy', $w) }}" data-confirm="ลบผลงานนี้?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-muted p-0"><i class="bi bi-trash"></i> ลบ</button></form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="card"><div class="empty"><i class="bi bi-folder2-open"></i>ยังไม่มีผลงานในแฟ้ม</div></div></div>
            @endforelse
        </div>
    </div>
</div>
@endsection
