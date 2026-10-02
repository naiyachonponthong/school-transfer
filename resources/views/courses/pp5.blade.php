@extends('layouts.app')
@section('title', 'ปพ.5 '.$course->subject->code.' '.$course->classroom->name())

@section('content')
@php
    $fmt = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $n = $students->count();
    $pct = fn ($c) => $n ? number_format($c / $n * 100, 1) : '0.0';
@endphp
<div class="d-flex gap-2 mb-3 no-print">
    <a href="{{ route('gradebook.show', $course) }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
    @if ($graded < $n)<span class="align-self-center small text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> ยังไม่มีผลการเรียน {{ $n - $graded }} คน</span>@endif
    <button onclick="print()" class="btn btn-primary ms-auto"><i class="bi bi-printer"></i> พิมพ์ / บันทึก PDF</button>
</div>

{{-- หน้า 1: ปก + สรุปผล + ลงนามอนุมัติ --}}
<div class="card doc-page" style="max-width:900px;margin:auto">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
            @if (school('logo'))<img src="{{ asset('storage/'.school('logo')) }}" alt="" style="height:64px" class="mb-2">@endif
            <h2 class="h5 fw-bold mb-0">แบบบันทึกผลการพัฒนาคุณภาพผู้เรียน (ปพ.5)</h2>
            <div>{{ school('school_name') }}</div>
            <div class="text-muted small">{{ $course->term->label() }}</div>
        </div>

        <table class="table table-bordered table-sm small mb-4">
            <tr><th class="fw-normal text-muted" style="width:22%">รหัสวิชา</th><td style="width:28%">{{ $course->subject->code }}</td><th class="fw-normal text-muted" style="width:22%">รายวิชา</th><td>{{ $course->subject->name }}</td></tr>
            <tr><th class="fw-normal text-muted">ประเภท</th><td>{{ $course->subject->typeLabel() }}{{ $course->subject->activityKindLabel() ? ' · '.$course->subject->activityKindLabel() : '' }}</td><th class="fw-normal text-muted">กลุ่มสาระการเรียนรู้</th><td>{{ $course->subject->group ?: '-' }}</td></tr>
            <tr><th class="fw-normal text-muted">หน่วยกิต</th><td>{{ $activity ? '-' : $course->subject->credit }}</td><th class="fw-normal text-muted">เวลาเรียน</th><td>{{ $course->subject->hours ? $course->subject->hours.' ชั่วโมง' : '-' }}</td></tr>
            <tr><th class="fw-normal text-muted">ชั้น</th><td>{{ $course->classroom->name() }}</td><th class="fw-normal text-muted">ครูผู้สอน</th><td>{{ $course->teacher?->name ?? '-' }}</td></tr>
        </table>

        <div class="fw-semibold mb-1">สรุปผลการ{{ $activity ? 'ประเมิน' : 'เรียน' }} (นักเรียน {{ $n }} คน)</div>
        <table class="table table-bordered table-sm small text-center mb-2">
            <tr class="table-light"><th class="text-start">ผล</th>@foreach ($distribution as $g => $c)<th>{{ $g }}</th>@endforeach<th>รวม</th></tr>
            <tr><td class="text-start">จำนวน (คน)</td>@foreach ($distribution as $c)<td>{{ $c }}</td>@endforeach<td>{{ $graded }}</td></tr>
            <tr><td class="text-start">ร้อยละ</td>@foreach ($distribution as $c)<td>{{ $pct($c) }}</td>@endforeach<td>{{ $pct($graded) }}</td></tr>
        </table>
        <div class="small mb-4">
            ผ่าน {{ $passed }} คน (ร้อยละ {{ $pct($passed) }})
            @unless ($activity) · ได้ระดับ 3 ขึ้นไป {{ $good }} คน (ร้อยละ {{ $pct($good) }})@endunless
            · ไม่ผ่าน/ยังไม่มีผล {{ $n - $passed }} คน
        </div>

        <div class="fw-semibold mb-3">การเสนอและอนุมัติผลการ{{ $activity ? 'ประเมิน' : 'เรียน' }}</div>
        <div class="row g-4 mb-4">
            <x-sign class="col-6" role="ครูผู้สอน" :name="$course->teacher?->name" />
            <x-sign class="col-6" role="หัวหน้ากลุ่มสาระการเรียนรู้" />
            <x-sign class="col-6" role="หัวหน้างานวัดผล" :name="school('measurement_head_name')" />
            <x-sign class="col-6" role="รองผู้อำนวยการฝ่ายวิชาการ" :name="school('academic_deputy_name')" />
        </div>
        <div class="border rounded-3 p-3 small">
            <div class="d-flex gap-4 justify-content-center mb-3"><span>☐ อนุมัติ</span><span>☐ ไม่อนุมัติ</span></div>
            <x-sign role="ผู้อำนวยการ{{ school('school_name') }}" :name="school('director_name')">
                <div class="mt-1">วันที่ ........ เดือน .................... พ.ศ. ............</div>
            </x-sign>
        </div>
    </div>
</div>

{{-- หน้า 2: คะแนนรายคน + เวลาเรียน --}}
<div class="card doc-page mt-3" style="max-width:900px;margin:auto">
    <div class="card-body p-4">
        <div class="fw-semibold mb-2">บันทึกคะแนนและเวลาเรียน · {{ $course->subject->code }} {{ $course->subject->name }} · {{ $course->classroom->name() }}</div>
        <table class="table table-bordered table-sm small align-middle mb-2">
            <thead class="table-light text-center">
                <tr>
                    <th style="width:36px">ที่</th><th>ชื่อ-สกุล</th>
                    @foreach ($course->assessments as $a)<th>{{ $a->name }}<div class="fw-normal">({{ $fmt($a->max_score) }})</div></th>@endforeach
                    <th>รวม<div class="fw-normal">({{ $fmt($course->maxTotal()) }})</div></th>
                    <th>ผล</th><th>เวลาเรียน</th><th>หมายเหตุ</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($students as $s)
                @php($r = $results[$s->id] ?? [])
                @php($at = $attendance[$s->id] ?? null)
                @php($o = $outcomes->get($s->id))
                <tr>
                    <td class="text-center">{{ $s->number }}</td>
                    <td class="text-nowrap">{{ $s->fullName() }}</td>
                    @foreach ($course->assessments as $a)<td class="text-center">{{ $fmt($scores[$s->id][$a->id] ?? null) }}</td>@endforeach
                    <td class="text-center fw-semibold">{{ $fmt($r['total'] ?? null) }}</td>
                    <td class="text-center fw-bold text-nowrap">{{ $r['grade'] ?? '' }}</td>
                    <td class="text-center text-nowrap">{{ $at && $at['percent'] !== null ? $at['came'].'/'.$at['total'].' ('.$at['percent'].'%)' : '' }}</td>
                    <td class="small">
                        @if (($r['remedial'] ?? null) !== null)แก้ตัวจาก {{ $r['original'] }} เป็น {{ $r['remedial'] }}{{ $o?->remedied_on ? ' ('.thai_date($o->remedied_on).')' : '' }}@endif
                        {{ $o?->note }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $course->assessments->count() + 6 }}" class="text-center text-muted">ห้องนี้ยังไม่มีนักเรียน</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="small text-muted">เวลาเรียน = มา+สาย / คาบที่เช็คชื่อ (ต่ำกว่าร้อยละ {{ \App\Models\PeriodAttendance::MIN_PERCENT }} ไม่มีสิทธิ์สอบ) · พิมพ์เมื่อ {{ thai_datetime(now()) }}</div>
    </div>
</div>
@endsection
