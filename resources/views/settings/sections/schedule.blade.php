<form method="POST" action="{{ route('settings.update') }}">
    @csrf
    <input type="hidden" name="section" value="schedule">
    <div class="set-head"><h3>เวลาและตารางเรียน</h3><p>ใช้คำนวณการมาสาย จัดตารางสอน และตัดเกรดทั้งโรงเรียน</p></div>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">นักเรียนมาสายหลัง</label><input type="time" name="late_time" value="{{ old('late_time', $settings['late_time']) }}" class="form-control @error('late_time') is-invalid @enderror" required></div>
        <div class="col-md-4"><label class="form-label">ครูมาสายหลัง</label><input type="time" name="staff_late_time" value="{{ old('staff_late_time', $settings['staff_late_time']) }}" class="form-control @error('staff_late_time') is-invalid @enderror" required></div>
        <div class="col-md-4"><label class="form-label">วันเรียน</label><select name="school_days" class="form-select"><option value="5" @selected($settings['school_days'] != 6)>จันทร์ถึงศุกร์</option><option value="6" @selected($settings['school_days'] == 6)>จันทร์ถึงเสาร์</option></select></div>
        <div class="col-md-4"><label class="form-label">จำนวนคาบต่อวัน</label><input type="number" name="periods_per_day" min="1" max="12" value="{{ old('periods_per_day', $settings['periods_per_day']) }}" class="form-control @error('periods_per_day') is-invalid @enderror" required></div>
        <div class="col-md-8"><label class="form-label">เกณฑ์ตัดเกรด <span class="text-muted fw-normal small">(คะแนนขั้นต่ำของเกรด 4, 3.5, 3, 2.5, 2, 1.5, 1)</span></label><input name="grade_scale" value="{{ old('grade_scale', $settings['grade_scale']) }}" class="form-control font-monospace @error('grade_scale') is-invalid @enderror" placeholder="80,75,70,65,60,55,50">@error('grade_scale')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-12"><label class="form-label">เวลาแต่ละคาบ (บรรทัดละคาบ)</label><textarea name="period_times" rows="7" class="form-control font-monospace small">{{ old('period_times', $settings['period_times']) }}</textarea></div>
    </div>
    <div class="set-actions"><button class="btn btn-primary"><i class="bi bi-save"></i>บันทึกเวลาและตารางเรียน</button></div>
</form>
