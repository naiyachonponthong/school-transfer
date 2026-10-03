@extends('layouts.app')
@section('title', $form->title)

@section('content')
@php($pending = $students->reject(fn ($s) => isset($responses[$s->id])))
<div class="page-head">
    <div><h1>{{ $form->title }}</h1><div class="sub">ตอบแล้ว {{ $responses->count() }}/{{ $students->count() }} · อนุญาต {{ $responses->where('agreed', true)->count() }} · ไม่อนุญาต {{ $responses->where('agreed', false)->count() }} · ยังไม่ตอบ {{ $pending->count() }}{{ $form->due_date ? ' · ตอบภายใน '.thai_date($form->due_date) : '' }}</div></div>
    <div class="actions">
        <a href="{{ route('consents.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
        <form method="POST" action="{{ route('consents.close', $form) }}">@csrf<button class="btn btn-light border">{{ $form->is_open ? 'ปิดรับคำตอบ' : 'เปิดรับคำตอบอีกครั้ง' }}</button></form>
        <button onclick="print()" class="btn btn-light border"><i class="bi bi-printer"></i> พิมพ์</button>
    </div>
</div>

<div class="card mb-3"><div class="card-body" style="white-space:pre-line">{{ $form->body }}</div></div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>ห้อง</th><th>นักเรียน</th><th>คำตอบ</th><th>ผู้ตอบ</th><th class="no-print text-end">ครูบันทึกแทน</th></tr></thead>
            <tbody>
            @foreach ($students as $s)
                @php($r = $responses[$s->id] ?? null)
                <tr>
                    <td>{{ $s->classroom->name() }}</td>
                    <td class="text-nowrap"><span class="text-muted me-1">{{ $s->number }}</span> {{ $s->fullName() }}</td>
                    <td>
                        @if (! $r)<span class="badge bg-secondary">ยังไม่ตอบ</span>
                        @elseif ($r->agreed)<span class="badge bg-success">อนุญาต</span>
                        @else<span class="badge bg-danger">ไม่อนุญาต</span>@endif
                    </td>
                    <td class="small text-muted">{{ $r ? ($r->user?->name ?? '-').' · '.thai_datetime($r->updated_at).($r->note ? ' · '.$r->note : '') : '' }}</td>
                    <td class="text-end text-nowrap no-print">
                        @foreach ([1 => ['อนุญาต', 'success'], 0 => ['ไม่อนุญาต', 'danger']] as $val => [$label, $color])
                            <form method="POST" action="{{ route('consents.record', $form) }}" class="d-inline">
                                @csrf<input type="hidden" name="student_id" value="{{ $s->id }}"><input type="hidden" name="agreed" value="{{ $val }}">
                                <button class="btn btn-sm btn-outline-{{ $color }}">{{ $label }}</button>
                            </form>
                        @endforeach
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
