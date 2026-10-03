@extends('layouts.app')
@section('title', 'ผังค่าธรรมเนียม')

@section('content')
<div class="page-head">
    <div><h1>ผังค่าธรรมเนียม</h1><div class="sub">กำหนดรายการ → สร้างแผนเรียกเก็บรายระดับชั้น → ออกใบแจ้งหนี้ทั้งระดับชั้นในคลิกเดียว (หักส่วนลดประจำตัวให้อัตโนมัติ)</div></div>
    <div class="actions"><a href="{{ route('invoices.index') }}" class="btn btn-light border"><i class="bi bi-wallet2"></i> ใบแจ้งหนี้</a></div>
</div>

@if ($errors->any())<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>@endif

<div class="row g-3">
    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-list-check"></i> รายการค่าธรรมเนียม</div>
            @foreach ($items as $it)
                <form method="POST" action="{{ route('fees.items.update', $it) }}" class="d-flex gap-2 px-3 py-2 border-bottom align-items-center">
                    @csrf @method('PUT')
                    <input name="name" value="{{ $it->name }}" class="form-control form-control-sm" aria-label="ชื่อรายการ" required>
                    <input name="category" value="{{ $it->category }}" class="form-control form-control-sm" style="width:110px" aria-label="หมวด" list="feeCategories" required>
                    <input name="default_amount" type="number" step="0.01" min="0" value="{{ $it->default_amount }}" class="form-control form-control-sm" style="width:100px" aria-label="จำนวนเงิน" required>
                    <input type="checkbox" class="form-check-input" name="is_active" value="1" title="ใช้งาน" aria-label="ใช้งาน" @checked($it->is_active)>
                    <button class="btn btn-sm btn-light border" title="บันทึก"><i class="bi bi-check-lg"></i></button>
                </form>
            @endforeach
            <form method="POST" action="{{ route('fees.items.store') }}" class="d-flex gap-2 p-3">
                @csrf
                <input name="name" class="form-control form-control-sm" placeholder="รายการใหม่ เช่น ค่าเล่าเรียน" required>
                <input name="category" class="form-control form-control-sm" style="width:110px" placeholder="หมวด" list="feeCategories" value="ทั่วไป" required>
                <input name="default_amount" type="number" step="0.01" min="0" class="form-control form-control-sm" style="width:100px" placeholder="บาท" required>
                <button class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-plus"></i> เพิ่ม</button>
            </form>
            <datalist id="feeCategories">@foreach ($items->pluck('category')->unique() as $c)<option value="{{ $c }}">@endforeach</datalist>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-tag"></i> ส่วนลดประจำตัวนักเรียน</div>
            @forelse ($discounts as $d)
                <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom small">
                    <div class="flex-grow-1">
                        <b>{{ $d->student->fullName() }}</b> <span class="text-muted">{{ $d->student->student_code }} · {{ $d->student->classroom?->name() }}</span>
                        <div>{{ $d->name }} · ลด {{ $d->valueLabel() }} {{ $d->feeItem ? 'จาก '.$d->feeItem->name : 'จากยอดรวม' }}{{ $d->year ? ' · ปี '.$d->year : '' }}</div>
                    </div>
                    <form method="POST" action="{{ route('fees.discounts.destroy', $d) }}" data-confirm="ลบส่วนลดนี้?">@csrf @method('DELETE')<button class="btn btn-sm btn-light border text-danger" title="ลบ"><i class="bi bi-trash"></i></button></form>
                </div>
            @empty
                <div class="empty py-4"><i class="bi bi-tag"></i>ยังไม่มีส่วนลดประจำตัว</div>
            @endforelse
            <form method="POST" action="{{ route('fees.discounts.store') }}" class="row g-2 p-3">
                @csrf
                <div class="col-5"><input name="student_code" value="{{ old('student_code') }}" class="form-control form-control-sm" placeholder="รหัสนักเรียน" required></div>
                <div class="col-7"><input name="name" value="{{ old('name') }}" class="form-control form-control-sm" placeholder="เช่น ทุนเรียนดี, พี่น้อง" required></div>
                <div class="col-4"><input name="value" type="number" step="0.01" min="0.01" class="form-control form-control-sm" placeholder="จำนวน" required></div>
                <div class="col-3"><select name="type" class="form-select form-select-sm" aria-label="หน่วย">@foreach (\App\Models\StudentDiscount::TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div class="col-5"><select name="fee_item_id" class="form-select form-select-sm" aria-label="ลดจาก"><option value="">จากยอดรวม</option>@foreach ($items as $it)<option value="{{ $it->id }}">{{ $it->name }}</option>@endforeach</select></div>
                <div class="col-12 d-grid"><button class="btn btn-sm btn-primary"><i class="bi bi-plus"></i> เพิ่มส่วนลด</button></div>
            </form>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-plus-circle"></i> สร้างแผนเรียกเก็บ</div>
            <form method="POST" action="{{ route('fees.plans.store') }}" class="card-body row g-3">
                @csrf
                <div class="col-md-6"><label class="form-label">ชื่อใบแจ้งหนี้</label><input name="title" value="{{ old('title', $term ? 'ค่าธรรมเนียม'.$term->label() : '') }}" class="form-control" required></div>
                <div class="col-md-3"><label class="form-label">ภาคเรียน</label><select name="term_id" class="form-select">@foreach ($terms as $t)<option value="{{ $t->id }}" @selected($term?->id === $t->id)>{{ $t->shortLabel() }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">กำหนดชำระ</label><input type="date" name="due_date" value="{{ old('due_date') }}" class="form-control"></div>
                <div class="col-12">
                    <label class="form-label">ระดับชั้น <span class="text-muted fw-normal small">(เลือกได้หลายระดับ ยอดเท่ากัน)</span></label>
                    <div class="d-flex flex-wrap gap-3">@foreach ($levels as $l)<label class="small"><input type="checkbox" class="form-check-input" name="levels[]" value="{{ $l }}" @checked(in_array($l, old('levels', [])))> {{ $l }}</label>@endforeach</div>
                </div>
                <div class="col-12">
                    <label class="form-label">รายการและจำนวนเงิน <span class="text-muted fw-normal small">(เว้นว่างหรือ 0 = ไม่เก็บ)</span></label>
                    @forelse ($items->where('is_active', true) as $it)
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span class="flex-grow-1 small">{{ $it->name }} <span class="text-muted">· {{ $it->category }}</span></span>
                            <input name="amounts[{{ $it->id }}]" type="number" step="0.01" min="0" value="{{ old('amounts.'.$it->id, $it->default_amount ?: '') }}" class="form-control form-control-sm" style="width:140px" aria-label="จำนวนเงิน {{ $it->name }}">
                        </div>
                    @empty
                        <div class="text-muted small">เพิ่มรายการค่าธรรมเนียมทางซ้ายก่อน</div>
                    @endforelse
                </div>
                <div class="col-12 text-end"><button class="btn btn-primary" @disabled($items->isEmpty())><i class="bi bi-check-lg"></i> สร้างแผน</button></div>
            </form>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-card-checklist"></i> แผนเรียกเก็บ</div>
            @forelse ($plans as $p)
                @php($pending = $p->pendingStudents()->count())
                <div class="px-3 py-2 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <div class="flex-grow-1">
                            <b>{{ $p->title }}</b> <span class="badge bg-dark">{{ $p->level }}</span>
                            <div class="small text-muted">{{ $p->term?->shortLabel() }} · {{ baht($p->total()) }} บาท{{ $p->due_date ? ' · กำหนด '.thai_date($p->due_date) : '' }} · ออกแล้ว {{ $p->invoices_count }} ใบ · ยังไม่ออก {{ $pending }} คน</div>
                            <div class="small text-muted">{{ $p->items->map(fn ($i) => $i->feeItem->name.' '.baht($i->amount))->implode(' · ') }}</div>
                        </div>
                        @if ($pending)
                            <form method="POST" action="{{ route('fees.plans.issue', $p) }}" data-confirm="ออกใบแจ้งหนี้ {{ $p->title }} ให้นักเรียนชั้น {{ $p->level }} จำนวน {{ $pending }} คน? ผู้ปกครองที่เชื่อม LINE จะได้รับแจ้งทันที">@csrf<button class="btn btn-sm btn-success text-nowrap"><i class="bi bi-send"></i> ออกใบแจ้งหนี้</button></form>
                        @endif
                        @if ($p->invoices_count === 0)
                            <form method="POST" action="{{ route('fees.plans.destroy', $p) }}" data-confirm="ลบแผนนี้?">@csrf @method('DELETE')<button class="btn btn-sm btn-light border text-danger" title="ลบ"><i class="bi bi-trash"></i></button></form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty py-4"><i class="bi bi-card-checklist"></i>ยังไม่มีแผนเรียกเก็บ</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
