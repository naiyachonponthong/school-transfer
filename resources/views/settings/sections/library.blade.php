<form method="POST" action="{{ route('settings.update') }}">
    @csrf
    <input type="hidden" name="section" value="library">
    <div class="set-head"><h3>ห้องสมุดและรับสมัครนักเรียน</h3><p>ระยะเวลายืมหนังสือ และทางลัดไปตั้งค่าฟอร์มรับสมัคร</p></div>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">ระยะยืมหนังสือ (วันทำการ)</label><input type="number" name="library_loan_days" value="{{ old('library_loan_days', $settings['library_loan_days']) }}" class="form-control @error('library_loan_days') is-invalid @enderror"></div>
    </div>
    <div class="set-block">
        <div class="set-block-title"><i class="bi bi-ui-checks"></i>รับสมัครนักเรียน</div>
        <p class="small text-muted">เปิด/ปิดรับสมัคร ชั้นที่รับ และคำถามในฟอร์ม</p>
        <a href="{{ route('admissions.form') }}" class="btn btn-light border"><i class="bi bi-box-arrow-up-right"></i>ตั้งค่าฟอร์มรับสมัคร</a>
    </div>
    <div class="set-actions"><button class="btn btn-primary"><i class="bi bi-save"></i>บันทึก</button></div>
</form>
