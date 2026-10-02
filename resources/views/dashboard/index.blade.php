@extends('layouts.app')
@section('title', 'หน้าหลัก')
@section('body_class', 'has-hero')

@section('content')
@php
    $u = auth()->user();
    $came = ($todayCounts['present'] ?? 0) + ($todayCounts['late'] ?? 0);
    $pct = $todayTotal ? round($came / $todayTotal * 100, 1) : null;
    $hour = now()->hour;
    $greet = $hour < 11 ? 'อรุณสวัสดิ์' : ($hour < 16 ? 'สวัสดีตอนบ่าย' : 'สวัสดีตอนเย็น');
    $times = \App\Support\Settings::periodTimes();
    $in = $myCheckin?->check_in ? substr($myCheckin->check_in, 0, 5) : null;
    $out = $myCheckin?->check_out ? substr($myCheckin->check_out, 0, 5) : null;
    $worked = $in ? \Illuminate\Support\Carbon::parse($myCheckin->check_in)->diffInMinutes($out ? \Illuminate\Support\Carbon::parse($myCheckin->check_out) : now()) : 0;
    $myNotChecked = $myClassrooms->filter(fn ($c) => $notChecked->contains('id', $c->id));
    $launcher = \App\Support\Menu::launcher($u, $navPendingLeaves);
@endphp

{{-- ================= มือถือ: หัวแอป + การ์ดซ้อน + ไอคอน ================= --}}
<div class="d-lg-none">
    <div class="m-hero">
        <div class="top">
            <span class="sb-logo" style="width:36px;height:36px;font-size:1.1rem;box-shadow:none;background:rgba(255,255,255,.2)"><i class="bi bi-mortarboard-fill"></i></span>
            <span class="fw-semibold small">{{ school('school_short') }}</span>
            <span class="ms-auto"></span>
            <button type="button" class="icon-btn" data-search-open><i class="bi bi-search"></i></button>
            <a href="{{ route('notifications') }}" class="icon-btn"><i class="bi bi-bell"></i>@if($navUnread)<span class="dot">{{ $navUnread }}</span>@endif</a>
            <a href="{{ route('profile') }}"><span class="sb-avatar sm" style="background:rgba(255,255,255,.25);color:#fff">@if($u->avatarUrl())<img src="{{ $u->avatarUrl() }}" alt="">@else{{ $u->initials() }}@endif</span></a>
        </div>
        <div class="hello">{{ $greet }}, {{ $u->firstName() }}<small>{{ $u->position ?: $u->roleLabel() }}</small></div>
        <img class="m-hero-art" src="{{ asset('assets/img/school-community-hero.png') }}" alt="" aria-hidden="true" width="1536" height="1024">
    </div>

    <div class="card m-float">
        <div class="card-body d-flex align-items-center gap-3">
            <div class="flex-grow-1">
                <div class="small text-muted">{{ \App\Support\Thai::fullDate(today()) }}</div>
                @if ($in)
                    <div class="fs-2 fw-bold lh-1 my-2">{{ $in }} - {{ $out ?? '--:--' }}</div>
                    <form method="POST" action="{{ route('checkin.store') }}" data-geo>@csrf <input type="hidden" name="action" value="out">
                        <button class="btn btn-sm btn-soft"><i class="bi bi-box-arrow-right"></i> {{ $out ? 'ลงเวลาออกใหม่' : 'ลงเวลากลับ' }}</button>
                    </form>
                @else
                    <div class="fs-5 fw-bold my-2">ยังไม่ได้ลงเวลาเข้างาน</div>
                    <form method="POST" action="{{ route('checkin.store') }}" data-geo>@csrf <input type="hidden" name="action" value="in">
                        <button class="btn btn-primary btn-sm px-3"><i class="bi bi-fingerprint"></i> ลงเวลาเข้างาน</button>
                    </form>
                @endif
            </div>
            <x-ring :value="$in ? min(100, $worked / 480 * 100) : 0" :size="92" :label="$in ? intdiv($worked, 60).' ชม.' : '-'" :sub="$in ? ($worked % 60).' นาที' : 'ทำงาน'" color="#3b82f6" />
        </div>
    </div>

    <div class="card m-section">
        <div class="card-body">
            <div class="m-grid">
                @foreach (array_slice($launcher, 0, 7) as $item)<x-app-tile :item="$item" />@endforeach
                <x-app-tile :item="['url' => route('menu'), 'icon' => 'bi-grid-3x3-gap', 'label' => 'ทั้งหมด', 'color' => 'slate', 'badge' => 0]" />
            </div>
        </div>
    </div>

    @if ($myNotChecked->isNotEmpty() && today()->isWeekday())
        <div class="m-section chip-grid">
            @foreach ($myNotChecked as $c)
                <a href="{{ route('attendance.index', ['classroom' => $c->id]) }}" class="chip"><i class="bi bi-alarm tint-danger"></i>เช็คชื่อ {{ $c->name() }}</a>
            @endforeach
        </div>
    @endif
</div>

{{-- ================= เดสก์ท็อป: หัวหน้า ================= --}}
<div class="school-dashboard-hero d-none d-lg-flex">
    <div class="school-dashboard-hero-copy">
        <span class="school-hero-eyebrow"><i class="bi bi-stars"></i> SCHOOL OPERATIONS · TODAY</span>
        <h1>{{ $greet }}, {{ $u->firstName() }}</h1>
        <p>{{ \App\Support\Thai::fullDate(today()) }} @if($term) · {{ $term->label() }} @endif</p>
        <a href="{{ route('attendance.index') }}" class="btn btn-light"><i class="bi bi-check2-square"></i> เช็คชื่อวันนี้</a>
    </div>
    <img class="school-dashboard-hero-art" src="{{ asset('assets/img/school-community-hero.png') }}" alt="" aria-hidden="true" width="1536" height="1024">
</div>

@if (! $term)
    <div class="alert alert-warning d-flex gap-2 align-items-center">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div>ยังไม่ได้ตั้งปีการศึกษา/ภาคเรียน @if($u->isAdmin())<a href="{{ route('terms.index') }}" class="alert-link">ตั้งค่าตอนนี้</a>@endif</div>
    </div>
@endif

<div class="dash mt-3 mt-lg-0">
    {{-- ---------- ซ้าย ---------- --}}
    <div class="dash-col dash-left">
        <div class="card profile-card d-none d-lg-block">
            <div class="card-body">
                <div class="who">
                    <span class="sb-avatar xl">@if($u->avatarUrl())<img src="{{ $u->avatarUrl() }}" alt="">@else{{ $u->initials() }}@endif</span>
                    <div class="min-w-0">
                        <div class="fw-bold text-truncate">{{ $u->name }}</div>
                        <div class="small text-muted">{{ $u->position ?: $u->roleLabel() }}</div>
                        @if ($myClassrooms->isNotEmpty())<div class="small text-primary fw-semibold">ครูประจำชั้น {{ $myClassrooms->map->name()->implode(', ') }}</div>@endif
                    </div>
                </div>
                <div class="mini-stats">
                    <div><b>{{ ($myMonth['present'] ?? 0) + ($myMonth['late'] ?? 0) }}</b><span>มาทำงาน</span></div>
                    <div><b class="text-warning">{{ $myMonth['late'] ?? 0 }}</b><span>สาย</span></div>
                    <div><b class="text-info">{{ ($myMonth['leave'] ?? 0) + ($myMonth['duty'] ?? 0) }}</b><span>ลา/ราชการ</span></div>
                </div>
                <div class="time-pills">
                    <div class="time-pill {{ $in ? '' : 'empty-t' }}"><i class="bi bi-box-arrow-in-right"></i>{{ $in ?? '--:--' }}</div>
                    <span class="text-muted">-</span>
                    <div class="time-pill out {{ $out ? '' : 'empty-t' }}"><i class="bi bi-box-arrow-right"></i>{{ $out ?? '--:--' }}</div>
                </div>
                <form method="POST" action="{{ route('checkin.store') }}" class="mt-2" data-geo>@csrf
                    <input type="hidden" name="action" value="{{ $in ? 'out' : 'in' }}">
                    <button class="btn {{ $in ? 'btn-soft' : 'btn-primary' }} w-100 btn-sm"><i class="bi bi-fingerprint"></i> {{ $in ? ($out ? 'ลงเวลาออกใหม่' : 'ลงเวลากลับ') : 'ลงเวลาเข้างาน' }}</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="card-title-sm"><i class="bi bi-calendar-event text-primary"></i> {{ \App\Support\Thai::DAYS_SHORT[today()->dayOfWeek] }} {{ thai_date(today(), true) }}
                    <a href="{{ route('timetable.mine') }}" class="ms-auto small fw-normal">ตารางสอน</a></div>
                @if ($todaySlots->isNotEmpty())
                    <div class="slot-list">
                        @foreach ($todaySlots as $s)
                            <div class="slot-bar {{ $nowPeriod === $s->period ? 'now' : ($nowPeriod && $s->period < $nowPeriod ? 'gray' : '') }}">
                                <span class="t">{{ explode('-', $times[$s->period - 1] ?? '')[0] }}</span>
                                <span class="text-truncate"><b>{{ $s->course->subject->name }}</b> · {{ $s->classroom->name() }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-muted small">@if(today()->isWeekend())<i class="bi bi-sun text-warning" aria-hidden="true"></i> วันหยุด พักผ่อนให้เต็มที่@else วันนี้ไม่มีคาบสอน@endif</div>
                @endif
            </div>
        </div>

        @foreach ($myRoomsToday as $room)
            <div class="card">
                <div class="card-body">
                    <div class="card-title-sm"><i class="bi bi-people text-primary"></i> ห้อง {{ $room['classroom']->name() }} วันนี้
                        <span class="ms-auto small fw-normal {{ $room['checked'] ? 'text-success' : 'text-danger' }}">{{ $room['checked'] ? 'เช็คแล้ว '.$room['checked'].'/'.$room['total'] : 'ยังไม่เช็คชื่อ' }}</span></div>
                    @if ($room['checked'])
                        @foreach (['absent' => ['ขาดเรียน', 'danger'], 'late' => ['มาสาย', 'warning'], 'leave' => ['ลา / ป่วย', 'info']] as $k => [$label, $color])
                            <div class="team-row">
                                <span>{{ $label }} <span class="badge bg-{{ $color }}-subtle text-{{ $color }}-emphasis">{{ $room['groups'][$k]->count() }}</span></span>
                                <span class="avatar-stack">
                                    @foreach ($room['groups'][$k]->take(5) as $st)
                                        <a href="{{ route('students.show', $st) }}" class="sb-avatar xs" title="{{ $st->fullName() }}">@if($st->photoUrl())<img src="{{ $st->photoUrl() }}" alt="">@else{{ $st->initials() }}@endif</a>
                                    @endforeach
                                    @if ($room['groups'][$k]->count() > 5)<span class="sb-avatar xs">+{{ $room['groups'][$k]->count() - 5 }}</span>@endif
                                </span>
                            </div>
                        @endforeach
                    @else
                        <a href="{{ route('attendance.index', ['classroom' => $room['classroom']->id]) }}" class="btn btn-primary w-100 btn-sm mt-1"><i class="bi bi-check2-square"></i> เช็คชื่อตอนนี้</a>
                    @endif
                </div>
            </div>
        @endforeach

        @if ($u->isAdmin())
            @php
                $staffIn = $staffToday->filter(fn ($r) => $r['checkin']?->check_in);
            @endphp
            <div class="card">
                <div class="card-body pb-2">
                    <div class="card-title-sm"><i class="bi bi-people-fill text-primary"></i> การลงเวลาวันนี้
                        <span class="badge bg-light text-dark border ms-auto">{{ $staffIn->count() }}/{{ $staffToday->count() }}</span></div>
                    @foreach ($staffToday->take(10) as $row)
                        <div class="team-row border-top">
                            <span class="d-flex align-items-center gap-2 min-w-0">
                                <span class="sb-avatar xs">@if($row['user']->avatarUrl())<img src="{{ $row['user']->avatarUrl() }}" alt="">@else{{ $row['user']->initials() }}@endif</span>
                                <span class="text-truncate">{{ $row['user']->name }}</span>
                            </span>
                            @if ($row['checkin']?->check_in)
                                <span class="badge bg-success-subtle text-success-emphasis">{{ substr($row['checkin']->check_in, 0, 5) }}</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning-emphasis">ยังไม่ลงเวลา</span>
                            @endif
                        </div>
                    @endforeach
                    @if ($staffToday->count() > 10)
                        <a href="{{ route('staff-attendance.report') }}" class="d-block text-center small py-2 border-top">ดูทั้งหมด {{ $staffToday->count() }} คน →</a>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- ---------- กลาง: ฟีดข่าว ---------- --}}
    <div class="dash-col">
        @if ($myNotChecked->isNotEmpty() && today()->isWeekday())
            <div class="alert alert-warning d-none d-lg-flex flex-wrap gap-2 align-items-center mb-0">
                <i class="bi bi-bell-fill"></i>
                <div class="me-auto">วันนี้ยังไม่ได้เช็คชื่อห้อง <b>{{ $myNotChecked->map->name()->implode(', ') }}</b></div>
                @foreach ($myNotChecked as $c)<a href="{{ route('attendance.index', ['classroom' => $c->id]) }}" class="btn btn-sm btn-primary">เช็คชื่อ {{ $c->name() }}</a>@endforeach
            </div>
        @endif
        <div class="card overflow-hidden" data-feed>
            <div class="card-header"><i class="bi bi-newspaper text-primary"></i> นิวส์ฟีด <a href="{{ route('feed.index') }}" class="ms-auto small">ทั้งหมด</a></div>
            @include('feed.composer')
            @include('feed.list', ['posts' => $posts])
        </div>
    </div>

    {{-- ---------- ขวา ---------- --}}
    <div class="dash-col dash-right">
        @if ($highlight)
            <a href="{{ route('announcements.show', $highlight) }}" class="card highlight-card">
                <span class="hi"><i class="bi bi-megaphone-fill"></i></span>
                <div class="min-w-0">
                    <div class="fw-bold">{{ $highlight->title }} @if($highlight->created_at->gt(now()->subDays(3)))<span class="badge bg-danger">ใหม่</span>@endif</div>
                    <div class="small text-muted text-truncate">{{ \Illuminate\Support\Str::limit($highlight->body, 70) }}</div>
                </div>
            </a>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="card-title-sm"><i class="bi bi-clipboard-data text-primary"></i> มาเรียนวันนี้ <a href="{{ route('attendance.today') }}" class="ms-auto small fw-normal">ดูรายห้อง</a></div>
                <div class="d-flex align-items-center gap-3">
                    <x-ring :value="$pct ?? 0" :size="96" :label="$pct !== null ? round($pct).'%' : '-'" :sub="number_format($came).'/'.number_format($todayTotal)" color="#10b981" />
                    <div class="flex-grow-1 small">
                        <div class="d-flex justify-content-between"><span>นักเรียนทั้งหมด</span><b>{{ number_format($studentCount) }}</b></div>
                        <div class="d-flex justify-content-between"><span class="text-danger">ขาด</span><b>{{ $todayCounts['absent'] ?? 0 }}</b></div>
                        <div class="d-flex justify-content-between"><span class="text-warning">สาย</span><b>{{ $todayCounts['late'] ?? 0 }}</b></div>
                        <div class="d-flex justify-content-between"><span class="text-info">ลา/ป่วย</span><b>{{ ($todayCounts['leave'] ?? 0) + ($todayCounts['sick'] ?? 0) }}</b></div>
                        @if ($notChecked->isNotEmpty() && today()->isWeekday())
                            <div class="d-flex justify-content-between text-muted"><span>ยังไม่เช็ค</span><b>{{ $notChecked->count() }} ห้อง</b></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body pb-2">
                <div class="card-title-sm"><i class="bi bi-envelope-paper text-primary"></i> ใบลารออนุมัติ
                    @if ($pendingLeaveCount)<span class="badge bg-danger">{{ $pendingLeaveCount }}</span>@endif
                    <a href="{{ route('leaves.index') }}" class="ms-auto small fw-normal">ทั้งหมด</a></div>
                @forelse ($pendingLeaves as $l)
                    <div class="d-flex gap-2 align-items-center py-2 border-top">
                        <span class="sb-avatar sm">{{ $l->student->initials() }}</span>
                        <div class="flex-grow-1 min-w-0 small">
                            <div class="fw-semibold text-truncate"><a href="{{ route('leaves.show', $l) }}" class="text-body text-decoration-none">{{ $l->student->fullName() }}</a></div>
                            <div class="text-muted">{{ $l->student->classroom?->name() }} · {{ $l->typeLabel() }} {{ thai_date($l->start_date) }}</div>
                        </div>
                        <a href="{{ route('leaves.show', $l) }}" class="btn btn-sm btn-light border" title="ดูรายละเอียดใบลา"><i class="bi bi-eye"></i> ดูใบลา</a>
                    </div>
                @empty
                    <div class="text-muted small pb-2"><i class="bi bi-check-circle text-success" aria-hidden="true"></i> ไม่มีใบลาค้าง</div>
                @endforelse
            </div>
        </div>

        @include('partials.upcoming-events')

        @if ($finance)
            <div class="card">
                <div class="card-body">
                    <div class="card-title-sm"><i class="bi bi-wallet2 text-primary"></i> การเงิน <a href="{{ route('invoices.index') }}" class="ms-auto small fw-normal">รายละเอียด</a></div>
                    <div class="d-flex justify-content-between small py-1"><span>ยอดค้างชำระ</span><b class="text-danger">{{ baht($finance['outstanding'], 0) }} ฿</b></div>
                    <div class="d-flex justify-content-between small py-1"><span>ใบแจ้งหนี้ค้าง</span><b>{{ number_format($finance['count']) }} ใบ</b></div>
                    <div class="d-flex justify-content-between small py-1"><span>รับชำระเดือนนี้</span><b class="text-success">{{ baht($finance['collected_month'], 0) }} ฿</b></div>
                </div>
            </div>
        @endif

        @if ($lowBehavior->isNotEmpty())
            <div class="card">
                <div class="card-body pb-2">
                    <div class="card-title-sm"><i class="bi bi-exclamation-diamond text-danger"></i> ต้องติดตามพฤติกรรม</div>
                    @foreach ($lowBehavior as $s)
                        <a href="{{ route('students.show', $s) }}" class="d-flex justify-content-between align-items-center py-2 border-top text-body small">
                            <span>{{ $s->fullName() }} <span class="text-muted">{{ $s->classroom?->name() }}</span></span>
                            <span class="badge bg-danger">{{ \App\Models\Student::BASE_BEHAVIOR + (int) $s->points_sum }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="card d-none d-lg-block">
            <div class="card-body">
                <div class="card-title-sm"><i class="bi bi-lightning-charge text-primary"></i> ทางลัด</div>
                <div class="app-grid" style="grid-template-columns:repeat(3,1fr)">
                    @foreach (array_slice($launcher, 0, 6) as $item)<x-app-tile :item="$item" />@endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
