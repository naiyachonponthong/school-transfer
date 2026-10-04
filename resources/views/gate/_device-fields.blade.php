<div class="col-12">
    <label class="form-label">ชื่อเครื่อง</label>
    <input name="name" class="form-control" value="{{ $device?->name }}" placeholder="เช่น ประตูหน้า ช่อง 1" maxlength="255" required>
</div>
<div class="col-12">
    <label class="form-label">จุดติดตั้ง <span class="text-muted small">(ไม่บังคับ)</span></label>
    <input name="location" class="form-control" value="{{ $device?->location }}" placeholder="เช่น ประตู 1 ฝั่งถนนใหญ่" maxlength="255">
</div>
<div class="col-12">
    <label class="form-label">ทิศทาง</label>
    <select name="mode" class="form-select">
        @foreach (\App\Models\GateDevice::MODES as $key => $label)
            <option value="{{ $key }}" @selected(($device?->mode ?? 'auto') === $key)>{{ $label }}</option>
        @endforeach
    </select>
    <div class="form-text">เครื่องเดียวใช้ทั้งเข้าและออก เลือก "ตามเวลา" · ถ้าแยกเครื่องขาเข้ากับขาออก เลือกตามจุดที่ติดตั้ง</div>
</div>
@if ($device)
    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="active{{ $device->id }}" @checked($device->is_active)>
            <label class="form-check-label" for="active{{ $device->id }}">เปิดใช้งาน (ปิดแล้วระบบจะไม่รับข้อมูลจากเครื่องนี้)</label>
        </div>
    </div>
@endif
