@extends('layouts.app')
@section('title', 'ชุมนุม')

@section('content')
@php
    [$from, $until] = $window;
    $joined = $total - $without->count();
    $fields = function (?\App\Models\Club $c = null) use ($teachers, $levels) {
        return view('clubs._fields', ['club' => $c, 'teachers' => $teachers, 'levels' => $levels])->render();
    };
@endphp
<div class="page-head">
    <div><h1>ชุมนุม / ชมรม</h1><div class="sub">{{ $term->label() }} · {{ $clubs->count() }} ชุมนุม · นักเรียนเลือกแล้ว {{ $joined }} จาก {{ $total }} คน</div></div>
    <div class="actions">
        @if ($canManage)<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClub"><i class="bi bi-plus-lg"></i> เพิ่มชุมนุม</button>@endif
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card h-100"><div class="card-body d-flex align-items-center gap-3">
        <x-ring :value="$total ? $joined / $total * 100 : 0" :size="86" :label="$joined.'/'.$total" sub="เลือกแล้ว" />
        <div class="small text-muted">ยังไม่ได้เลือก <b class="text-body">{{ $without->count() }}</b> คน<br>รายชื่ออยู่ด้านล่างของหน้านี้</div>
    </div></div></div>
    <div class="col-md-8"><div class="card h-100">
        <div class="card-header"><i class="bi bi-calendar-range"></i> ช่วงที่นักเรียนเลือกชุมนุมเองได้
            <span class="ms-auto badge bg-{{ $open ? 'success' : 'secondary' }}">{{ $open ? 'กำลังเปิดรับ' : ($from && now()->lt($from) ? 'ยังไม่ถึงวันเปิดรับ' : 'ปิดรับอยู่') }}</span>
        </div>
        <div class="card-body">
            @if ($canManage)
                <form method="POST" action="{{ route('clubs.window') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-sm-5"><label class="form-label small">เปิดรับตั้งแต่</label><input type="datetime-local" name="club_signup_from" value="{{ old('club_signup_from', $from?->format('Y-m-d\TH:i')) }}" class="form-control @error('club_signup_from') is-invalid @enderror"></div>
                    <div class="col-sm-5"><label class="form-label small">ถึง</label><input type="datetime-local" name="club_signup_until" value="{{ old('club_signup_until', $until?->format('Y-m-d\TH:i')) }}" class="form-control @error('club_signup_until') is-invalid @enderror"></div>
                    <div class="col-sm-2"><button class="btn btn-light border w-100">บันทึก</button></div>
                    <div class="col-12 small text-muted">เว้นว่างทั้งสองช่องเพื่อปิดรับ · นอกช่วงนี้ครูที่ปรึกษายังเพิ่มหรือย้ายสมาชิกเองได้</div>
                </form>
            @else
                <div class="small">{{ $from ? thai_datetime($from).' – '.thai_datetime($until) : 'ยังไม่ได้กำหนดช่วงเปิดรับ' }}</div>
            @endif
        </div>
    </div></div>
</div>

<div class="card mb-3">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>ชุมนุม</th><th>ครูที่ปรึกษา</th><th>ระดับที่รับ</th><th style="min-width:170px">สมาชิก</th><th>รายวิชา</th><th></th></tr></thead>
            <tbody>
            @forelse ($clubs as $c)
                @php($pct = $c->capacity ? min(100, $c->students_count / $c->capacity * 100) : 0)
                <tr>
                    <td><a href="{{ route('clubs.show', $c) }}" class="fw-semibold">{{ $c->name }}</a>@if ($c->location)<div class="small text-muted"><i class="bi bi-geo-alt"></i> {{ $c->location }}</div>@endif</td>
                    <td class="small">{{ $c->teacher?->name ?? '-' }}</td>
                    <td class="small">{{ $c->levelsLabel() }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2 small">
                            <span class="text-nowrap fw-semibold">{{ $c->students_count }}{{ $c->capacity ? ' / '.$c->capacity : '' }}</span>
                            @if ($c->capacity)<div class="progress flex-grow-1" style="height:6px" role="img" aria-label="รับแล้ว {{ $c->students_count }} จาก {{ $c->capacity }}"><div class="progress-bar {{ $c->isFull($c->students_count) ? 'bg-danger' : '' }}" style="width:{{ $pct }}%"></div></div>
                            @else<span class="text-muted">ไม่จำกัด</span>@endif
                            @if ($c->isFull($c->students_count))<span class="badge bg-danger">เต็ม</span>@endif
                        </div>
                    </td>
                    <td>@if ($c->course_id)<a href="{{ route('gradebook.show', $c->course_id) }}" class="badge bg-success-subtle text-success-emphasis text-decoration-none">มีรายวิชาแล้ว</a>@else<span class="small text-muted">ยังไม่สร้าง</span>@endif</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('clubs.show', $c) }}" class="btn btn-sm btn-light border">สมาชิก</a>
                        @if ($canManage)<button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#editClub{{ $c->id }}" title="แก้ไข" aria-label="แก้ไข {{ $c->name }}"><i class="bi bi-pencil"></i></button>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><i class="bi bi-people"></i>ยังไม่มีชุมนุมในภาคเรียนนี้{{ $canManage ? ' กด "เพิ่มชุมนุม" เพื่อเริ่ม' : '' }}</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-person-exclamation"></i> นักเรียนที่ยังไม่ได้เลือกชุมนุม <span class="ms-2 badge bg-secondary-subtle text-secondary-emphasis">{{ $without->count() }} คน</span></div>
    <div class="card-body">
        @forelse ($without->groupBy(fn ($s) => $s->classroom->name()) as $room => $list)
            <div class="mb-2"><span class="fw-semibold small">{{ $room }}</span> <span class="small text-muted">({{ $list->count() }})</span>
                <div class="small">{{ $list->map(fn ($s) => $s->number.' '.$s->first_name.' '.$s->last_name)->implode(' · ') }}</div>
            </div>
        @empty
            <div class="small text-success"><i class="bi bi-check-circle"></i> นักเรียนทุกคนมีชุมนุมแล้ว</div>
        @endforelse
    </div>
</div>

@if ($canManage)
    <div class="modal fade" id="addClub" tabindex="-1">
        <div class="modal-dialog"><form method="POST" action="{{ route('clubs.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">เพิ่มชุมนุม</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body row g-3">{!! $fields() !!}</div>
            <div class="modal-footer"><button class="btn btn-primary">เพิ่มชุมนุม</button></div>
        </form></div>
    </div>
    @foreach ($clubs as $c)
        <div class="modal fade" id="editClub{{ $c->id }}" tabindex="-1">
            <div class="modal-dialog"><form method="POST" action="{{ route('clubs.update', $c) }}" class="modal-content">
                @csrf @method('PUT')
                <div class="modal-header"><h5 class="modal-title">แก้ไข {{ $c->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
                <div class="modal-body row g-3">{!! $fields($c) !!}</div>
                <div class="modal-footer"><button class="btn btn-primary">บันทึก</button></div>
            </form></div>
        </div>
    @endforeach
@endif
@endsection
