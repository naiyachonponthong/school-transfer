@extends('layouts.app')
@section('title', 'ทุนการศึกษา')

@section('content')
<div class="page-head">
    <div><h1>ทุนการศึกษา</h1><div class="sub">ปีการศึกษา {{ $year }} · {{ $scholarships->count() }} ทุน · ได้รับทุนแล้ว {{ number_format($scholarships->sum('approved_count')) }} คน</div></div>
    <div class="actions">
        <form method="GET"><select name="year" class="form-select" data-autosubmit aria-label="ปีการศึกษา">
            @foreach ($years as $y)<option value="{{ $y }}" @selected($y === $year)>ปี {{ $y }}</option>@endforeach
        </select></form>
        @if ($canManage)<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScholarship"><i class="bi bi-plus-lg"></i> ตั้งทุน</button>@endif
    </div>
</div>

<div class="card">
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>ทุน</th><th>ประเภท</th><th>วิธีมอบ</th><th class="text-end">มูลค่าต่อทุน</th><th class="text-end">ได้รับทุน</th><th class="text-end">ใช้งบไป</th><th class="text-end">รอพิจารณา</th><th>เสนอชื่อ</th></tr></thead>
        <tbody>
        @forelse ($scholarships as $s)
            <tr>
                <td><a href="{{ route('scholarships.show', $s) }}" class="fw-semibold">{{ $s->name }}</a><div class="small text-muted">{{ $s->donor }}</div></td>
                <td class="small">{{ \App\Models\Scholarship::CATEGORIES[$s->category] ?? $s->category }}</td>
                <td class="small">{{ \App\Models\Scholarship::MODES[$s->mode] ?? $s->mode }}</td>
                <td class="text-end">{{ $s->valueLabel() }}</td>
                <td class="text-end">{{ number_format($s->approved_count) }}{{ $s->slots !== null ? ' / '.number_format($s->slots) : '' }}</td>
                <td class="text-end small">{{ $s->isPercent() ? '-' : baht($s->approved_sum ?? 0).($s->budget !== null ? ' / '.baht($s->budget) : '') }}</td>
                <td class="text-end">@if ($s->nominated_count)<span class="badge bg-warning text-dark">{{ $s->nominated_count }}</span>@else<span class="text-muted">-</span>@endif</td>
                <td class="small">@if ($s->acceptsNominations())<span class="text-success">เปิดรับ{{ $s->closes_on ? ' ถึง '.thai_date($s->closes_on) : '' }}</span>@else<span class="text-muted">ปิดรับ</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty py-4"><i class="bi bi-mortarboard"></i>ยังไม่มีทุนในปีการศึกษานี้{{ $canManage ? ' กด "ตั้งทุน" เพื่อเริ่ม' : '' }}</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

@if ($canManage)
    <div class="modal fade" id="addScholarship" tabindex="-1">
        <div class="modal-dialog modal-lg"><form method="POST" action="{{ route('scholarships.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">ตั้งทุนการศึกษา</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body row g-3">@include('scholarships._fields', ['s' => null])</div>
            <div class="modal-footer"><button class="btn btn-primary">ตั้งทุน</button></div>
        </form></div>
    </div>
@endif
@endsection

@push('scripts')
@include('scholarships._script')
@endpush
