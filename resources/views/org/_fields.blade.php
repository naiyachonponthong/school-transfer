@php
    // หน่วยแม่ที่เลือกได้: ไม่ใช่ตัวเองและไม่ใช่หน่วยย่อยของตัวเอง
    $blocked = $department ? \App\Models\Department::descendantIds($department->id, $departments) : [];
@endphp
<div class="col-md-8">
    <label class="form-label">ชื่อหน่วยงาน</label>
    <input name="name" class="form-control" value="{{ $department?->name }}" maxlength="255" required placeholder="เช่น งานทะเบียนและวัดผล">
</div>
<div class="col-md-4">
    <label class="form-label">รหัสย่อ <span class="text-muted small">(ไม่บังคับ)</span></label>
    <input name="code" class="form-control" value="{{ $department?->code }}" maxlength="20">
</div>
<div class="col-md-4">
    <label class="form-label">ประเภท</label>
    <select name="kind" class="form-select">
        @foreach (\App\Models\Department::KINDS as $key => $label)
            <option value="{{ $key }}" @selected(($department?->kind ?? 'unit') === $key)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-8">
    <label class="form-label">อยู่ภายใต้</label>
    <select name="parent_id" class="form-select">
        <option value="">— ระดับบนสุด —</option>
        @foreach ($flat as $row)
            @continue(in_array($row['dept']->id, $blocked, true))
            <option value="{{ $row['dept']->id }}" @selected($department?->parent_id === $row['dept']->id)>{{ str_repeat('— ', $row['depth']) }}{{ $row['dept']->name }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-8">
    <label class="form-label">หัวหน้า</label>
    <select name="head_id" class="form-select">
        <option value="">— ยังไม่ระบุ —</option>
        @foreach ($staff as $u)
            <option value="{{ $u->id }}" @selected($department?->head_id === $u->id)>{{ $u->name }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-4">
    <label class="form-label">ลำดับ</label>
    <input type="number" name="sort" class="form-control" value="{{ $department?->sort ?? 0 }}" min="0" max="9999">
</div>
@if ($department)
    <div class="col-12">
        <label class="form-label">บุคลากรในหน่วยนี้</label>
        <input type="hidden" name="members_sent" value="1">
        <div class="border rounded-3 p-2" style="max-height:220px;overflow:auto">
            <div class="row g-1">
                @foreach ($staff as $u)
                    <div class="col-sm-6"><div class="form-check small">
                        <input class="form-check-input" type="checkbox" name="members[]" value="{{ $u->id }}" id="m{{ $department->id }}_{{ $u->id }}" @checked($department->members->contains('id', $u->id))>
                        <label class="form-check-label" for="m{{ $department->id }}_{{ $u->id }}">{{ $u->name }}</label>
                    </div></div>
                @endforeach
            </div>
        </div>
        <div class="form-text">คนหนึ่งอยู่ได้หลายหน่วย · หน่วยหลักของแต่ละคนเปลี่ยนได้ที่หน้าประวัติของคนนั้น</div>
    </div>
@endif
