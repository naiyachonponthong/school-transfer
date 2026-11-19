<form method="POST" action="{{ route('settings.update') }}">
    @csrf
    <input type="hidden" name="section" value="line-login">
    <div class="set-head"><h3>เข้าสู่ระบบด้วย LINE</h3><p>ให้ครูและผู้ปกครองกดเข้าระบบด้วย LINE ได้โดยไม่ต้องจำรหัสผ่าน</p></div>
    <div class="alert alert-light d-flex gap-2 small mb-3"><i class="bi bi-info-circle"></i><div>
        สร้างช่อง LINE Login ใน LINE Developers <b>ใต้ Provider เดียวกับ LINE OA</b> แล้วใส่ Callback URL: <code>{{ route('line.callback') }}</code>
        · สถานะ: {!! \App\Http\Controllers\Auth\LineLoginController::configured() ? '<span class="text-success fw-semibold">เปิดใช้งาน</span>' : '<span class="text-muted">ยังไม่ได้ตั้งค่า</span>' !!}
    </div></div>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Channel ID</label><input name="line_login_channel_id" value="{{ old('line_login_channel_id', $settings['line_login_channel_id']) }}" class="form-control" inputmode="numeric"></div>
        <div class="col-md-6"><label class="form-label">Channel secret</label><input type="password" name="line_login_channel_secret" class="form-control" placeholder="{{ $settings['line_login_channel_secret'] ? '•••••••• (เว้นว่าง = ใช้ค่าเดิม)' : '' }}" autocomplete="off"></div>
    </div>
    <div class="set-actions"><button class="btn btn-primary"><i class="bi bi-save"></i>บันทึก LINE Login</button></div>
</form>
