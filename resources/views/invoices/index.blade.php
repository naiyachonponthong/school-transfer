@extends('layouts.app')
@section('title', 'ค่าธรรมเนียม')

@section('content')
<div class="page-head">
    <div><h1>ค่าธรรมเนียมการศึกษา</h1><div class="sub">ใบแจ้งหนี้และการรับชำระ</div></div>
    @if (auth()->user()->hasPermission('finance.manage'))
        <div class="actions"><a href="{{ route('invoices.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> ออกใบแจ้งหนี้</a></div>
    @endif
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-danger"><i class="bi bi-cash-stack"></i></div><div><div class="stat-value fs-5">{{ baht($summary['outstanding']) }}</div><div class="stat-label">ยอดค้างชำระทั้งหมด</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-warning"><i class="bi bi-alarm"></i></div><div><div class="stat-value fs-5">{{ $summary['overdue'] }}</div><div class="stat-label">ใบที่เลยกำหนด</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-success"><i class="bi bi-wallet2"></i></div><div><div class="stat-value fs-5">{{ baht($summary['collected_today']) }}</div><div class="stat-label">รับชำระวันนี้</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-primary"><i class="bi bi-calendar-month"></i></div><div><div class="stat-value fs-5">{{ baht($summary['collected_month']) }}</div><div class="stat-label">รับชำระเดือนนี้</div></div></div></div></div>
</div>

<form class="card mb-3" method="GET">
    <div class="card-body d-flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" class="form-control" style="max-width:280px" placeholder="เลขที่ ชื่อนักเรียน รายการ">
        <select name="status" class="form-select w-auto" data-autosubmit>
            <option value="open" @selected($status === 'open')>ค้างชำระ</option>
            @foreach (\App\Models\Invoice::STATUSES as $k => [$v])<option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>@endforeach
            <option value="all" @selected($status === 'all')>ทั้งหมด</option>
        </select>
        <select name="classroom" class="form-select w-auto" data-autosubmit>
            <option value="">ทุกห้อง</option>
            @foreach ($classrooms as $c)<option value="{{ $c->id }}" @selected(request('classroom') == $c->id)>{{ $c->name() }}</option>@endforeach
        </select>
        <button class="btn btn-primary">ค้นหา</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-cards table-hover align-middle">
            <thead><tr><th>เลขที่</th><th>นักเรียน</th><th>รายการ</th><th>กำหนดชำระ</th><th class="text-end">ยอดสุทธิ</th><th class="text-end">ค้างชำระ</th><th>สถานะ</th></tr></thead>
            <tbody>
            @forelse ($invoices as $inv)
                <tr data-href="{{ route('invoices.show', $inv) }}" style="cursor:pointer">
                    <td class="text-muted small">{{ $inv->invoice_no }}</td>
                    <td class="tc-title"><span class="fw-semibold">{{ $inv->student->fullName() }}</span> <span class="small text-muted">{{ $inv->student->classroom?->name() }}</span></td>
                    <td>{{ $inv->title }}</td>
                    <td class="small {{ $inv->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $inv->due_date ? thai_date($inv->due_date) : '-' }}</td>
                    <td class="text-end">{{ baht($inv->netTotal()) }}</td>
                    <td class="text-end fw-semibold">{{ baht($inv->balance()) }}</td>
                    <td><span class="badge bg-{{ $inv->statusColor() }}">{{ $inv->statusLabel() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty"><i class="bi bi-receipt"></i>ไม่มีรายการ</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $invoices->links() }}</div>
@endsection
