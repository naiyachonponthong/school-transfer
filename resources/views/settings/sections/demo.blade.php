@php($demoUsers = \App\Models\User::where('is_active', true)->where('role', '!=', 'admin')->orderBy('role')->orderBy('name')->get(['id', 'name', 'username', 'role']))
<form method="POST" action="{{ route('demo.settings') }}">
    @csrf
    <div class="set-head"><h3>โหมดทดลองใช้</h3><p>ให้คนภายนอกกดเข้าดูระบบตามบทบาทโดยไม่ต้องมีรหัสผ่าน</p></div>
    <div class="alert alert-warning d-flex gap-2 small"><i class="bi bi-exclamation-triangle-fill"></i><div>
        ผู้ทดลองเปลี่ยนรหัสผ่าน แก้ตั้งค่า จัดการผู้ใช้ ลบข้อมูล และส่งออกข้อมูลไม่ได้ บทบาทผู้บริหารดูได้อย่างเดียว
        <b>ใช้กับระบบสาธิตเท่านั้น อย่าเปิดบนระบบที่มีข้อมูลจริง</b>
    </div></div>
    <div class="d-flex flex-wrap gap-4 mb-3">
        <label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="demo_mode" value="1" @checked($settings['demo_mode'] === '1')> เปิดโหมดทดลองใช้</label>
        <label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="demo_reset" value="1" @checked($settings['demo_reset'] === '1')> คืนข้อมูลเป็นต้นแบบทุกคืน เวลา 03:30</label>
    </div>
    <div class="row g-3">
        @foreach (\App\Support\Demo::ROLES as $role => [$label, $icon, $hint])
            <div class="col-md-6 col-xl-3">
                <label class="form-label"><i class="bi {{ $icon }}"></i> บัญชีของบทบาท{{ $label }}</label>
                <select name="demo_user_{{ $role }}" class="form-select @error('demo_user_'.$role) is-invalid @enderror" data-search>
                    <option value="">- ไม่เปิดบทบาทนี้ -</option>
                    @foreach ($demoUsers as $u)<option value="{{ $u->id }}" @selected((string) $u->id === (string) $settings['demo_user_'.$role])>{{ $u->name }} ({{ $u->username }} · {{ \App\Models\User::ROLES[$u->role] ?? $u->role }})</option>@endforeach
                </select>
                @error('demo_user_'.$role)<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">{{ $hint }}{{ $role === 'exec' ? ' · เลือกผู้ใช้ที่มีตำแหน่งผู้บริหาร' : '' }}</div>
            </div>
        @endforeach
    </div>
    <div class="small mt-3">
        ต้นแบบข้อมูล: {!! \App\Support\Demo::snapshotAt() ? '<span class="text-success fw-semibold">บันทึกไว้เมื่อ '.e(thai_datetime(\App\Support\Demo::snapshotAt())).'</span>' : '<span class="text-muted">ยังไม่ได้บันทึก</span>' !!}
        · เตรียมข้อมูลตัวอย่างให้เรียบร้อยแล้วกด "บันทึกต้นแบบ" ระบบจะคืนข้อมูลทั้งหมดกลับเป็นแบบนี้ (หลังอัปเดตเวอร์ชันต้องบันทึกต้นแบบใหม่)
    </div>
    <div class="set-actions">
        <button class="btn btn-light border" formaction="{{ route('demo.snapshot') }}" data-confirm="บันทึกข้อมูลปัจจุบันทั้งระบบเป็นต้นแบบ?"><i class="bi bi-camera"></i>บันทึกต้นแบบ</button>
        <button class="btn btn-light border" formaction="{{ route('demo.reset') }}" data-confirm="คืนข้อมูลทั้งระบบกลับเป็นต้นแบบ? ข้อมูลที่เปลี่ยนหลังบันทึกต้นแบบจะหายไป"><i class="bi bi-arrow-counterclockwise"></i>คืนค่าเดี๋ยวนี้</button>
        <button class="btn btn-primary"><i class="bi bi-save"></i>บันทึกโหมดทดลองใช้</button>
    </div>
</form>
