@extends('layouts.app')
@section('title', $survey->title)

@push('head')
<style>
    .q-row { padding: .8rem 1rem; border-bottom: 1px solid #f0f1f4; }
    .q-row.missing { background: #fff7f7; }
    .q-opts { display: grid; grid-template-columns: repeat(auto-fit, minmax(90px, 1fr)); gap: .4rem; margin-top: .5rem; }
    .q-opts label { text-align: center; border: 1.5px solid var(--sb-border); border-radius: 11px; padding: .45rem .3rem; cursor: pointer; font-size: .88rem; }
    .q-opts input { display: none; }
    .q-opts input:checked + span { color: #fff; }
    .q-opts label:has(input:checked) { background: var(--sb-primary); border-color: var(--sb-primary); color: #fff; }
</style>
@endpush

@section('content')
@php
    $prev = $existing?->answers ?? [];
@endphp
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="page-head">
            <div><h1>{{ $survey->title }}</h1><div class="sub">{{ $student->fullName() }} · ห้อง {{ $student->classroom?->name() }} · {{ $term?->label() }} · ผู้ตอบ: {{ ['parent' => 'ผู้ปกครอง', 'student' => 'นักเรียน (ประเมินตนเอง)'][$role] ?? 'ครู' }}</div></div>
        </div>
        @if ($survey->description)<div class="alert alert-light border small">{{ $survey->description }}</div>@endif
        @if ($existing)
            <div class="alert alert-success small"><i class="bi bi-check-circle"></i> ประเมินแล้วเมื่อ {{ thai_datetime($existing->updated_at) }} ·
                ผลรวม <b>{{ $existing->scores['total'] ?? '-' }}</b> {{ $existing->scores['total_band']['label'] ?? '' }} — แก้ไขคำตอบด้านล่างแล้วบันทึกใหม่ได้</div>
        @endif
        <form method="POST" action="{{ route('surveys.save', [$survey, $student]) }}" class="card overflow-hidden">
            @csrf
            @foreach ($survey->items as $i => $item)
                <div class="q-row {{ $errors->has('a.'.$item->id) ? 'missing' : '' }}">
                    <div><span class="text-muted me-1">{{ $i + 1 }}.</span> {{ $item->text }}</div>
                    <div class="q-opts">
                        @foreach ($survey->scale as $opt)
                            <label><input type="radio" name="a[{{ $item->id }}]" value="{{ $opt['value'] }}" @checked((string) old('a.'.$item->id, $prev[$item->id] ?? '') === (string) $opt['value'])><span>{{ $opt['label'] }}</span></label>
                        @endforeach
                    </div>
                </div>
            @endforeach
            <div class="card-footer bg-transparent d-flex gap-2">
                <button class="btn btn-primary btn-lg"><i class="bi bi-save"></i> บันทึกผลการประเมิน</button>
                <a href="{{ url()->previous() }}" class="btn btn-light border btn-lg">ยกเลิก</a>
            </div>
        </form>
    </div>
</div>
@endsection
