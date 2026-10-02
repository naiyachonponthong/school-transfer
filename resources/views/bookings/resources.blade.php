@extends('layouts.app')
@section('title', 'ห้อง / รถ / อุปกรณ์ที่จองได้')

@section('content')
<div class="page-head">
    <div><h1>ห้อง / รถ / อุปกรณ์ที่จองได้</h1><div class="sub">{{ $resources->where('is_active', true)->count() }} รายการเปิดให้จอง · ตั้ง "ต้องอนุมัติ" ให้รายการที่ต้องผ่านงานอาคารสถานที่ก่อน (เช่น รถตู้ หอประชุม)</div></div>
    <div class="actions">
        <a href="{{ route('bookings.index') }}" class="btn btn-light border"><i class="bi bi-calendar2-week"></i> ตารางจอง</a>
        <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-plus-lg"></i> เพิ่ม</button>
            <ul class="dropdown-menu dropdown-menu-end">
                @foreach (\App\Models\BookableResource::TYPES as $k => [$label, $icon])<li><a class="dropdown-item" href="{{ route('bookings.resources.create', ['type' => $k]) }}"><i class="bi {{ $icon }} me-1"></i> {{ $label }}</a></li>@endforeach
            </ul>
        </div>
    </div>
</div>

@if ($resources->isEmpty())
    <div class="card"><div class="empty"><i class="bi bi-door-open"></i>ยังไม่มีรายการที่จองได้<div class="mt-2"><a href="{{ route('bookings.resources.create') }}" class="btn btn-primary btn-sm">เพิ่มห้องแรก</a></div></div></div>
@else
    <div class="d-flex flex-wrap gap-1 mb-3" id="typeFilter">
        <button type="button" class="btn btn-sm btn-dark" data-type="">ทั้งหมด {{ $resources->count() }}</button>
        @foreach ($resources->groupBy('type') as $type => $list)<button type="button" class="btn btn-sm btn-light border" data-type="{{ $type }}"><i class="bi {{ \App\Models\BookableResource::TYPES[$type][1] ?? 'bi-box' }}"></i> {{ \App\Models\BookableResource::TYPES[$type][0] ?? $type }} {{ $list->count() }}</button>@endforeach
    </div>
    <div class="pick-grid" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr))">
        @foreach ($resources as $r)
            <div class="pick-card compact {{ $r->is_active ? '' : 'disabled' }}" data-rtype="{{ $r->type }}">
                <div class="pc-badge d-flex gap-1">
                    <span class="badge text-bg-light border"><i class="bi {{ $r->icon() }}"></i> {{ $r->typeLabel() }}</span>
                    @if ($r->requires_approval)<span class="badge text-bg-warning">ต้องอนุมัติ</span>@endif
                    @unless ($r->is_active)<span class="badge text-bg-secondary">ปิดให้จอง</span>@endunless
                </div>
                <div class="pc-img">@if($r->photoUrl())<img src="{{ $r->photoUrl() }}" alt="" loading="lazy">@else<i class="bi {{ $r->icon() }}"></i>@endif</div>
                <div class="pc-body">
                    <div class="pc-title">{{ $r->name }}</div>
                    <div class="pc-meta">
                        @if ($r->capacity)<span class="me-2"><i class="bi bi-people"></i> {{ $r->capacity }} {{ $r->type === 'vehicle' ? 'ที่นั่ง' : ($r->type === 'equipment' ? 'ชุด' : 'คน') }}</span>@endif
                        @if ($r->plate_no)<span class="me-2"><i class="bi bi-card-text"></i> {{ $r->plate_no }}</span>@endif
                    </div>
                    @if ($r->location)<div class="pc-meta"><i class="bi bi-geo-alt"></i> {{ $r->location }}</div>@endif
                    @if ($r->contact)<div class="pc-meta"><i class="bi bi-person"></i> {{ $r->contact }}</div>@endif
                    @if ($r->amenityList())<div class="tag-list mt-1">@foreach ($r->amenityList() as $a)<span>{{ $a }}</span>@endforeach</div>@endif
                    <div class="d-flex align-items-center mt-auto pt-2">
                        <span class="small text-muted"><i class="bi bi-calendar2-check"></i> จองล่วงหน้า {{ $r->upcoming_count }}</span>
                        <a href="{{ route('bookings.create', ['resource' => $r->id]) }}" class="btn btn-sm btn-soft ms-auto me-1">จอง</a>
                        <a href="{{ route('bookings.resources.edit', $r) }}" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i> แก้ไข</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection

@push('scripts')
<script>
document.getElementById('typeFilter')?.addEventListener('click', (e) => {
    const b = e.target.closest('[data-type]'); if (!b) return;
    document.querySelectorAll('#typeFilter button').forEach((x) => x.className = 'btn btn-sm ' + (x === b ? 'btn-dark' : 'btn-light border'));
    document.querySelectorAll('[data-rtype]').forEach((c) => c.classList.toggle('d-none', !!b.dataset.type && c.dataset.rtype !== b.dataset.type));
});
</script>
@endpush
