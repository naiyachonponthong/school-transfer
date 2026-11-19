<form method="POST" action="{{ route('settings.update') }}">
    @csrf
    <input type="hidden" name="section" value="line">
    <div class="set-head"><h3>LINE Official Account</h3><p>ส่งแจ้งเตือนถึงผู้ปกครองและครู ตั้งค่าใน LINE Developers แล้ววางค่าที่นี่</p></div>
    <div class="alert alert-light d-flex gap-2 small mb-3"><i class="bi bi-info-circle"></i><div>
        Webhook URL: <code>{{ route('line.webhook') }}</code> · สถานะ: {!! \App\Services\Line::configured() ? '<span class="text-success fw-semibold">ตั้งค่าแล้ว</span>' : '<span class="text-danger">ยังไม่ตั้งค่า</span>' !!}
        · <a href="{{ route('settings.messages') }}">ประวัติการส่ง / วิธีตั้งค่า</a>
    </div></div>
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Channel access token</label><input type="password" name="line_channel_token" class="form-control" placeholder="{{ $settings['line_channel_token'] ? '•••••• (เว้นว่าง = ใช้ค่าเดิม)' : 'วางค่าจาก LINE Developers' }}" autocomplete="off"></div>
        <div class="col-md-4"><label class="form-label">Channel secret</label><input type="password" name="line_channel_secret" class="form-control" placeholder="{{ $settings['line_channel_secret'] ? '•••••• (เว้นว่าง = ใช้ค่าเดิม)' : '' }}" autocomplete="off"></div>
        <div class="col-md-2"><label class="form-label">LINE ID ของ OA</label><input name="line_oa_id" value="{{ old('line_oa_id', $settings['line_oa_id']) }}" class="form-control" placeholder="@school"></div>
        <div class="col-12 d-flex flex-wrap gap-4">
            <label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="line_notify_gate" value="1" @checked($settings['line_notify_gate'])> แจ้งเมื่อสแกนเข้า/ออกโรงเรียน</label>
            <label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="line_notify_absent" value="1" @checked($settings['line_notify_absent'])> แจ้งเมื่อเช็คชื่อ ขาด/สาย</label>
        </div>
    </div>
    <div class="set-actions">
        <a href="{{ route('settings.messages') }}" class="btn btn-light border"><i class="bi bi-chat-left-text"></i>ประวัติข้อความ</a>
        <button class="btn btn-primary"><i class="bi bi-save"></i>บันทึก LINE OA</button>
    </div>
</form>
