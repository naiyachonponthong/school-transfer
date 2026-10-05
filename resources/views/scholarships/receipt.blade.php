@extends('layouts.app')
@section('title', 'ใบสำคัญรับเงินทุน '.$award->doc_no)

@section('content')
<div class="d-flex gap-2 mb-3 no-print">
    <a href="{{ route('scholarships.show', $award->scholarship) }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
    <button onclick="print()" class="btn btn-light border ms-auto"><i class="bi bi-printer"></i> พิมพ์</button>
</div>

<div class="card doc-page" style="max-width:820px;margin:auto"><div class="card-body p-4">
    <div class="d-flex justify-content-between small"><span>{{ school('school_name') }}</span><span>เลขที่ {{ $award->doc_no }}</span></div>
    <div class="text-center mb-4"><h2 class="h6 fw-bold mb-0">ใบสำคัญรับเงินทุนการศึกษา</h2></div>
    <div class="small text-end mb-3">วันที่ {{ \App\Support\Thai::fullDate($award->paid_at) }}</div>
    <p class="small" style="line-height:2">
        ข้าพเจ้า <b>{{ $award->received_by }}</b> ได้รับเงินทุนการศึกษา "<b>{{ $award->scholarship->name }}</b>" ปีการศึกษา {{ $award->scholarship->year }}{{ $award->scholarship->donor ? ' จาก '.$award->scholarship->donor : '' }}
        สำหรับ <b>{{ $award->student->fullName() }}</b> รหัสนักเรียน {{ $award->student->student_code }} ชั้น {{ $award->student->classroom?->name() ?? '-' }}
        เป็นจำนวนเงิน <b>{{ baht($award->amount) }} บาท</b> ({{ \App\Support\Thai::bahtText((float) $award->amount) }}) ไว้เป็นการถูกต้องแล้ว
    </p>
    <div class="row g-3 mt-5">
        <x-sign class="col-6" role="ผู้รับเงิน" :name="$award->received_by" />
        <x-sign class="col-6" role="ผู้จ่ายเงิน" :name="$award->payer?->name" />
    </div>
</div></div>
@endsection
