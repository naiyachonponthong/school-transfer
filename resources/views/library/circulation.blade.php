@extends('layouts.app')
@section('title', 'ห้องสมุด · ยืม-คืน')

@section('content')
<div class="page-head">
    <div><h1>ห้องสมุด · ยืม-คืน</h1><div class="sub">สแกนบัตรนักเรียน แล้วสแกนบาร์โค้ดหนังสือ</div></div>
    <div class="actions">
        <a href="{{ route('library.index') }}" class="btn btn-light border"><i class="bi bi-book"></i> ทะเบียนหนังสือ</a>
        <form method="POST" action="{{ route('library.remind') }}" data-confirm="ส่ง LINE แจ้งผู้ปกครองของนักเรียนที่เกินกำหนดคืน?">@csrf<button class="btn btn-light border"><i class="bi bi-bell"></i> แจ้งเกินกำหนด</button></form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-7">
        <form method="POST" action="{{ route('library.borrow') }}" class="card h-100">
            @csrf
            <div class="card-header"><i class="bi bi-box-arrow-up-right text-primary"></i> ยืม</div>
            <div class="card-body row g-2">
                <div class="col-sm-6"><label class="form-label">นักเรียน (รหัส / สแกนบัตร)</label><input name="student" value="{{ old('student') }}" class="form-control" required autofocus></div>
                <div class="col-sm-4"><label class="form-label">รหัสหนังสือ</label><input name="book" class="form-control" required></div>
                <div class="col-sm-2"><label class="form-label">วัน</label><input name="days" type="number" value="{{ $loanDays }}" class="form-control"></div>
                <div class="col-12"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> บันทึกการยืม</button> <span class="small text-muted ms-2">นับเฉพาะวันทำการ · ยืมได้ต่อเนื่อง ช่องนักเรียนจะค้างไว้ให้</span></div>
            </div>
        </form>
    </div>
    <div class="col-md-5">
        <form method="POST" action="{{ route('library.return.code') }}" class="card h-100">
            @csrf
            <div class="card-header"><i class="bi bi-box-arrow-in-down-left text-success"></i> คืน</div>
            <div class="card-body">
                <label class="form-label">สแกนรหัสหนังสือที่คืน</label>
                <div class="input-group"><input name="book" class="form-control" required><button class="btn btn-success">คืน</button></div>
                @if ($recentReturns->isNotEmpty())
                    <div class="small text-muted mt-3">คืนล่าสุด: {{ $recentReturns->take(3)->map(fn ($l) => $l->book->title)->implode(', ') }}</div>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-journal-bookmark"></i> กำลังยืม ({{ $loans->total() }})
        <form class="ms-auto" method="GET"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="ค้นหา"></form></div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>หนังสือ</th><th>ผู้ยืม</th><th>ยืมเมื่อ</th><th>กำหนดคืน</th><th></th></tr></thead>
            <tbody>
            @forelse ($loans as $l)
                <tr class="{{ $l->isOverdue() ? 'table-danger' : '' }}">
                    <td><div class="fw-semibold">{{ $l->book->title }}</div><div class="small text-muted">{{ $l->book->code }}</div></td>
                    <td>{{ $l->student->fullName() }} <span class="small text-muted">{{ $l->student->classroom?->name() }}</span></td>
                    <td class="small">{{ thai_date($l->borrowed_on) }}</td>
                    <td class="small {{ $l->isOverdue() ? 'text-danger fw-bold' : '' }}">{{ thai_date($l->due_on) }}@if($l->isOverdue()) (เกิน {{ $l->due_on->diffInDays(today()) }} วัน)@endif</td>
                    <td class="text-end"><form method="POST" action="{{ route('library.return', $l) }}">@csrf<button class="btn btn-sm btn-success">รับคืน</button></form></td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty"><i class="bi bi-book"></i>ไม่มีหนังสือที่ถูกยืมอยู่</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $loans->links() }}</div>
@endsection
