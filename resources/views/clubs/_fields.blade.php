{{-- ช่องกรอกของฟอร์มเพิ่ม/แก้ไขชุมนุม (อยู่ในหน้าต่างป๊อปอัป) --}}
<div class="col-12"><label class="form-label">ชื่อชุมนุม</label><input name="name" value="{{ $club?->name }}" class="form-control" required maxlength="255" placeholder="เช่น ชุมนุมหุ่นยนต์"></div>
<div class="col-12"><label class="form-label">รายละเอียด <span class="text-muted fw-normal small">(นักเรียนเห็นตอนเลือก)</span></label><textarea name="description" rows="2" class="form-control" maxlength="2000">{{ $club?->description }}</textarea></div>
<div class="col-md-7"><label class="form-label">ครูที่ปรึกษา</label>
    <select name="teacher_id" class="form-select" data-search>
        <option value="">- ยังไม่กำหนด -</option>
        @foreach ($teachers as $t)<option value="{{ $t->id }}" @selected($club?->teacher_id === $t->id)>{{ $t->name }}</option>@endforeach
    </select>
</div>
<div class="col-md-5"><label class="form-label">จำนวนรับ</label><input type="number" name="capacity" value="{{ $club?->capacity }}" min="1" max="999" class="form-control" placeholder="ไม่จำกัด"></div>
<div class="col-12"><label class="form-label">สถานที่</label><input name="location" value="{{ $club?->location }}" class="form-control" maxlength="255" placeholder="เช่น ห้องคอมพิวเตอร์ 1"></div>
<div class="col-12">
    <label class="form-label">ระดับชั้นที่รับ <span class="text-muted fw-normal small">(ไม่ติ๊ก = รับทุกระดับ)</span></label>
    <div class="d-flex flex-wrap gap-3">
        @foreach ($levels as $l)
            <label class="small"><input type="checkbox" class="form-check-input" name="levels[]" value="{{ $l }}" @checked(in_array($l, $club?->levels ?? [], true))> {{ $l }}</label>
        @endforeach
    </div>
</div>
