@extends('layouts.app')
@section('title', $survey->title)

@section('content')
@php
    $done = $responses->filter(fn ($r) => $r->contains('respondent_role', 'teacher'))->count();
@endphp
<div class="page-head">
    <div><h1>{{ $survey->title }}</h1><div class="sub">ห้อง {{ $classroom?->name() }} · {{ $term?->label() }}</div></div>
    <div class="actions">
        <form method="GET"><select name="classroom" class="form-select" data-autosubmit>@foreach ($classrooms as $c)<option value="{{ $c->id }}" @selected($classroom?->id === $c->id)>{{ $c->name() }}</option>@endforeach</select></form>
        <button class="btn btn-light border" onclick="print()"><i class="bi bi-printer"></i></button>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card h-100"><div class="card-body d-flex align-items-center gap-3">
        <x-ring :value="$students->count() ? $done / $students->count() * 100 : 0" :size="86" :label="$done.'/'.$students->count()" sub="ครูประเมินแล้ว" />
        <div class="small text-muted">{{ \App\Models\Survey::RESPONDENTS[$survey->respondent] }}</div>
    </div></div></div>
    <div class="col-md-8"><div class="card h-100"><div class="card-body">
        <div class="card-title-sm">สรุปผลรวม (ครูประเมิน)</div>
        <div class="d-flex flex-wrap gap-2">
            @foreach ($survey->total_bands ?? [] as $b)
                <span class="badge bg-{{ $b['color'] }}-subtle text-{{ $b['color'] }}-emphasis fs-6 fw-normal">{{ $b['label'] }} {{ $summary[$b['label']] ?? 0 }} คน</span>
            @endforeach
        </div>
    </div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr><th>เลขที่ · ชื่อ</th>
                    @foreach ($survey->subscales as $sub)<th class="text-center small">{{ $sub['name'] }}</th>@endforeach
                    <th class="text-center">รวม</th><th>ผู้ปกครอง</th><th>นักเรียน</th><th></th></tr>
            </thead>
            <tbody>
            @foreach ($students as $s)
                @php
                    $mine = ($responses[$s->id] ?? collect())->firstWhere('respondent_role', 'teacher');
                    $par = ($responses[$s->id] ?? collect())->firstWhere('respondent_role', 'parent');
                    $self = ($responses[$s->id] ?? collect())->firstWhere('respondent_role', 'student');
                    $sc = $mine?->scores;
                @endphp
                <tr>
                    <td class="text-nowrap"><span class="text-muted">{{ $s->number }}</span> {{ $s->fullName() }}</td>
                    @foreach ($survey->subscales as $sub)
                        @php($cell = $sc['subscales'][$sub['key']] ?? null)
                        <td class="text-center">@if($cell)<span class="badge bg-{{ $cell['band']['color'] ?? 'secondary' }}-subtle text-{{ $cell['band']['color'] ?? 'secondary' }}-emphasis" title="{{ $cell['band']['label'] ?? '' }}">{{ $cell['score'] }}</span>@else<span class="text-muted">-</span>@endif</td>
                    @endforeach
                    <td class="text-center">@if($sc && $sc['total'] !== null)<span class="badge bg-{{ $sc['total_band']['color'] ?? 'secondary' }}">{{ $sc['total'] }} {{ $sc['total_band']['label'] ?? '' }}</span>@else - @endif</td>
                    <td class="small">@if($par)<span class="badge bg-{{ $par->scores['total_band']['color'] ?? 'secondary' }}-subtle text-{{ $par->scores['total_band']['color'] ?? 'secondary' }}-emphasis">{{ $par->scores['total'] }} {{ $par->scores['total_band']['label'] ?? '' }}</span>@else<span class="text-muted">-</span>@endif</td>
                    <td class="small">@if($self)<span class="badge bg-{{ $self->scores['total_band']['color'] ?? 'secondary' }}-subtle text-{{ $self->scores['total_band']['color'] ?? 'secondary' }}-emphasis">{{ $self->scores['total'] }} {{ $self->scores['total_band']['label'] ?? '' }}</span>@else<span class="text-muted">-</span>@endif</td>
                    <td class="text-end">
                        @if ($survey->allows('teacher'))
                            <a href="{{ route('surveys.fill', [$survey, $s]) }}" class="btn btn-sm {{ $mine ? 'btn-light border' : 'btn-primary' }}">{{ $mine ? 'แก้ไข' : 'ประเมิน' }}</a>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
