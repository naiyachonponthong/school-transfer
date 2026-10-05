@extends('layouts.app')
@section('title', 'งบประมาณ')

@section('content')
@php
    $total = $rows->sum(fn ($r) => (float) $r['source']->amount);
    $allocated = $rows->sum('allocated');
    $spent = $rows->sum('spent');
@endphp
<div class="page-head">
    <div><h1>งบประมาณ</h1><div class="sub">ปีงบประมาณ {{ $year }} (1 ต.ค. {{ $year - 1 }} – 30 ก.ย. {{ $year }}) · {{ $projectCount }} โครงการ</div></div>
    <div class="actions">
        <form method="GET"><select name="year" class="form-select" data-autosubmit aria-label="ปีงบประมาณ">
            @foreach ($years as $y)<option value="{{ $y }}" @selected($y === $year)>ปีงบประมาณ {{ $y }}</option>@endforeach
        </select></form>
        <a href="{{ route('projects.index', ['year' => $year]) }}" class="btn btn-light border"><i class="bi bi-kanban"></i> โครงการ</a>
        <a href="{{ route('budget-requests.index') }}" class="btn btn-light border"><i class="bi bi-cart-check"></i> คำขอใช้งบ</a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSource"><i class="bi bi-plus-lg"></i> เพิ่มประเภทเงิน</button>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-primary"><i class="bi bi-bank"></i></div><div><div class="stat-value">{{ baht($total, 0) }}</div><div class="stat-label">วงเงินที่ได้รับ</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-info"><i class="bi bi-kanban"></i></div><div><div class="stat-value">{{ baht($allocated, 0) }}</div><div class="stat-label">จัดสรรให้กิจกรรมแล้ว</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-warning"><i class="bi bi-scissors"></i></div><div><div class="stat-value">{{ baht($spent, 0) }}</div><div class="stat-label">ตัดงบแล้ว · รอพิจารณา {{ baht($pending, 0) }}</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-success"><i class="bi bi-piggy-bank"></i></div><div><div class="stat-value">{{ baht($total - $allocated, 0) }}</div><div class="stat-label">ยังไม่ได้จัดสรร</div></div></div></div></div>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-bank"></i> ประเภทเงิน
        @if ($rows->count() < count(\App\Models\BudgetSource::STANDARD))
            <form method="POST" action="{{ route('budget.sources.standard') }}" class="ms-auto">@csrf<input type="hidden" name="fiscal_year" value="{{ $year }}"><button class="btn btn-sm btn-light border"><i class="bi bi-magic"></i> สร้าง 4 ประเภทมาตรฐาน</button></form>
        @endif
    </div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>ประเภทเงิน</th><th class="text-end">วงเงิน</th><th class="text-end">จัดสรรแล้ว</th><th class="text-end">ตัดงบแล้ว</th><th class="text-end">ยังไม่จัดสรร</th><th></th></tr></thead>
        <tbody>
        @forelse ($rows as $r)
            @php($s = $r['source'])
            <tr>
                <td><span class="fw-semibold">{{ $s->name }}</span><div class="small text-muted">{{ $s->note }}</div></td>
                <td class="text-end">{{ baht($s->amount) }}</td>
                <td class="text-end">{{ baht($r['allocated']) }}</td>
                <td class="text-end">{{ baht($r['spent']) }}</td>
                <td class="text-end fw-semibold">{{ baht((float) $s->amount - $r['allocated']) }}</td>
                <td class="text-end"><button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#editSource{{ $s->id }}" aria-label="แก้ไข {{ $s->name }}"><i class="bi bi-pencil"></i></button></td>
            </tr>
        @empty
            <tr><td colspan="6"><div class="empty py-4"><i class="bi bi-bank"></i>ยังไม่มีประเภทเงินของปีงบประมาณนี้ กด "สร้าง 4 ประเภทมาตรฐาน" หรือ "เพิ่มประเภทเงิน"</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

<form method="POST" action="{{ route('budget.steps') }}" class="card">
    @csrf
    <div class="card-header"><i class="bi bi-diagram-3"></i> ขั้นพิจารณาคำขอใช้งบ</div>
    <div class="card-body row g-3">
        @foreach (\App\Models\BudgetRequest::STEPS as $key => [$label, $permission])
            <div class="col-md-6 col-xl-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="steps[]" value="{{ $key }}" id="step{{ $key }}" @checked(in_array($key, $steps, true)) @disabled($key === 'cut')>
                    <label class="form-check-label" for="step{{ $key }}">{{ $loop->iteration }}. {{ $label }}</label>
                </div>
                <div class="form-text ms-4">{{ $key === 'cut' ? 'มีเสมอ · แยกกลุ่มทั่วไป / ห้องเรียนพิเศษ' : 'สิทธิ์ '.$permission }}</div>
            </div>
        @endforeach
        <div class="col-12 small text-muted">คำขอผ่านขั้นที่ติ๊กตามลำดับ แล้วจบที่เจ้าหน้าที่ตัดงบซึ่งระบุว่าตัดจากประเภทเงินใด · ผู้พิจารณาแต่ละขั้นกำหนดที่เมนูตำแหน่งและสิทธิ์ · เปลี่ยนแล้วมีผลกับใบที่ยื่นหลังจากนี้</div>
    </div>
    <div class="card-footer bg-transparent text-end"><button class="btn btn-light border">บันทึกขั้นพิจารณา</button></div>
</form>

@php($fields = fn ($s = null) => view('budget._source-fields', ['s' => $s, 'year' => $year])->render())
<div class="modal fade" id="addSource" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="{{ route('budget.sources.store') }}" class="modal-content">
        @csrf
        <div class="modal-header"><h5 class="modal-title">เพิ่มประเภทเงิน</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
        <div class="modal-body row g-3">{!! $fields() !!}</div>
        <div class="modal-footer"><button class="btn btn-primary">เพิ่มประเภทเงิน</button></div>
    </form></div>
</div>
@foreach ($rows as $r)
    <div class="modal fade" id="editSource{{ $r['source']->id }}" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="{{ route('budget.sources.update', $r['source']) }}" id="sourceForm{{ $r['source']->id }}">
                @csrf @method('PUT')
                <div class="modal-header"><h5 class="modal-title">แก้ไขประเภทเงิน</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
                <div class="modal-body row g-3">{!! $fields($r['source']) !!}</div>
            </form>
            <div class="modal-footer">
                @if ($r['allocated'] == 0)
                    <form method="POST" action="{{ route('budget.sources.destroy', $r['source']) }}" class="me-auto" data-confirm="ลบประเภทเงิน {{ $r['source']->name }}?">@csrf @method('DELETE')<button class="btn btn-outline-danger"><i class="bi bi-trash"></i> ลบ</button></form>
                @endif
                <button class="btn btn-primary" form="sourceForm{{ $r['source']->id }}">บันทึก</button>
            </div>
        </div></div>
    </div>
@endforeach
@endsection
