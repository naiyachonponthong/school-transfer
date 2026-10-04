@extends('layouts.app')
@section('title', 'หน้าหลัก')
@section('body_class', 'has-hero')

@section('content')
@php
    $u = auth()->user();
    $hour = now()->hour;
    $greet = $hour < 11 ? 'อรุณสวัสดิ์' : ($hour < 16 ? 'สวัสดีตอนบ่าย' : 'สวัสดีตอนเย็น');
    $statusIcon = ['present' => 'bi-check-circle-fill', 'late' => 'bi-clock-fill', 'absent' => 'bi-x-circle-fill', 'leave' => 'bi-envelope-paper-fill', 'sick' => 'bi-thermometer-half'];
@endphp

<div class="row g-3">
    <div class="col-lg-5 col-xl-4">
        <div class="m-hero">
            <div class="top">
                <span class="sb-logo" style="width:36px;height:36px;font-size:1.1rem;box-shadow:none;background:rgba(255,255,255,.2)"><i class="bi bi-mortarboard-fill"></i></span>
                <span class="fw-semibold small">{{ school('school_short') }}</span>
                <span class="ms-auto"></span>
                <a href="{{ route('notifications') }}" class="icon-btn"><i class="bi bi-bell"></i>@if($navUnread)<span class="dot">{{ $navUnread }}</span>@endif</a>
                <a href="{{ route('profile') }}"><span class="sb-avatar sm" style="background:rgba(255,255,255,.25);color:#fff">@if($u->avatarUrl())<img src="{{ $u->avatarUrl() }}" alt="">@else{{ $u->initials() }}@endif</span></a>
            </div>
            <div class="hello">{{ $greet }}, คุณ{{ $u->firstName() }}<small>{{ \App\Support\Thai::fullDate(today()) }}</small></div>
            <img class="m-hero-art" src="{{ asset('assets/img/school-community-hero.png') }}" alt="" aria-hidden="true" width="1536" height="1024">
        </div>

        {{-- การ์ดลูกแต่ละคน ซ้อนบนหัวแอป เลื่อนซ้าย-ขวาได้ถ้ามีหลายคน --}}
        <div class="{{ $children->count() > 1 ? 'hscroll hscroll-fit' : '' }} m-float" style="box-shadow:none">
            @forelse ($children as $child)
                @php($st = $todayStatus[$child->id] ?? null)
                <a href="{{ route('parent.child', $child) }}" class="card text-body d-block" style="box-shadow:var(--sb-shadow-lg);border-radius:20px">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="sb-avatar">@if($child->photoUrl())<img src="{{ $child->photoUrl() }}" alt="">@else{{ $child->initials() }}@endif</span>
                                <div class="min-w-0">
                                    <div class="fw-bold text-truncate">น้อง{{ $child->nickname ?: $child->first_name }}</div>
                                    <div class="small text-muted">ห้อง {{ $child->classroom?->name() }} · เลขที่ {{ $child->number }}</div>
                                </div>
                            </div>
                            @if ($st)
                                <div class="today-status py-2 bg-{{ \App\Models\Attendance::color($st->status) }}-subtle text-{{ \App\Models\Attendance::color($st->status) }}-emphasis">
                                    <i class="bi {{ $statusIcon[$st->status] ?? 'bi-info-circle-fill' }}"></i>
                                    <span>วันนี้{{ \App\Models\Attendance::label($st->status) }}@if($st->checked_at) <span class="fw-normal small">{{ substr($st->checked_at, 0, 5) }} น.</span>@endif
                                        @if($st->checkout_at)<span class="fw-normal small">· กลับ {{ substr($st->checkout_at, 0, 5) }} น.</span>@endif</span>
                                </div>
                            @else
                                <div class="today-status py-2 bg-light text-muted"><i class="bi bi-hourglass-split"></i> <span>ครูยังไม่ได้เช็คชื่อ</span></div>
                            @endif
                        </div>
                        <x-ring :value="$rates[$child->id] ?? 0" :size="86" :label="isset($rates[$child->id]) ? $rates[$child->id].'%' : '-'" sub="มาเรียน" color="#10b981" />
                    </div>
                </a>
            @empty
                <div class="card"><div class="empty"><i class="bi bi-person-exclamation"></i>ยังไม่ได้ผูกบัญชีกับนักเรียน<br>กรุณาติดต่อครูประจำชั้น</div></div>
            @endforelse
        </div>

        <div class="card m-section">
            <div class="card-body">
                <div class="m-grid">
                    @foreach (\App\Support\Menu::launcher($u) as $item)<x-app-tile :item="$item" />@endforeach
                </div>
            </div>
        </div>

        @if ($unpaid->isNotEmpty())
            <a href="{{ route('invoices.show', $unpaid->first()) }}" class="card highlight-card m-section">
                <span class="hi" style="background:linear-gradient(145deg,#2dd4bf,#0d9488)"><i class="bi bi-wallet2"></i></span>
                <div>
                    <div class="fw-bold">ค่าธรรมเนียมค้างชำระ {{ baht($unpaid->sum(fn ($i) => $i->balance())) }} บาท</div>
                    <div class="small text-muted">{{ $unpaid->count() }} รายการ · แตะเพื่อดูช่องทางชำระ</div>
                </div>
            </a>
        @endif

        @include('partials.upcoming-events', ['class' => 'm-section'])

        @if ($leaves->isNotEmpty())
            <div class="card m-section">
                <div class="card-body pb-2">
                    <div class="card-title-sm"><i class="bi bi-envelope-paper text-primary"></i> ใบลาที่ส่ง <a href="{{ route('parent.leave') }}" class="ms-auto small fw-normal">+ ส่งใบลา</a></div>
                    @foreach ($leaves as $l)
                        <div class="d-flex align-items-center gap-2 py-2 border-top small">
                            <div class="flex-grow-1">
                                <div class="fw-semibold">น้อง{{ $l->student->nickname ?: $l->student->first_name }} · {{ $l->typeLabel() }}</div>
                                <div class="text-muted">{{ thai_date($l->start_date) }}@if($l->days() > 1) – {{ thai_date($l->end_date) }}@endif</div>
                            </div>
                            <span class="badge bg-{{ $l->statusColor() }}">{{ $l->statusLabel() }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-7 col-xl-8">
        <div class="m-section-title d-lg-none mt-2">ข่าวสารจากโรงเรียน <a href="{{ route('feed.index') }}">ดูทั้งหมด</a></div>
        <div class="card overflow-hidden" data-feed>
            <div class="card-header d-none d-lg-flex"><i class="bi bi-newspaper text-primary"></i> ข่าวสารจากโรงเรียน <a href="{{ route('feed.index') }}" class="ms-auto small">ทั้งหมด</a></div>
            @include('feed.list', ['posts' => $posts])
        </div>
    </div>
</div>
@endsection
