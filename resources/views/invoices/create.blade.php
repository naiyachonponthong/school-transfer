@extends('layouts.app')
@section('title', 'ออกใบแจ้งหนี้')

@section('content')
<div class="page-head"><div><h1>ออกใบแจ้งหนี้</h1><div class="sub">ออกทีเดียวทั้งห้อง ทั้งชั้น หรือเฉพาะบางคน</div></div></div>

<form method="POST" action="{{ route('invoices.store') }}">
    @csrf
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-receipt"></i> รายละเอียด</div>
                <div class="card-body row g-3">
                    <div class="col-12"><label class="form-label">ชื่อใบแจ้งหนี้</label><input name="title" value="{{ old('title', 'ค่าธรรมเนียมการศึกษา '.($current?->label() ?? '')) }}" class="form-control" required></div>
                    <div class="col-md-6">
                        <label class="form-label">ภาคเรียน</label>
                        <select name="term_id" class="form-select">
                            @foreach ($terms as $t)<option value="{{ $t->id }}" @selected($current?->id === $t->id)>{{ $t->label() }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">กำหนดชำระ</label><input type="date" name="due_date" value="{{ old('due_date', today()->addDays(14)->toDateString()) }}" class="form-control"></div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><i class="bi bi-list-ul"></i> รายการ <span class="ms-auto">รวม <b data-items-sum>0.00</b> บาท/คน</span></div>
                <div class="card-body">
                    <div id="items">
                        @foreach (old('items', [['description' => 'ค่าบำรุงการศึกษา', 'amount' => ''], ['description' => 'ค่าเอกสาร/แบบฝึกหัด', 'amount' => '']]) as $i => $it)
                            <div class="item-row d-flex gap-2 mb-2">
                                <input name="items[{{ $i }}][description]" value="{{ $it['description'] }}" class="form-control" placeholder="รายการ">
                                <input name="items[{{ $i }}][amount]" value="{{ $it['amount'] }}" type="number" step="0.01" min="0" class="form-control" style="max-width:150px" placeholder="บาท" data-amount>
                                <button type="button" class="btn btn-light border" data-remove-row><i class="bi bi-x"></i></button>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-sm btn-light border" data-add-row="#itemTpl" data-target="#items"><i class="bi bi-plus"></i> เพิ่มรายการ</button>
                    <template id="itemTpl">
                        <div class="item-row d-flex gap-2 mb-2">
                            <input name="items[__i__][description]" class="form-control" placeholder="รายการ">
                            <input name="items[__i__][amount]" type="number" step="0.01" min="0" class="form-control" style="max-width:150px" placeholder="บาท" data-amount>
                            <button type="button" class="btn btn-light border" data-remove-row><i class="bi bi-x"></i></button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><i class="bi bi-people"></i> ส่งถึง</div>
                <div class="card-body">
                    <label class="form-label">เลือกห้อง</label>
                    <div class="border rounded-3 p-2 mb-3" style="max-height:320px;overflow:auto">
                        @foreach ($classrooms->groupBy('level') as $level => $rooms)
                            <div class="d-flex align-items-center gap-2 mt-1">
                                <label class="small fw-semibold"><input type="checkbox" class="form-check-input" data-check-all=".lvl-{{ $loop->index }}"> {{ $level }} (ทั้งชั้น)</label>
                            </div>
                            <div class="d-flex flex-wrap gap-3 mb-2 ps-3">
                                @foreach ($rooms as $r)
                                    <label class="small"><input type="checkbox" name="classroom_ids[]" value="{{ $r->id }}" class="form-check-input lvl-{{ $loop->parent->index }}" @checked(in_array($r->id, old('classroom_ids', [])))> {{ $r->name() }} <span class="text-muted">({{ $r->students_count }})</span></label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <label class="form-label">และ/หรือ ระบุรหัสนักเรียน</label>
                    <textarea name="student_code" rows="2" class="form-control" placeholder="เช่น 10001, 10002">{{ old('student_code') }}</textarea>
                </div>
                <div class="card-footer bg-transparent"><button class="btn btn-primary btn-lg w-100"><i class="bi bi-send"></i> ออกใบแจ้งหนี้</button></div>
            </div>
        </div>
    </div>
</form>
@endsection
