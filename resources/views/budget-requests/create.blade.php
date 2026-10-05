@extends('layouts.app')
@section('title', 'เขียนคำขอใช้งบ')

@section('content')
<div class="page-head">
    <div><h1>เขียนคำขอใช้งบ</h1><div class="sub">ลำดับการพิจารณา: {{ collect($steps)->map(fn ($k) => \App\Models\BudgetRequest::STEPS[$k][0])->implode(' → ') }}</div></div>
    <div class="actions"><a href="{{ route('budget-requests.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a></div>
</div>

@if ($activities->isEmpty())
    <div class="card"><div class="empty py-5"><i class="bi bi-kanban"></i>ยังไม่มีกิจกรรมที่ตั้งงบไว้ ให้งานแผนตั้งโครงการ กิจกรรม และงบก่อนจึงขอใช้งบได้</div></div>
@else
<form method="POST" action="{{ route('budget-requests.store') }}" class="card" id="requestForm">
    @csrf
    <div class="card-body row g-3">
        <div class="col-lg-6">
            <label class="form-label" for="activitySelect">กิจกรรมที่ใช้งบ</label>
            <select name="project_activity_id" id="activitySelect" class="form-select @error('project_activity_id') is-invalid @enderror" required>
                @foreach ($activities->groupBy(fn ($a) => $a->project->code.' '.$a->project->name) as $project => $group)
                    <optgroup label="{{ $project }}">
                        @foreach ($group as $a)<option value="{{ $a->id }}" data-available="{{ $available[$a->id] }}" @selected((int) old('project_activity_id', $selected) === $a->id)>{{ $a->name }} — เหลือ {{ baht($available[$a->id]) }}</option>@endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-lg-3">
            <label class="form-label" for="typeSelect">ประเภทคำขอ</label>
            <select name="type" id="typeSelect" class="form-select">@foreach (\App\Models\BudgetRequest::TYPES as $k => $label)<option value="{{ $k }}" @selected(old('type') === $k)>{{ $label }}</option>@endforeach</select>
        </div>
        <div class="col-6 col-lg-3" id="methodBox">
            <label class="form-label">วิธีจัดหา</label>
            <select name="method" class="form-select">@foreach (\App\Models\BudgetRequest::METHODS as $k => $label)<option value="{{ $k }}" @selected(old('method') === $k)>{{ $label }}</option>@endforeach</select>
        </div>
        <div class="col-lg-6"><label class="form-label">เรื่อง</label><input name="title" value="{{ old('title') }}" class="form-control @error('title') is-invalid @enderror" maxlength="255" required placeholder="เช่น ขอซื้อวัสดุฝึกวิทยาศาสตร์ ม.1">@error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-6 col-lg-3"><label class="form-label">ผู้ขาย/ผู้รับจ้าง/ผู้รับเงิน <span class="text-muted small">(ไม่บังคับ)</span></label><input name="vendor" value="{{ old('vendor') }}" class="form-control" maxlength="255"></div>
        <div class="col-md-6 col-lg-3"><label class="form-label">ต้องการใช้ภายใน <span class="text-muted small">(ไม่บังคับ)</span></label><input type="date" name="needed_on" value="{{ old('needed_on') }}" class="form-control"></div>
        <div class="col-12"><label class="form-label">เหตุผลความจำเป็น</label><textarea name="reason" rows="2" class="form-control" maxlength="5000">{{ old('reason') }}</textarea></div>
    </div>

    <div class="card-header border-top"><i class="bi bi-list-ol"></i> รายการ
        <button type="button" class="btn btn-sm btn-light border ms-auto" id="addRow"><i class="bi bi-plus-lg"></i> เพิ่มรายการ</button>
    </div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th style="min-width:240px">รายการ</th><th style="width:130px">ประเภท</th><th style="width:110px" class="text-end">จำนวน</th><th style="width:110px">หน่วย</th><th style="width:140px" class="text-end">ราคา/หน่วย</th><th style="width:130px" class="text-end">รวม</th><th style="width:50px"></th></tr></thead>
        <tbody id="rows"></tbody>
        <tfoot><tr><th colspan="5" class="text-end">รวมทั้งสิ้น</th><th class="text-end" id="grand">0.00</th><th></th></tr></tfoot>
    </table></div>
    <div class="card-footer bg-transparent d-flex flex-wrap align-items-center gap-3">
        <div class="small flex-grow-1" id="budgetNote" role="status" aria-live="polite"></div>
        <button class="btn btn-primary" id="submitBtn"><i class="bi bi-send"></i> ยื่นคำขอ</button>
    </div>
</form>

<template id="rowTemplate">
    <tr>
        <td><input class="form-control" data-f="description" maxlength="255" required aria-label="รายการ"></td>
        <td><select class="form-select" data-f="item_type" aria-label="ประเภทรายการ">@foreach (\App\Models\BudgetRequest::ITEM_TYPES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></td>
        <td><input type="number" class="form-control text-end" data-f="quantity" min="0.01" step="0.01" value="1" required aria-label="จำนวน"></td>
        <td><input class="form-control" data-f="unit" maxlength="30" placeholder="ชิ้น" aria-label="หน่วย"></td>
        <td><input type="number" class="form-control text-end" data-f="unit_price" min="0" step="0.01" required aria-label="ราคาต่อหน่วย"></td>
        <td class="text-end" data-sum>0.00</td>
        <td><button type="button" class="btn btn-sm btn-light border text-danger" data-remove aria-label="ลบรายการ"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const rows = document.getElementById('rows');
    if (!rows) return;
    const tpl = document.getElementById('rowTemplate'), grand = document.getElementById('grand'), note = document.getElementById('budgetNote');
    const activity = document.getElementById('activitySelect'), submit = document.getElementById('submitBtn');
    const type = document.getElementById('typeSelect'), methodBox = document.getElementById('methodBox');
    const money = (n) => Number(n).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const old = @json(array_values(old('items', [])));

    // ชื่อช่องเรียงใหม่ทุกครั้ง ลำดับจึงต่อเนื่องแม้ลบแถวกลาง
    const renumber = () => [...rows.children].forEach((tr, i) => tr.querySelectorAll('[data-f]').forEach((el) => { el.name = `items[${i}][${el.dataset.f}]`; }));
    const recalc = () => {
        let total = 0;
        [...rows.children].forEach((tr) => {
            const sum = (Number(tr.querySelector('[data-f=quantity]').value) || 0) * (Number(tr.querySelector('[data-f=unit_price]').value) || 0);
            tr.querySelector('[data-sum]').textContent = money(sum); total += sum;
        });
        grand.textContent = money(total);
        const available = Number(activity.selectedOptions[0]?.dataset.available || 0), over = total > available + 0.001;
        note.className = 'small flex-grow-1 ' + (over ? 'text-danger' : 'text-muted');
        note.textContent = over ? `เกินงบคงเหลือของกิจกรรม ${money(total - available)} บาท (เหลือ ${money(available)} บาท)` : `งบคงเหลือของกิจกรรม ${money(available)} บาท · หลังขอจะเหลือ ${money(available - total)} บาท`;
        submit.disabled = over || total <= 0;
    };
    const add = (data = {}) => {
        const tr = tpl.content.firstElementChild.cloneNode(true);
        Object.entries(data).forEach(([k, v]) => { const el = tr.querySelector(`[data-f=${k}]`); if (el && v != null) el.value = v; });
        rows.appendChild(tr); renumber(); recalc();
    };
    // วิธีจัดหาใช้กับขอซื้อ/จ้างเท่านั้น
    const syncType = () => { const buy = type.value === 'buy_hire'; methodBox.classList.toggle('d-none', !buy); methodBox.querySelector('select').disabled = !buy; };
    rows.addEventListener('input', recalc);
    rows.addEventListener('click', (e) => { const b = e.target.closest('[data-remove]'); if (b && rows.children.length > 1) { b.closest('tr').remove(); renumber(); recalc(); } });
    document.getElementById('addRow').addEventListener('click', () => { add(); rows.lastElementChild.querySelector('input').focus(); });
    activity.addEventListener('change', recalc); type.addEventListener('change', syncType);
    old.length ? old.forEach(add) : add();
    syncType();
})();
</script>
@endpush
