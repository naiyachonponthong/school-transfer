@extends('layouts.app')
@section('title', 'หน้าหลัก')
@section('body_class', 'has-hero')

@section('content')
@php
    $u = auth()->user();
    $hour = now()->hour;
    $greet = $hour < 11 ? 'อรุณสวัสดิ์' : ($hour < 16 ? 'สวัสดีตอนบ่าย' : 'สวัสดีตอนเย็น');
    $statusIcon = ['present' => 'bi-check-circle-fill', 'late' => 'bi-clock-fill', 'absent' => 'bi-x-circle-fill', 'leave' => 'bi-envelope-paper-fill', 'sick' => 'bi-thermometer-half'];
    $behavior = $student->behaviorScore();
    $num = fn ($n) => rtrim(rtrim(number_format($n, 2), '0'), '.');
    // คาบที่กำลังเรียนอยู่ตอนนี้ (ช่วงเวลาเก็บเป็น "08.30-09.20")
    $nowPeriod = null;
    foreach ($times as $i => $range) {
        [$from, $to] = array_map('trim', array_pad(explode('-', str_replace('.', ':', $range)), 2, ''));
        if ($from && $to && now()->format('H:i') >= $from && now()->format('H:i') < $to) {
            $nowPeriod = $i + 1;
        }
    }
@endphp

<div class="row g-3">
    <div class="col-lg-5 col-xl-4">
        <div class="m-hero">
            <div class="top">
                <span class="sb-logo" style="width:36px;height:36px;font-size:1.1rem;box-shadow:none;background:rgba(255,255,255,.2)"><i class="bi bi-mortarboard-fill"></i></span>
                <span class="fw-semibold small">{{ school('school_short') }}</span>
                <span class="ms-auto"></span>
                <a href="{{ route('notifications') }}" class="icon-btn"><i class="bi bi-bell"></i>@if($navUnread)<span class="dot">{{ $navUnread }}</span>@endif</a>
                <a href="{{ route('profile') }}"><span class="sb-avatar sm" style="background:rgba(255,255,255,.25);color:#fff">@if($student->photoUrl())<img src="{{ $student->photoUrl() }}" alt="">@else{{ $student->initials() }}@endif</span></a>
            </div>
            <div class="hello">{{ $greet }}, {{ $student->nickname ?: $student->first_name }}<small>{{ \App\Support\Thai::fullDate(today()) }}</small></div>
            <img class="m-hero-art" src="{{ asset('assets/img/school-community-hero.png') }}" alt="" aria-hidden="true" width="1536" height="1024">
        </div>

        <a href="{{ route('student.info') }}" class="card text-body d-block m-float" style="box-shadow:var(--sb-shadow-lg);border-radius:20px">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-bold text-truncate">{{ $student->fullName() }}</div>
                    <div class="small text-muted mb-2">ห้อง {{ $student->classroom?->name() }} · เลขที่ {{ $student->number }} · {{ $student->student_code }}</div>
                    @if ($todayAtt)
                        <div class="today-status py-2 bg-{{ \App\Models\Attendance::color($todayAtt->status) }}-subtle text-{{ \App\Models\Attendance::color($todayAtt->status) }}-emphasis">
                            <i class="bi {{ $statusIcon[$todayAtt->status] ?? 'bi-info-circle-fill' }}"></i>
                            <span>วันนี้{{ \App\Models\Attendance::label($todayAtt->status) }}@if($todayAtt->checked_at) <span class="fw-normal small">{{ substr($todayAtt->checked_at, 0, 5) }} น.</span>@endif</span>
                        </div>
                    @else
                        <div class="today-status py-2 bg-light text-muted"><i class="bi bi-hourglass-split"></i> <span>ครูยังไม่ได้เช็คชื่อ</span></div>
                    @endif
                </div>
                <x-ring :value="$behavior" :size="86" :label="(string) $behavior" sub="ประพฤติ" :color="$behavior >= 80 ? '#10b981' : ($behavior >= 60 ? '#f59e0b' : '#ef4444')" />
            </div>
        </a>

        <div class="card m-section">
            <div class="card-body">
                <div class="m-grid">
                    @foreach (\App\Support\Menu::launcher($u) as $item)<x-app-tile :item="$item" />@endforeach
                </div>
            </div>
        </div>

        @include('partials.upcoming-events', ['class' => 'm-section'])
    </div>

    <div class="col-lg-7 col-xl-8">
        <div class="row g-3 mb-3">
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-calendar3 text-primary"></i> เรียนวันนี้
                        <a href="{{ route('student.info', ['tab' => 'timetable']) }}" class="ms-auto small">ตารางทั้งสัปดาห์</a>
                    </div>
                    @forelse ($slots as $slot)
                        @php($ps = $slot->course_id ? ($periodStatus[$slot->course_id.'-'.$slot->period] ?? null) : null)
                        @php($now = (int) $slot->period === $nowPeriod)
                        <div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom {{ $now ? 'bg-primary-subtle' : '' }}">
                            <div class="text-center" style="width:48px">
                                <div class="fw-bold lh-1">{{ $slot->period }}</div>
                                <div class="text-muted" style="font-size:.68rem">{{ $times[$slot->period - 1] ?? '' }}</div>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate">{{ $slot->course?->subject->name ?? $slot->label }}</div>
                                <div class="small text-muted text-truncate">{{ $slot->course?->teacher?->name }}{{ $slot->room_name ? ' · '.$slot->room_name : '' }}</div>
                            </div>
                            @if ($now)<span class="badge bg-primary">ตอนนี้</span>@endif
                            @if ($ps)
                                <span class="badge bg-{{ \App\Models\Attendance::color($ps->status) }}-subtle text-{{ \App\Models\Attendance::color($ps->status) }}-emphasis">{{ \App\Models\Attendance::label($ps->status) }}</span>
                            @endif
                        </div>
                    @empty
                        <div class="empty"><i class="bi bi-cup-hot"></i>วันนี้ไม่มีคาบเรียน</div>
                    @endforelse
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header"><i class="bi bi-journal-text text-primary"></i> การบ้านที่ต้องส่ง
                        @if ($pending->isNotEmpty())<span class="badge bg-danger">{{ $pending->count() }}</span>@endif
                        <a href="{{ route('student.homework') }}" class="ms-auto small">ทั้งหมด</a>
                    </div>
                    @forelse ($pending->take(5) as $a)
                        @php($late = $a->due_at && $a->due_at->isPast())
                        <a href="{{ route('student.homework') }}" class="d-flex align-items-center gap-2 px-3 py-2 border-bottom text-decoration-none text-body list-link">
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate">{{ $a->title }}</div>
                                <div class="small text-muted text-truncate">{{ $a->course->subject->name }}</div>
                            </div>
                            <span class="small text-nowrap {{ $late ? 'text-danger fw-semibold' : 'text-muted' }}">
                                @if ($a->due_at){{ $late ? 'เลยกำหนด' : 'ส่ง '.thai_date($a->due_at) }}@else ไม่มีกำหนด @endif
                            </span>
                        </a>
                    @empty
                        <div class="empty"><i class="bi bi-emoji-sunglasses"></i>ส่งงานครบแล้ว เยี่ยมมาก!</div>
                    @endforelse
                    @if ($graded->isNotEmpty())
                        <div class="px-3 pt-2 pb-1 small fw-semibold text-muted">ตรวจแล้วล่าสุด</div>
                        @foreach ($graded as $s)
                            <div class="d-flex align-items-center gap-2 px-3 py-1 small">
                                <i class="bi bi-patch-check-fill text-success"></i>
                                <span class="flex-grow-1 text-truncate">{{ $s->assignment?->title }}</span>
                                <b>{{ $num($s->score) }}{{ $s->assignment?->max_score ? '/'.$num($s->assignment->max_score) : '' }}</b>
                            </div>
                        @endforeach
                        <div class="pb-2"></div>
                    @endif
                </div>
            </div>
        </div>

        <div class="m-section-title d-lg-none mt-2">ข่าวสารจากโรงเรียน <a href="{{ route('feed.index') }}">ดูทั้งหมด</a></div>
        <div class="card overflow-hidden" data-feed>
            <div class="card-header d-none d-lg-flex"><i class="bi bi-newspaper text-primary"></i> ข่าวสารจากโรงเรียน <a href="{{ route('feed.index') }}" class="ms-auto small">ทั้งหมด</a></div>
            @include('feed.list', ['posts' => $posts])
        </div>
    </div>
</div>
@endsection
