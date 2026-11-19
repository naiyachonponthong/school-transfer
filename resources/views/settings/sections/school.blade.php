<form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="section" value="school">
    <div class="set-head"><h3>ข้อมูลโรงเรียน</h3><p>ชื่อและที่อยู่ใช้ในเมนู ใบแจ้งหนี้ และเอกสารราชการทุกฉบับ</p></div>
    <div class="row g-3">
        <div class="col-md-8"><label class="form-label">ชื่อโรงเรียน</label><input name="school_name" value="{{ old('school_name', $settings['school_name']) }}" class="form-control @error('school_name') is-invalid @enderror" required>@error('school_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-md-4"><label class="form-label">ชื่อย่อ (แสดงบนเมนู)</label><input name="school_short" value="{{ old('school_short', $settings['school_short']) }}" class="form-control"></div>
        <div class="col-12"><label class="form-label">ที่อยู่</label><input name="school_address" value="{{ old('school_address', $settings['school_address']) }}" class="form-control"></div>
        <div class="col-md-8"><label class="form-label">สังกัด / เขตพื้นที่การศึกษา</label><input name="school_affiliation" value="{{ old('school_affiliation', $settings['school_affiliation']) }}" class="form-control" placeholder="เช่น สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาขอนแก่น"></div>
        <div class="col-md-4"><label class="form-label">จังหวัด</label><input name="school_province" value="{{ old('school_province', $settings['school_province']) }}" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">เบอร์โทร</label><input name="school_phone" value="{{ old('school_phone', $settings['school_phone']) }}" class="form-control"></div>
        <div class="col-md-6">
            <label class="form-label">โลโก้</label>
            <div class="d-flex gap-3 align-items-center">
                @if (! empty($settings['logo']))<img src="{{ asset('storage/'.$settings['logo']) }}" style="height:44px" alt="โลโก้ปัจจุบัน">@endif
                <input type="file" name="logo" accept="image/*" class="form-control @error('logo') is-invalid @enderror">
            </div>
            @error('logo')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="set-block">
        <div class="set-block-title"><i class="bi bi-pen"></i>ผู้ลงนามในเอกสาร</div>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">ผู้อำนวยการ (พิมพ์ในสมุดพก)</label><input name="director_name" value="{{ old('director_name', $settings['director_name']) }}" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">รองผู้อำนวยการฝ่ายวิชาการ</label><input name="academic_deputy_name" value="{{ old('academic_deputy_name', $settings['academic_deputy_name'] ?? '') }}" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">หัวหน้างานวัดผล</label><input name="measurement_head_name" value="{{ old('measurement_head_name', $settings['measurement_head_name'] ?? '') }}" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">นายทะเบียน</label><input name="registrar_name" value="{{ old('registrar_name', $settings['registrar_name'] ?? '') }}" class="form-control"></div>
            <div class="col-12 small text-muted">พิมพ์ลงในเอกสาร ปพ.1 ปพ.5 ปพ.6 ปพ.7 เว้นว่างได้ จะเป็นเส้นประให้เขียนเอง</div>
        </div>
    </div>
    <div class="set-block">
        <div class="set-block-title"><i class="bi bi-arrows-move"></i>ตำแหน่งพิมพ์ ปพ.2 ลงแบบพิมพ์จริง</div>
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label">เลื่อนซ้าย (-) / ขวา (+) มม.</label><input type="number" step="0.5" name="pp2_offset_x" value="{{ old('pp2_offset_x', $settings['pp2_offset_x']) }}" class="form-control @error('pp2_offset_x') is-invalid @enderror"></div>
            <div class="col-md-3"><label class="form-label">เลื่อนขึ้น (-) / ลง (+) มม.</label><input type="number" step="0.5" name="pp2_offset_y" value="{{ old('pp2_offset_y', $settings['pp2_offset_y']) }}" class="form-control @error('pp2_offset_y') is-invalid @enderror"></div>
            <div class="col-md-6"><label class="form-label">ตราครุฑ (พิมพ์ลงกระดาษเปล่า)</label>
                <div class="d-flex gap-2 align-items-center">@if ($settings['pp2_emblem'])<img src="{{ asset('storage/'.$settings['pp2_emblem']) }}" alt="" style="height:44px">@endif
                <input type="file" name="pp2_emblem" accept="image/png,image/jpeg,image/webp" class="form-control @error('pp2_emblem') is-invalid @enderror"></div>
                @if ($settings['pp2_emblem'])<label class="form-check small mt-1"><input type="checkbox" class="form-check-input" name="pp2_emblem_remove" value="1"> ลบตราที่อัปโหลด</label>@endif
                <div class="form-text">ใช้ไฟล์ PNG พื้นหลังโปร่งใส แสดงเฉพาะ "ฉบับตรวจสอบ" โหมดพิมพ์ลงแบบฟอร์มจริงไม่พิมพ์ตรา เพราะมีบนแบบแล้ว</div></div>
            <div class="col-md-6 small text-muted d-flex align-items-end">พิมพ์โหมด "พิมพ์ลงแบบฟอร์มจริง" ลงกระดาษเปล่าหนึ่งแผ่น ซ้อนกับแบบพิมพ์ ปพ.2 แล้วปรับจนข้อความตรงช่อง ใช้กับเครื่องพิมพ์เครื่องเดิม</div>
        </div>
    </div>
    <div class="set-actions"><button class="btn btn-primary"><i class="bi bi-save"></i>บันทึกข้อมูลโรงเรียน</button></div>
</form>
