@extends('layouts.app')
@section('title', 'เลือกชุมนุม')

@section('content')
@php
    [$from, $until] = $window;
@endphp
<div class="page-head">
    <div><h1>เลือกชุมนุม</h1><div class="sub">{{ $term->label() }} · เลือกได้ 1 ชุมนุม
        @if ($open) · เปิดรับถึง {{ thai_datetime($until) }}
        @elseif ($from && now()->lt($from)) · เปิดรับ {{ thai_datetime($from) }}
        @else · ปิดรับสมัครแล้ว @endif
    </div></div>
</div>

@if ($mine)
    <div class="card mb-3 border-success">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <span class="stat-icon tint-success"><i class="bi bi-check2-circle"></i></span>
            <div class="flex-grow-1">
                <div class="small text-muted">ชุมนุมของฉัน</div>
                <div class="fw-bold fs-5">{{ $mine->name }}</div>
                <div class="small text-muted">ครูที่ปรึกษา {{ $mine->teacher?->name ?? '-' }}{{ $mine->location ? ' · '.$mine->location : '' }}</div>
            </div>
            @if ($open)
                <form method="POST" action="{{ route('student.clubs.leave') }}" data-confirm="ยกเลิกชุมนุม {{ $mine->name }} เพื่อเลือกใหม่? ที่นั่งอาจถูกคนอื่นเลือกไปก่อน">@csrf @method('DELETE')
                    <button class="btn btn-light border">เปลี่ยนชุมนุม</button>
                </form>
            @endif
        </div>
    </div>
@elseif (! $open)
    <div class="alert alert-warning small">{{ $from && now()->lt($from) ? 'ยังไม่ถึงช่วงเปิดรับสมัคร ดูรายชื่อชุมนุมไว้ก่อนได้' : 'ปิดรับสมัครแล้ว ถ้ายังไม่มีชุมนุม กรุณาติดต่อครูประจำชั้น' }}</div>
@endif

@error('club')<div class="alert alert-danger small">{{ $message }}</div>@enderror

<div class="row g-3">
    @forelse ($clubs as $c)
        @php
            $full = $c->isFull($c->students_count);
            $left = $c->capacity ? max(0, $c->capacity - $c->students_count) : null;
            $isMine = $mine?->id === $c->id;
        @endphp
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 {{ $isMine ? 'border-success' : '' }}">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-start gap-2">
                        <div class="fw-bold flex-grow-1">{{ $c->name }}</div>
                        @if ($isMine)<span class="badge bg-success">เลือกแล้ว</span>
                        @elseif ($full)<span class="badge bg-danger">เต็ม</span>
                        @elseif ($left !== null)<span class="badge bg-success-subtle text-success-emphasis">เหลือ {{ $left }} ที่</span>
                        @else<span class="badge bg-secondary-subtle text-secondary-emphasis">ไม่จำกัด</span>@endif
                    </div>
                    <div class="small text-muted mb-2">ครูที่ปรึกษา {{ $c->teacher?->name ?? '-' }}{{ $c->location ? ' · '.$c->location : '' }}</div>
                    @if ($c->description)<div class="small mb-3" style="white-space:pre-line">{{ $c->description }}</div>@endif
                    <div class="mt-auto">
                        @if ($c->capacity)
                            <div class="progress mb-2" style="height:6px" role="img" aria-label="รับแล้ว {{ $c->students_count }} จาก {{ $c->capacity }}"><div class="progress-bar {{ $full ? 'bg-danger' : '' }}" style="width:{{ min(100, $c->students_count / $c->capacity * 100) }}%"></div></div>
                        @endif
                        <div class="d-flex align-items-center gap-2">
                            <span class="small text-muted flex-grow-1">สมาชิก {{ $c->students_count }}{{ $c->capacity ? ' / '.$c->capacity : '' }} คน</span>
                            @if ($open && ! $mine && ! $full)
                                <form method="POST" action="{{ route('student.clubs.join', $c) }}" data-confirm="เลือกชุมนุม {{ $c->name }}?">@csrf
                                    <button class="btn btn-primary btn-sm">เลือกชุมนุมนี้</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12"><div class="card"><div class="empty"><i class="bi bi-people"></i>ยังไม่มีชุมนุมที่เปิดรับระดับชั้นของคุณ</div></div></div>
    @endforelse
</div>
@endsection
