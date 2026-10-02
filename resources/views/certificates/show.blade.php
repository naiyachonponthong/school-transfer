@extends('layouts.app')
@section('title', 'ปพ.7 '.$issue->code().' '.$issue->student_name)

@section('content')
@php
    $dash = '..............................';
    $date = fn ($d) => $d ? thai_date(\Illuminate\Support\Carbon::parse($d), true) : $dash;
@endphp
<div class="d-flex gap-2 mb-3 no-print">
    <a href="{{ route('certificates.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> ทะเบียนคุม</a>
    <button onclick="print()" class="btn btn-primary ms-auto"><i class="bi bi-printer"></i> พิมพ์ / บันทึก PDF</button>
</div>
<div class="card doc-page" style="max-width:820px;margin:auto">
    <div class="card-body p-4 p-md-5" style="line-height:2">
        <div class="d-flex justify-content-between small"><span></span><span>เลขที่ {{ $issue->code() }}</span></div>
        <div class="text-center mb-3">
            @if (school('logo'))<img src="{{ asset('storage/'.school('logo')) }}" alt="" style="height:72px" class="mb-2">@endif
            <h2 class="h5 fw-bold mb-0">ใบรับรองผลการศึกษา (ปพ.7)</h2>
            <div>{{ school('school_name') }}</div>
            @if (school('school_address'))<div class="small text-muted">{{ school('school_address') }}</div>@endif
        </div>

        <div class="d-flex gap-4">
            <div class="flex-grow-1">
                <p class="mb-1" style="text-indent:3em">
                    {{ school('school_name') }} ขอรับรองว่า <b>{{ $s['name'] }}</b>
                    เลขประจำตัวนักเรียน {{ $s['student_code'] }}
                    เลขประจำตัวประชาชน {{ $s['citizen_id'] ?: $dash }}
                    เกิดวันที่ {{ $date($s['birthdate']) }}
                    ชื่อบิดา {{ $s['father_name'] ?: $dash }}
                    ชื่อมารดา {{ $s['mother_name'] ?: $dash }}
                </p>
                <p class="mb-1" style="text-indent:3em">
                    @switch($s['status'])
                        @case('active')
                            เป็นนักเรียนของโรงเรียนนี้ ขณะนี้กำลังศึกษาอยู่ชั้น {{ $s['classroom'] ?? $dash }} ปีการศึกษา {{ $s['year'] ?? $dash }}
                            @break
                        @case('graduated')
                            ได้สำเร็จการศึกษาจากโรงเรียนนี้ เมื่อวันที่ {{ $date($s['left_on']) }}
                            @break
                        @default
                            เคยเป็นนักเรียนของโรงเรียนนี้ ตั้งแต่วันที่ {{ $date($s['admitted_on']) }} ถึงวันที่ {{ $date($s['left_on']) }}
                            สาเหตุที่ออก {{ $s['leave_reason'] ?: \App\Models\Student::STATUSES[$s['status']] ?? $dash }}
                    @endswitch
                </p>
                <p class="mb-1" style="text-indent:3em">
                    มีผลการเรียนเฉลี่ยสะสม <b>{{ $s['gpax'] !== null ? number_format($s['gpax'], 2) : '-' }}</b>
                    หน่วยกิตสะสมที่ได้ {{ $s['credits'] }}
                    @if ($s['last_term'])(ถึง{{ $s['last_term'] }})@endif
                </p>
                <p class="mb-1" style="text-indent:3em">ใบรับรองนี้ออกให้เพื่อ {{ $issue->purpose }}</p>
                <p class="mb-0" style="text-indent:3em">ให้ไว้ ณ วันที่ {{ thai_date($issue->issued_on, true) }}</p>
            </div>
            <div class="text-center flex-shrink-0">
                <div class="border d-flex align-items-center justify-content-center text-muted small" style="width:3cm;height:4cm;overflow:hidden">
                    @if ($s['photo'] && \Illuminate\Support\Facades\Storage::disk('public')->exists($s['photo']))<img src="{{ asset('storage/'.$s['photo']) }}" alt="" style="width:100%;height:100%;object-fit:cover">@else รูปถ่าย<br>3 × 4 ซม.@endif
                </div>
            </div>
        </div>

        <div class="row g-4 mt-4">
            <x-sign class="col-6" role="นายทะเบียน" :name="school('registrar_name')" />
            <x-sign class="col-6" role="ผู้อำนวยการ{{ school('school_name') }}" :name="school('director_name')" />
        </div>
        <div class="small text-muted text-center mt-4">ใบรับรองนี้ใช้ได้เมื่อมีลายมือชื่อผู้มีอำนาจและประทับตราโรงเรียน</div>
    </div>
</div>
@endsection
