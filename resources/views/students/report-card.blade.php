@extends('layouts.app')
@section('title', isset($classroom) ? 'ปพ.6 ห้อง '.$classroom->name() : 'ปพ.6 '.$cards[0]['student']->fullName())

@section('content')
<div class="d-flex gap-2 mb-3 no-print">
    <a href="{{ $back }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
    @isset($classroom)<span class="align-self-center small text-muted">ห้อง {{ $classroom->name() }} · {{ count($cards) }} คน (หนึ่งคนต่อหนึ่งหน้า)</span>@endisset
    <button onclick="print()" class="btn btn-primary ms-auto"><i class="bi bi-printer"></i> พิมพ์ / บันทึก PDF</button>
</div>

@forelse ($cards as $d)
    @include('students._report-card', ['d' => $d, 'term' => $term])
@empty
    <div class="card"><div class="empty"><i class="bi bi-people"></i>ห้องนี้ยังไม่มีนักเรียน</div></div>
@endforelse
@endsection
