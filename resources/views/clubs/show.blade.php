@extends('layouts.app')
@section('title', $club->name)

@section('content')
<div class="page-head">
    <div><h1>{{ $club->name }}</h1><div class="sub">{{ $club->term->label() }} · ครูที่ปรึกษา {{ $club->teacher?->name ?? '-' }} · รับ {{ $club->levelsLabel() }}{{ $club->location ? ' · '.$club->location : '' }}</div></div>
    <div class="actions">
        <button class="btn btn-light border" onclick="print()"><i class="bi bi-printer"></i> พิมพ์รายชื่อ</button>
        <a href="{{ route('clubs.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
    </div>
</div>

@if ($club->description)<div class="card mb-3"><div class="card-body small" style="white-space:pre-line">{{ $club->description }}</div></div>@endif

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-people"></i> สมาชิก {{ $members->count() }}{{ $club->capacity ? ' / '.$club->capacity : '' }} คน
                @if ($club->isFull($members->count()))<span class="badge bg-danger ms-2">เต็ม</span>@endif
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th style="width:56px">ที่</th><th>ชื่อ-สกุล</th><th>ห้อง</th><th>เลขที่</th><th class="no-print"></th></tr></thead>
                    <tbody>
                    @forelse ($members as $i => $s)
                        <tr>
                            <td>{{ $i + 1 }}</td><td>{{ $s->fullName() }}</td><td>{{ $s->classroom?->name() }}</td><td>{{ $s->number }}</td>
                            <td class="text-end no-print">
                                @if ($canManage)
                                    <form method="POST" action="{{ route('clubs.members.remove', [$club, $s]) }}" data-confirm="นำ {{ $s->fullName() }} ออกจากชุมนุม?">@csrf @method('DELETE')
                                        <button class="btn btn-sm btn-light border text-danger" title="นำออก" aria-label="นำ {{ $s->fullName() }} ออก"><i class="bi bi-x-lg"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty"><i class="bi bi-people"></i>ยังไม่มีสมาชิก</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4 no-print">
        @if ($canManage)
            <form method="POST" action="{{ route('clubs.members.add', $club) }}" class="card mb-3">
                @csrf
                <div class="card-header"><i class="bi bi-person-plus"></i> เพิ่มสมาชิก</div>
                <div class="card-body">
                    <select name="student_id" class="form-select @error('club') is-invalid @enderror" required data-search>
                        <option value="">- เลือกนักเรียนที่ยังไม่มีชุมนุม -</option>
                        @foreach ($candidates as $s)<option value="{{ $s->id }}">{{ $s->classroom->name() }} #{{ $s->number }} {{ $s->fullName() }}</option>@endforeach
                    </select>
                    <div class="form-text">แสดงเฉพาะนักเรียนในระดับที่ชุมนุมรับและยังไม่มีชุมนุม · ครูเพิ่มเกินจำนวนรับได้</div>
                </div>
                <div class="card-footer bg-transparent text-end"><button class="btn btn-primary"><i class="bi bi-plus-lg"></i> เพิ่ม</button></div>
            </form>
        @endif

        <div class="card">
            <div class="card-header"><i class="bi bi-journal-check"></i> รายวิชาของชุมนุม</div>
            <div class="card-body small">
                @if ($club->course)
                    <p class="mb-2">มีรายวิชาแล้ว ครูที่ปรึกษาเช็คชื่อรายคาบและประเมินผล ผ/มผ ได้ รายชื่อในรายวิชาจะตามสมาชิกของชุมนุมอัตโนมัติ</p>
                    <a href="{{ route('gradebook.show', $club->course) }}" class="btn btn-sm btn-light border"><i class="bi bi-journal-check"></i> เปิดสมุดคะแนน</a>
                @else
                    <p class="mb-2 text-muted">เมื่อรายชื่อสมาชิกลงตัวแล้ว สร้างรายวิชาเพื่อใช้เช็คชื่อรายคาบและประเมินผล ผ/มผ ของชุมนุมนี้</p>
                    @error('course')<div class="text-danger mb-2">{{ $message }}</div>@enderror
                    @if ($canAdmin)
                        <form method="POST" action="{{ route('clubs.course', $club) }}" data-confirm="สร้างรายวิชาของชุมนุม {{ $club->name }} จากรายชื่อสมาชิกปัจจุบัน?">@csrf
                            <button class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> สร้างรายวิชา</button>
                        </form>
                    @else
                        <div class="text-muted">ฝ่ายวิชาการเป็นผู้สร้างรายวิชา</div>
                    @endif
                @endif
            </div>
        </div>

        @if ($canAdmin && ! $club->course_id && $members->isEmpty())
            <form method="POST" action="{{ route('clubs.destroy', $club) }}" data-confirm="ลบชุมนุม {{ $club->name }}?" class="text-end mt-2">@csrf @method('DELETE')
                <button class="btn btn-link btn-sm text-danger">ลบชุมนุมนี้</button>
            </form>
        @endif
    </div>
</div>
@endsection
