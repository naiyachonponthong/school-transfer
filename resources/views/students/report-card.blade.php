@extends('layouts.app')
@section('title', 'สมุดรายงานผล '.$student->fullName())

@section('content')
<div class="d-flex gap-2 mb-3 no-print">
    <a href="{{ url()->previous() }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
    <button onclick="print()" class="btn btn-primary ms-auto"><i class="bi bi-printer"></i> พิมพ์ / บันทึก PDF</button>
</div>

<div class="card" style="max-width:860px;margin:auto">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
            @if (school('logo'))<img src="{{ asset('storage/'.school('logo')) }}" alt="" style="height:70px" class="mb-2">@endif
            <h2 class="h5 fw-bold mb-1">รายงานผลการเรียน (ปพ.6)</h2>
            <div>{{ school('school_name') }}</div>
            <div class="text-muted small">{{ $term?->label() }}</div>
        </div>

        <div class="row small mb-3 g-2">
            <div class="col-sm-6"><span class="text-muted">ชื่อ-สกุล:</span> <b>{{ $student->fullName() }}</b></div>
            <div class="col-sm-3"><span class="text-muted">รหัส:</span> {{ $student->student_code }}</div>
            <div class="col-sm-3"><span class="text-muted">ชั้น:</span> {{ $student->classroom?->name() }} เลขที่ {{ $student->number }}</div>
        </div>

        <table class="table table-bordered align-middle small">
            <thead>
                <tr class="text-center"><th style="width:110px">รหัสวิชา</th><th>รายวิชา</th><th style="width:80px">หน่วยกิต</th><th style="width:100px">คะแนน</th><th style="width:70px">เกรด</th></tr>
            </thead>
            <tbody>
            @forelse ($grades as $g)
                <tr>
                    <td class="text-center">{{ $g['course']->subject->code }}</td>
                    <td>{{ $g['course']->subject->name }} <span class="text-muted">({{ $g['course']->subject->typeLabel() }})</span></td>
                    <td class="text-center">{{ $g['course']->subject->credit }}</td>
                    <td class="text-center">{{ $g['total'] !== null ? rtrim(rtrim(number_format($g['total'], 2), '0'), '.') : '-' }}</td>
                    <td class="text-center fw-bold text-nowrap">@if($g['original'] !== null && $g['original'] !== $g['grade'])<span class="fw-normal text-muted text-decoration-line-through me-1">{{ $g['original'] }}</span>@endif{{ $g['grade'] ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">ยังไม่มีผลการเรียน</td></tr>
            @endforelse
            </tbody>
            <tfoot>
                <tr class="fw-semibold"><td colspan="2" class="text-end">รวมหน่วยกิต (ได้ {{ $earned }})</td><td class="text-center">{{ $credits }}</td><td class="text-end">เกรดเฉลี่ย</td><td class="text-center fs-6">{{ $gpa !== null ? number_format($gpa, 2) : '-' }}</td></tr>
            </tfoot>
        </table>

        <div class="row g-3 small mt-2">
            <div class="col-sm-7">
                <div class="fw-semibold mb-1">เวลาเรียน</div>
                <table class="table table-bordered table-sm text-center mb-0">
                    <tr>@foreach (\App\Models\Attendance::STATUSES as [$label])<th class="fw-normal">{{ $label }}</th>@endforeach</tr>
                    <tr>@foreach (array_keys(\App\Models\Attendance::STATUSES) as $k)<td>{{ $attendance[$k] ?? 0 }}</td>@endforeach</tr>
                </table>
            </div>
            <div class="col-sm-5">
                <div class="fw-semibold mb-1">คะแนนความประพฤติ</div>
                <div class="border rounded p-2 text-center fs-5 fw-bold">{{ $behavior }} / {{ \App\Models\Student::BASE_BEHAVIOR }}</div>
            </div>
        </div>

        <div class="row g-3 small mt-1">
            <div class="col-sm-8">
                <div class="fw-semibold mb-1">คุณลักษณะอันพึงประสงค์</div>
                <table class="table table-bordered table-sm mb-0">
                    @foreach (\App\Support\Evaluation::TRAITS as $no => $name)
                        <tr><td>{{ $no }}. {{ $name }}</td><td class="text-center" style="width:90px">{{ \App\Support\Evaluation::label($evaluation?->trait($no)) }}</td></tr>
                    @endforeach
                    <tr class="fw-semibold"><td class="text-end">สรุปผลการประเมิน</td><td class="text-center">{{ \App\Support\Evaluation::label($evaluation?->traitsSummary()) }}</td></tr>
                </table>
            </div>
            <div class="col-sm-4">
                <div class="fw-semibold mb-1">การอ่าน คิดวิเคราะห์ และเขียน</div>
                <div class="border rounded p-2 text-center fs-6 fw-bold">{{ \App\Support\Evaluation::label($evaluation?->rtw) }}</div>
            </div>
        </div>

        <div class="row text-center small mt-5 pt-3">
            <div class="col-6">
                <div>ลงชื่อ ...........................................</div>
                <div class="mt-1">( {{ $student->classroom?->homeroomTeacher?->name ?? '...........................................' }} )</div>
                <div class="text-muted">ครูประจำชั้น</div>
            </div>
            <div class="col-6">
                <div>ลงชื่อ ...........................................</div>
                <div class="mt-1">( {{ school('director_name') ?: '...........................................' }} )</div>
                <div class="text-muted">ผู้อำนวยการโรงเรียน</div>
            </div>
        </div>
    </div>
</div>
@endsection
