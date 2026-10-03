@extends('layouts.app')
@section('title', 'ปีการศึกษา')

@section('content')
<div class="page-head"><div><h1>ปีการศึกษา / ภาคเรียน</h1><div class="sub">ภาคเรียนปัจจุบันใช้เป็นค่าเริ่มต้นของทุกหน้า</div></div></div>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-cards align-middle">
                    <thead><tr><th>ภาคเรียน</th><th>เปิดภาค</th><th>ปิดภาค</th><th>ประกาศผล</th><th class="text-center">รายวิชา</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($terms as $t)
                        <tr class="{{ $t->is_current ? 'table-primary' : '' }}">
                                <td class="fw-semibold">{{ $t->label() }} @if($t->is_current)<span class="badge bg-primary">ปัจจุบัน</span>@endif</td>
                                <td><input form="tm{{ $t->id }}" type="date" name="start_date" value="{{ $t->start_date?->toDateString() }}" class="form-control form-control-sm"></td>
                                <td><input form="tm{{ $t->id }}" type="date" name="end_date" value="{{ $t->end_date?->toDateString() }}" class="form-control form-control-sm"></td>
                                <td><input form="tm{{ $t->id }}" type="date" name="results_announce_on" value="{{ $t->results_announce_on?->toDateString() }}" class="form-control form-control-sm" title="ก่อนวันนี้ผู้ปกครอง/นักเรียนยังไม่เห็นผลการเรียน" aria-label="วันประกาศผล"></td>
                                <td class="text-center">{{ $t->courses_count }}</td>
                                <td class="text-end text-nowrap">
                                <form id="tm{{ $t->id }}" method="POST" action="{{ route('terms.update', $t) }}" class="d-inline">@csrf @method('PUT')<button class="btn btn-sm btn-light border"><i class="bi bi-check-lg"></i></button></form>
                                @unless ($t->is_current)
                                    <form method="POST" action="{{ route('terms.current', $t) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-primary">ตั้งเป็นปัจจุบัน</button></form>
                                @endunless
                                </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">ยังไม่มีภาคเรียน</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <form method="POST" action="{{ route('terms.store') }}" class="card">
            @csrf
            <div class="card-header"><i class="bi bi-plus-circle"></i> เพิ่มภาคเรียน</div>
            <div class="card-body row g-2">
                @php($last = $terms->first())
                <div class="col-6"><label class="form-label">ปีการศึกษา</label><input name="year" type="number" value="{{ $last ? ($last->term >= 2 ? $last->year + 1 : $last->year) : now()->year + 543 }}" class="form-control" required></div>
                <div class="col-6"><label class="form-label">ภาค</label><select name="term" class="form-select"><option value="1" @selected($last?->term >= 2)>1</option><option value="2" @selected($last?->term == 1)>2</option><option value="3">3 (ฤดูร้อน)</option></select></div>
                <div class="col-6"><label class="form-label">เปิดภาค</label><input type="date" name="start_date" class="form-control"></div>
                <div class="col-6"><label class="form-label">ปิดภาค</label><input type="date" name="end_date" class="form-control"></div>
                <div class="col-12"><label class="small"><input type="checkbox" name="make_current" value="1" class="form-check-input" checked> ตั้งเป็นภาคเรียนปัจจุบัน</label></div>
            </div>
            <div class="card-footer bg-transparent"><button class="btn btn-primary w-100">เพิ่ม</button></div>
        </form>
    </div>
</div>
@endsection
