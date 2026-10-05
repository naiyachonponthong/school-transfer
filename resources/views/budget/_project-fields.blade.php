<div class="col-md-8"><label class="form-label">ชื่อโครงการ</label><input name="name" value="{{ old('name', $p?->name) }}" class="form-control" maxlength="255" required></div>
<div class="col-md-4"><label class="form-label">ปีงบประมาณ</label><input type="number" name="fiscal_year" value="{{ old('fiscal_year', $p?->fiscal_year ?? $year) }}" class="form-control" min="2500" max="2700" required></div>
<div class="col-md-6">
    <label class="form-label">ผู้รับผิดชอบโครงการ</label>
    <select name="owner_id" class="form-select" required>
        <option value="">— เลือก —</option>
        @foreach ($staff as $u)<option value="{{ $u->id }}" @selected((int) old('owner_id', $p?->owner_id) === $u->id)>{{ $u->name }}{{ $u->position ? ' · '.$u->position : '' }}</option>@endforeach
    </select>
</div>
<div class="col-md-6">
    <label class="form-label">หน่วยงาน <span class="text-muted small">(ไม่บังคับ)</span></label>
    <select name="department_id" class="form-select">
        <option value="">— ไม่ระบุ —</option>
        @foreach ($departments as $d)<option value="{{ $d->id }}" @selected((int) old('department_id', $p?->department_id) === $d->id)>{{ $d->name }}</option>@endforeach
    </select>
</div>
<div class="col-md-6"><label class="form-label">เริ่ม</label><input type="date" name="starts_on" value="{{ old('starts_on', $p?->starts_on?->toDateString()) }}" class="form-control"></div>
<div class="col-md-6"><label class="form-label">สิ้นสุด</label><input type="date" name="ends_on" value="{{ old('ends_on', $p?->ends_on?->toDateString()) }}" class="form-control"></div>
<div class="col-12"><label class="form-label">วัตถุประสงค์/เป้าหมาย</label><textarea name="objective" rows="3" class="form-control" maxlength="5000">{{ old('objective', $p?->objective) }}</textarea></div>
