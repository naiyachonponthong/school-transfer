{{-- ช่องของฟอร์มตั้ง/แก้ทุน ($s = ทุนเดิม หรือว่างเมื่อตั้งใหม่) --}}
@php($uid = $s?->id ?? 'new')
<div class="col-md-8"><label class="form-label">ชื่อทุน</label><input name="name" value="{{ old('name', $s?->name) }}" class="form-control" maxlength="255" required placeholder="เช่น ทุนเรียนดีมูลนิธิศิษย์เก่า"></div>
<div class="col-md-4"><label class="form-label">ปีการศึกษา</label><input type="number" name="year" value="{{ old('year', $s?->year ?? $year) }}" class="form-control" min="2500" max="2700" required></div>
<div class="col-md-4">
    <label class="form-label">ประเภท</label>
    <select name="category" class="form-select">
        @foreach (\App\Models\Scholarship::CATEGORIES as $k => $label)<option value="{{ $k }}" @selected(old('category', $s?->category) === $k)>{{ $label }}</option>@endforeach
    </select>
</div>
<div class="col-md-8"><label class="form-label">ผู้ให้ทุน <span class="text-muted small">(ไม่บังคับ)</span></label><input name="donor" value="{{ old('donor', $s?->donor) }}" class="form-control" maxlength="255"></div>
<div class="col-md-4">
    <label class="form-label">วิธีมอบ</label>
    <select name="mode" class="form-select" data-scholarship-mode="{{ $uid }}">
        @foreach (\App\Models\Scholarship::MODES as $k => $label)<option value="{{ $k }}" @selected(old('mode', $s?->mode ?? 'cash') === $k)>{{ $label }}</option>@endforeach
    </select>
</div>
<div class="col-md-4">
    <label class="form-label">มูลค่าต่อทุน</label>
    <div class="input-group">
        <input type="number" name="value" value="{{ old('value', $s ? (float) $s->value : '') }}" class="form-control" min="0.01" step="0.01" inputmode="decimal" required>
        <select name="value_type" class="form-select" style="max-width:110px" data-scholarship-type="{{ $uid }}" aria-label="หน่วยของมูลค่า">
            <option value="amount" @selected(old('value_type', $s?->value_type ?? 'amount') === 'amount')>บาท</option>
            <option value="percent" @selected(old('value_type', $s?->value_type) === 'percent')>%</option>
        </select>
    </div>
</div>
<div class="col-md-4" data-scholarship-fee="{{ $uid }}">
    <label class="form-label">ลดจากรายการ</label>
    <select name="fee_item_id" class="form-select">
        <option value="">ยอดรวมของใบแจ้งหนี้</option>
        @foreach ($feeItems as $item)<option value="{{ $item->id }}" @selected((int) old('fee_item_id', $s?->fee_item_id) === $item->id)>{{ $item->name }}</option>@endforeach
    </select>
</div>
<div class="col-md-3"><label class="form-label">จำนวนทุน</label><input type="number" name="slots" value="{{ old('slots', $s?->slots) }}" class="form-control" min="1" placeholder="ไม่จำกัด"></div>
<div class="col-md-3"><label class="form-label">งบรวม (บาท)</label><input type="number" name="budget" value="{{ old('budget', $s?->budget !== null ? (float) $s->budget : '') }}" class="form-control" min="0" step="0.01" placeholder="ไม่จำกัด"></div>
<div class="col-md-3"><label class="form-label">เปิดรับเสนอชื่อ</label><input type="date" name="opens_on" value="{{ old('opens_on', $s?->opens_on?->toDateString()) }}" class="form-control"></div>
<div class="col-md-3"><label class="form-label">ปิดรับ</label><input type="date" name="closes_on" value="{{ old('closes_on', $s?->closes_on?->toDateString()) }}" class="form-control"></div>
<div class="col-12"><label class="form-label">เงื่อนไข/คุณสมบัติ <span class="text-muted small">(ครูเห็นตอนเสนอชื่อ)</span></label><textarea name="conditions" rows="3" class="form-control" maxlength="5000">{{ old('conditions', $s?->conditions) }}</textarea></div>
<div class="col-12">
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" name="is_open" value="1" id="open{{ $uid }}" @checked(old('is_open', $s?->is_open ?? true))>
        <label class="form-check-label" for="open{{ $uid }}">เปิดให้ครูประจำชั้นเสนอชื่อ</label>
    </div>
    <div class="form-text">ทุนลดค่าธรรมเนียม: อนุมัติแล้วระบบสร้างส่วนลดประจำตัวให้ ใบแจ้งหนี้ที่ออกจากผังค่าธรรมเนียมหลังจากนั้นจะหักเอง · ทุนจ่ายเป็นเงิน: บันทึกการจ่ายและพิมพ์ใบสำคัญรับเงิน</div>
</div>
