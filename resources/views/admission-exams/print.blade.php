@extends('layouts.app')
@section('title', $title.' · '.$round->label())

@section('content')
@php
    $fmt = fn ($v) => $v === null ? '-' : rtrim(rtrim(number_format($v, 2), '0'), '.');
    $head = 'การสอบคัดเลือกนักเรียนเข้าศึกษาต่อชั้น '.$round->level.' ปีการศึกษา '.$round->year;
    $date = $round->exam_date ? 'สอบวันที่ '.thai_date($round->exam_date) : '';
@endphp
<div class="d-flex gap-2 mb-3 no-print">
    <a href="{{ route('admission-exams.show', [$round, 'tab' => in_array($doc, ['announce', 'scores'], true) ? 'results' : 'seats']) }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
    <div class="align-self-center fw-semibold">{{ $title }}</div>
    <button onclick="print()" class="btn btn-primary ms-auto"><i class="bi bi-printer"></i> พิมพ์</button>
</div>

@if (in_array($doc, ['door', 'sign', 'desk'], true) && $byRoom->isEmpty())
    <div class="card"><div class="empty"><i class="bi bi-people"></i>ยังไม่มีผู้เข้าสอบ</div></div>
@endif

{{-- รายชื่อติดหน้าห้อง / ใบลงชื่อเข้าสอบ: ห้องละหน้า --}}
@if (in_array($doc, ['door', 'sign'], true))
    @foreach ($byRoom as $room => $list)
        <div class="card doc-page mb-3" style="max-width:820px;margin:auto"><div class="card-body p-4">
            <div class="text-center mb-2">
                <div class="fw-bold">{{ school('school_name') }}</div>
                <div>{{ $doc === 'door' ? 'รายชื่อผู้เข้าสอบ' : 'ใบลงชื่อผู้เข้าสอบ' }} {{ $head }}</div>
                <div class="fs-4 fw-bold mt-1">ห้องสอบ {{ $room }}</div>
                <div class="small">{{ $date }} · ผู้เข้าสอบ {{ $list->count() }} คน · เลขประจำตัวสอบ {{ $list->first()->exam_no }}–{{ $list->last()->exam_no }}</div>
            </div>
            <table class="table table-bordered table-sm align-middle {{ $doc === 'door' ? '' : 'small' }}">
                <thead class="table-light text-center">
                    <tr>
                        <th style="width:56px">ที่นั่ง</th><th style="width:110px">เลขประจำตัวสอบ</th><th>ชื่อ-สกุล</th>
                        @if ($doc === 'door')
                            <th>โรงเรียนเดิม</th>
                        @else
                            @forelse ($exams as $e)<th style="width:{{ max(70, intdiv(300, max(1, $exams->count()))) }}px">ลายมือชื่อ<br><span class="fw-normal">{{ $e->subjectLabel() }}</span></th>@empty<th style="width:160px">ลายมือชื่อ</th>@endforelse
                            <th style="width:80px">หมายเหตุ</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                @foreach ($list as $a)
                    <tr style="{{ $doc === 'sign' ? 'height:30px' : '' }}">
                        <td class="text-center">{{ $a->exam_seat }}</td>
                        <td class="text-center font-monospace fw-semibold">{{ $a->exam_no }}</td>
                        <td>{{ $a->fullName() }}</td>
                        @if ($doc === 'door')
                            <td class="small">{{ $a->previous_school }}</td>
                        @else
                            @forelse ($exams as $e)<td></td>@empty<td></td>@endforelse
                            <td></td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
            @if ($doc === 'sign')
                <div class="small mb-3">ผู้เข้าสอบ ........... คน · ขาดสอบ ........... คน (เลขประจำตัวสอบ ..................................................)</div>
                <div class="row g-3 mt-2">
                    <x-sign class="col-6" role="กรรมการคุมสอบ" />
                    <x-sign class="col-6" role="กรรมการคุมสอบ" />
                </div>
            @endif
        </div></div>
    @endforeach
@endif

{{-- บัตรติดโต๊ะสอบ 10 ใบต่อหน้า --}}
@if ($doc === 'desk')
    <style>
        .desk-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm; }
        .desk-card { border: 1.5px solid #222; border-radius: 3mm; padding: 3mm 4mm; height: 50mm; display: flex; flex-direction: column; justify-content: space-between; background: #fff; break-inside: avoid; }
        .desk-card .no { font-size: 30pt; font-weight: 800; letter-spacing: 2px; line-height: 1; font-family: ui-monospace, monospace; }
        @media print { .desk-sheet { break-after: page; } .desk-sheet:last-child { break-after: auto; } }
    </style>
    @foreach ($byRoom->flatten(1)->chunk(10) as $page)
        <div class="desk-sheet mb-3" style="max-width:820px;margin:auto">
            <div class="desk-grid">
                @foreach ($page as $a)
                    <div class="desk-card">
                        <div class="small text-muted">{{ school('school_name') }} · สอบคัดเลือกชั้น {{ $round->level }} ปี {{ $round->year }}</div>
                        <div class="d-flex justify-content-between align-items-end">
                            <div><div class="small">เลขประจำตัวสอบ</div><div class="no">{{ $a->exam_no }}</div></div>
                            <div class="text-end"><div class="small mb-1">ห้องสอบ {{ $a->exam_room }}</div><div class="fs-3 fw-bold" style="line-height:1.25">ที่นั่ง {{ $a->exam_seat }}</div></div>
                        </div>
                        <div class="fw-semibold">{{ $a->fullName() }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
@endif

{{-- ประกาศรายชื่อ (ไม่แสดงคะแนน ตามแบบประกาศของโรงเรียนทั่วไป) --}}
@if ($doc === 'announce')
    @php($pass = $standings->where('result', 'pass')->values())
    @php($reserve = $standings->where('result', 'reserve')->values())
    <div class="card doc-page" style="max-width:820px;margin:auto"><div class="card-body p-4">
        @if (school('logo'))<div class="text-center mb-2"><img src="{{ asset('storage/'.school('logo')) }}" alt="" style="height:70px"></div>@endif
        <div class="text-center mb-3">
            <div class="fw-bold fs-5">ประกาศ{{ school('school_name') }}</div>
            <div class="fw-semibold">เรื่อง รายชื่อผู้ผ่านการคัดเลือกเข้าศึกษาต่อชั้น {{ $round->level }} ปีการศึกษา {{ $round->year }}</div>
            <div>----------------------------------------</div>
        </div>
        <p style="text-indent:2.5rem">ตามที่{{ school('school_name') }}ได้ดำเนินการสอบคัดเลือกนักเรียนเข้าศึกษาต่อชั้น {{ $round->level }} ปีการศึกษา {{ $round->year }}
            @if ($round->exam_date) เมื่อวันที่ {{ thai_date($round->exam_date) }} @endif
            เสร็จสิ้นแล้ว จึงประกาศรายชื่อผู้ผ่านการคัดเลือก จำนวน {{ $pass->count() }} คน @if ($reserve->isNotEmpty()) และรายชื่อสำรอง จำนวน {{ $reserve->count() }} คน @endif ดังนี้</p>

        <div class="fw-semibold mb-1">ผู้ผ่านการคัดเลือก</div>
        <table class="table table-bordered table-sm">
            <thead class="table-light text-center"><tr><th style="width:60px">ที่</th><th style="width:130px">เลขประจำตัวสอบ</th><th>ชื่อ-สกุล</th></tr></thead>
            <tbody>
            @forelse ($pass as $i => $row)
                <tr><td class="text-center">{{ $i + 1 }}</td><td class="text-center font-monospace">{{ $row['application']->exam_no }}</td><td>{{ $row['application']->fullName() }}</td></tr>
            @empty
                <tr><td colspan="3" class="text-center text-muted">-</td></tr>
            @endforelse
            </tbody>
        </table>
        @if ($reserve->isNotEmpty())
            <div class="fw-semibold mb-1 mt-3">รายชื่อสำรอง (เรียกตามลำดับเมื่อมีที่ว่าง)</div>
            <table class="table table-bordered table-sm">
                <thead class="table-light text-center"><tr><th style="width:60px">ลำดับ</th><th style="width:130px">เลขประจำตัวสอบ</th><th>ชื่อ-สกุล</th></tr></thead>
                <tbody>@foreach ($reserve as $row)<tr><td class="text-center">{{ $row['reserve_no'] }}</td><td class="text-center font-monospace">{{ $row['application']->exam_no }}</td><td>{{ $row['application']->fullName() }}</td></tr>@endforeach</tbody>
            </table>
        @endif
        @if ($round->announce_note)<p class="mt-3" style="text-indent:2.5rem;white-space:pre-line">{{ $round->announce_note }}</p>@endif
        <p class="mt-3" style="text-indent:2.5rem">ประกาศ ณ วันที่ {{ thai_date($round->published_at ?? now()) }}</p>
        <div class="row mt-4"><x-sign class="col-6 offset-6" :role="'ผู้อำนวยการ'.school('school_name')" :name="school('director_name')" /></div>
    </div></div>
@endif

{{-- รายงานคะแนนทั้งหมด (ภายใน) --}}
@if ($doc === 'scores')
    <div class="card doc-page" style="max-width:1000px;margin:auto"><div class="card-body p-4">
        <div class="text-center mb-2">
            <div class="fw-bold">{{ school('school_name') }}</div>
            <div>รายงานคะแนน{{ $head }}</div>
            <div class="small">{{ $date }} · เกณฑ์: รับ {{ $round->quota ?: 'ไม่จำกัด' }} คน · สำรอง {{ $round->reserve }} คน{{ $round->min_score !== null ? ' · คะแนนขั้นต่ำ '.$fmt($round->min_score) : '' }}</div>
        </div>
        <table class="table table-bordered table-sm small align-middle">
            <thead class="table-light text-center"><tr><th>อันดับ</th><th>เลขสอบ</th><th>ชื่อ-สกุล</th>
                @foreach ($exams as $e)<th>{{ $e->subjectLabel() }}@if($e->weight != 1) ×{{ $fmt($e->weight) }}@endif</th>@endforeach
                <th>รวม</th><th>ผล</th></tr></thead>
            <tbody>
            @foreach ($standings as $row)
                <tr>
                    <td class="text-center">{{ $row['rank'] ?? '-' }}</td>
                    <td class="text-center font-monospace">{{ $row['application']->exam_no }}</td>
                    <td>{{ $row['application']->fullName() }}</td>
                    @foreach ($exams as $e)<td class="text-center">{{ $row['scores'][$e->id] === null ? 'ขาด' : $fmt($row['scores'][$e->id]) }}</td>@endforeach
                    <td class="text-center fw-semibold">{{ $row['absent'] ? '-' : $fmt($row['total']) }}</td>
                    <td class="text-center">{{ \App\Models\AdmissionRound::RESULTS[$row['result']][0] }}{{ $row['reserve_no'] ? ' '.$row['reserve_no'] : '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="row g-3 mt-3">
            <x-sign class="col-4" role="ประธานกรรมการ" />
            <x-sign class="col-4" role="กรรมการ" />
            <x-sign class="col-4" role="กรรมการ" />
        </div>
    </div></div>
@endif
@endsection
