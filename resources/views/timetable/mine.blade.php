@extends('layouts.app')
@section('title', 'ตารางสอนของฉัน')

@section('content')
<div class="page-head">
    <div>
        <h1>ตารางสอนของฉัน</h1>
        <div class="sub">{{ $term?->label() }} · สอน {{ $totalPeriods }} คาบ/สัปดาห์</div>
    </div>
    <div class="actions no-print"><button class="btn btn-light border" onclick="print()"><i class="bi bi-printer"></i> พิมพ์</button></div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table tt mb-0">
            <thead><tr><th class="day">วัน / คาบ</th>@foreach ($periods as $i => $time)<th>คาบ {{ $i + 1 }}<div class="small text-muted fw-normal">{{ $time }}</div></th>@endforeach</tr></thead>
            <tbody>
            @foreach (\App\Models\TimetableSlot::DAYS as $d => $dayName)
                <tr>
                    <th class="day {{ $d === now()->dayOfWeekIso ? 'text-primary' : '' }}">{{ $dayName }}</th>
                    @foreach ($periods as $i => $time)
                        @php($slot = $slots[$d.'-'.($i + 1)] ?? null)
                        <td class="p-1">
                            @if ($slot)
                                <div class="slot" style="background:#eef2ff">
                                    <b>{{ $slot->course->subject->name }}</b>
                                    <small class="fw-semibold text-primary">ห้อง {{ $slot->classroom->name() }}</small>
                                </div>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@if ($totalPeriods === 0)
    <p class="text-muted small mt-2">ยังไม่มีคาบสอน ฝ่ายวิชาการจัดตารางได้ที่เมนู "ตารางเรียน"</p>
@endif
@endsection
