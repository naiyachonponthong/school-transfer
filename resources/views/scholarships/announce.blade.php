@extends('layouts.app')
@section('title', 'ประกาศรายชื่อ '.$scholarship->name)

@section('content')
<div class="d-flex gap-2 mb-3 no-print">
    <a href="{{ route('scholarships.show', $scholarship) }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
    <button onclick="print()" class="btn btn-light border ms-auto"><i class="bi bi-printer"></i> พิมพ์</button>
</div>

<div class="card doc-page" style="max-width:820px;margin:auto"><div class="card-body p-4">
    <div class="text-center mb-3">
        <h2 class="h6 fw-bold mb-1">ประกาศ{{ school('school_name') }}</h2>
        <div>เรื่อง รายชื่อนักเรียนที่ได้รับทุนการศึกษา "{{ $scholarship->name }}" ปีการศึกษา {{ $scholarship->year }}</div>
    </div>
    <p class="small">ตามที่โรงเรียนได้พิจารณาคัดเลือกนักเรียนเพื่อรับทุนการศึกษา{{ $scholarship->donor ? ' ซึ่งได้รับการสนับสนุนจาก '.$scholarship->donor : '' }} ทุนละ {{ $scholarship->valueLabel() }} นั้น บัดนี้การพิจารณาเสร็จสิ้นแล้ว จึงประกาศรายชื่อผู้ได้รับทุน จำนวน {{ $approved->count() }} คน ดังนี้</p>

    @foreach (['ผู้ได้รับทุน' => $approved, 'สำรอง' => $reserve] as $title => $list)
        @if ($list->isNotEmpty())
            <div class="fw-semibold small mt-3 mb-1">{{ $title }}</div>
            <table class="table table-bordered table-sm small">
                <thead class="table-light text-center"><tr><th style="width:50px">ที่</th><th style="width:110px">รหัส</th><th>ชื่อ-สกุล</th><th style="width:110px">ห้อง</th></tr></thead>
                <tbody>
                @foreach ($list as $i => $a)
                    <tr><td class="text-center">{{ $i + 1 }}</td><td class="text-center">{{ $a->student->student_code }}</td><td>{{ $a->student->fullName() }}</td><td class="text-center">{{ $a->student->classroom?->name() ?? '-' }}</td></tr>
                @endforeach
                </tbody>
            </table>
        @endif
    @endforeach
    @if ($approved->isEmpty())<p class="text-center text-muted small">ยังไม่มีผู้ได้รับทุน</p>@endif

    <div class="small mt-3">ประกาศ ณ วันที่ {{ \App\Support\Thai::fullDate(today()) }}</div>
    <div class="row g-3 mt-4 justify-content-end">
        <x-sign class="col-6" role="ผู้อำนวยการ{{ school('school_name') }}" :name="school('director_name')" />
    </div>
</div></div>
@endsection
