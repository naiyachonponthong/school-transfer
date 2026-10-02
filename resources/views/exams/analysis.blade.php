@extends('layouts.app')
@section('title', 'วิเคราะห์ข้อสอบ · '.$exam->title)

@section('content')
@include('exams._nav')

@if ($pendingReview)
    <div class="alert alert-warning no-print"><i class="bi bi-exclamation-triangle"></i> ยังมี {{ $pendingReview }} แผ่นรอตรวจทาน — ไม่นับในการวิเคราะห์ <a href="{{ route('exams.results', [$exam, 'tab' => 'review']) }}">ไปตรวจทาน</a></div>
@endif
@unless ($exam->keyReady())
    <div class="alert alert-warning no-print"><i class="bi bi-key"></i> ยังใส่เฉลยไม่ครบ — ข้อที่ไม่มีเฉลยจะไม่ถูกวิเคราะห์ <a href="{{ route('exams.show', $exam) }}">ใส่เฉลย</a></div>
@endunless

@php
    // หัวรายงาน/ไฟล์ .txt แบบ EVANA
    $meta = ['subject_code' => $exam->subject->code, 'subject_name' => $exam->subject->name.' · '.$exam->title, 'term' => $exam->term?->term,
        'year' => $exam->term?->year, 'teacher' => $exam->creator?->name, 'school' => school('school_name')];
@endphp
<div id="anApp" data-papers='@json($papers)' data-key='@json($exam->key())' data-cancelled='@json($exam->cancelledItems())'
     data-meta='@json($meta)'>
    <div class="card mb-3 no-print">
        <div class="card-body d-flex flex-wrap gap-3 align-items-end">
            <div>
                <label class="form-label small">เทคนิค</label>
                <select id="anTech" class="form-select">
                    <option value="27">27% (ตาราง Chung-Teh Fan)</option>
                    <option value="25">25% (สูตรอย่างง่าย)</option>
                </select>
            </div>
            <div>
                <label class="form-label small">ห้อง</label>
                <select id="anRoom" class="form-select">
                    <option value="">ทุกห้องรวมกัน</option>
                    @foreach ($rooms as $r)<option value="{{ $r->id }}">{{ $r->name() }}</option>@endforeach
                </select>
            </div>
            <div class="ms-auto d-flex gap-2">
                <button class="btn btn-light border" id="anTxt"><i class="bi bi-filetype-txt"></i> ไฟล์ .txt แบบ EVANA</button>
                <button class="btn btn-light border" onclick="print()"><i class="bi bi-printer"></i> พิมพ์รายงาน</button>
            </div>
        </div>
    </div>

    <div class="print-only text-center mb-2">
        <h5 class="mb-0">{{ school('school_name') }}</h5>
        <div>การวิเคราะห์ข้อสอบรายข้อ วิชา {{ $exam->subject->code }} {{ $exam->subject->name }} · {{ $exam->title }}</div>
    </div>

    <div id="anBody"></div>
</div>
@endsection

@push('scripts')
@include('exams._scripts', ['files' => ['common.js', 'evana.js', 'analysis.js']])
@endpush
