@extends('layouts.app')
@section('title', 'ตั้งค่าโรงเรียน')

@section('content')
<div class="page-head"><div><h1>ตั้งค่าโรงเรียน</h1></div></div>
<div class="row g-3">
    <div class="col-lg-7">
        <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="card">
            @csrf
            <div class="card-header"><i class="bi bi-palette"></i> สีธีมของระบบ</div>
            <div class="card-body">
                @php($theme = \App\Support\Theme::color())
                <div class="swatches mb-3">
                    @foreach (\App\Support\Theme::PRESETS as $hex => $name)
                        <button type="button" class="swatch" style="background:{{ $hex }}" data-color="{{ $hex }}" title="{{ $name }}"></button>
                    @endforeach
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <input type="color" value="{{ $theme }}" class="form-control form-control-color" data-theme-input title="เลือกสีเอง">
                    <input name="theme_color" value="{{ $theme }}" class="form-control" style="width:130px" data-theme-text maxlength="7">
                    <div class="d-flex align-items-center gap-2 ms-2" data-theme-preview style="--prev: {{ $theme }}">
                        <span class="btn btn-sm text-white" style="background:var(--prev)">ปุ่มหลัก</span>
                        <span class="badge rounded-pill" style="background:var(--prev)">ใหม่</span>
                        <span class="rounded-3" style="width:34px;height:34px;background:var(--prev);display:inline-block"></span>
                    </div>
                </div>
                <div class="small text-muted mt-2">ใช้กับเมนู ปุ่ม หัวแอปมือถือ และไอคอนทั้งระบบ แนะนำให้ใช้สีประจำโรงเรียน กดบันทึกด้านล่างเพื่อใช้งาน</div>
            </div>
            <div class="card-header border-top"><i class="bi bi-building"></i> ข้อมูลโรงเรียน</div>
            <div class="card-body row g-3">
                <div class="col-md-8"><label class="form-label">ชื่อโรงเรียน</label><input name="school_name" value="{{ $settings['school_name'] }}" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">ชื่อย่อ (แสดงบนเมนู)</label><input name="school_short" value="{{ $settings['school_short'] }}" class="form-control"></div>
                <div class="col-12"><label class="form-label">ที่อยู่</label><input name="school_address" value="{{ $settings['school_address'] }}" class="form-control"></div>
                <div class="col-md-8"><label class="form-label">สังกัด / เขตพื้นที่การศึกษา</label><input name="school_affiliation" value="{{ $settings['school_affiliation'] }}" class="form-control" placeholder="เช่น สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาขอนแก่น"></div>
                <div class="col-md-4"><label class="form-label">จังหวัด</label><input name="school_province" value="{{ $settings['school_province'] }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">เบอร์โทร</label><input name="school_phone" value="{{ $settings['school_phone'] }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">ชื่อผู้อำนวยการ (พิมพ์ในสมุดพก)</label><input name="director_name" value="{{ $settings['director_name'] }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">รองผู้อำนวยการฝ่ายวิชาการ</label><input name="academic_deputy_name" value="{{ $settings['academic_deputy_name'] ?? '' }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">หัวหน้างานวัดผล</label><input name="measurement_head_name" value="{{ $settings['measurement_head_name'] ?? '' }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">นายทะเบียน</label><input name="registrar_name" value="{{ $settings['registrar_name'] ?? '' }}" class="form-control"></div>
                <div class="col-12 small text-muted mt-0">ชื่อผู้ลงนามพิมพ์ลงในเอกสาร ปพ.1 ปพ.5 ปพ.6 ปพ.7 (เว้นว่างได้ จะเป็นเส้นประให้เขียนเอง)</div>
                <div class="col-12">
                    <label class="form-label">โลโก้</label>
                    <div class="d-flex gap-3 align-items-center">
                        @if (! empty($settings['logo']))<img src="{{ asset('storage/'.$settings['logo']) }}" style="height:48px" alt="">@endif
                        <input type="file" name="logo" accept="image/*" class="form-control">
                    </div>
                </div>
            </div>
            <div class="card-header border-top"><i class="bi bi-clock"></i> เวลาและตารางเรียน</div>
            <div class="card-body row g-3">
                <div class="col-md-4"><label class="form-label">นักเรียนมาสายหลัง</label><input type="time" name="late_time" value="{{ $settings['late_time'] }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">ครูมาสายหลัง</label><input type="time" name="staff_late_time" value="{{ $settings['staff_late_time'] }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">จำนวนคาบ/วัน</label><input type="number" name="periods_per_day" min="1" max="12" value="{{ $settings['periods_per_day'] }}" class="form-control"></div>
                <div class="col-md-8"><label class="form-label">เกณฑ์ตัดเกรด <span class="text-muted fw-normal small">(คะแนนขั้นต่ำของเกรด 4, 3.5, 3, 2.5, 2, 1.5, 1)</span></label><input name="grade_scale" value="{{ old('grade_scale', $settings['grade_scale']) }}" class="form-control font-monospace @error('grade_scale') is-invalid @enderror" placeholder="80,75,70,65,60,55,50">@error('grade_scale')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label class="form-label">วันเรียน</label><select name="school_days" class="form-select"><option value="5" @selected($settings['school_days'] != 6)>จันทร์–ศุกร์</option><option value="6" @selected($settings['school_days'] == 6)>จันทร์–เสาร์</option></select></div>
                <div class="col-12"><label class="form-label">เวลาแต่ละคาบ (บรรทัดละคาบ)</label><textarea name="period_times" rows="5" class="form-control font-monospace small">{{ $settings['period_times'] }}</textarea></div>
            </div>
            <div class="card-header border-top" id="privacy"><i class="bi bi-shield-lock"></i> ประกาศความเป็นส่วนตัว (PDPA)</div>
            <div class="card-body row g-3">
                <div class="col-12">
                    <textarea name="privacy_notice" rows="6" class="form-control" placeholder="เว้นว่าง = ไม่บังคับให้รับทราบ · ใส่ข้อความแล้วผู้ใช้ทุกคนต้องกดรับทราบก่อนเข้าใช้งานครั้งถัดไป">{{ old('privacy_notice', $settings['privacy_notice']) }}</textarea>
                    <div class="d-flex flex-wrap gap-3 mt-2 small">
                        <label><input type="checkbox" class="form-check-input" name="privacy_bump" value="1"> แก้ไขสาระสำคัญ ให้ทุกคนรับทราบใหม่ (ตอนนี้ฉบับที่ {{ $settings['privacy_version'] }})</label>
                        <span class="text-muted">รับทราบฉบับปัจจุบันแล้ว {{ \Illuminate\Support\Facades\DB::table('privacy_consents')->where('version', (int) $settings['privacy_version'])->count() }} คน</span>
                    </div>
                </div>
            </div>
            <div class="card-header border-top"><i class="bi bi-bank"></i> ช่องทางรับชำระเงิน (แสดงในใบแจ้งหนี้)</div>
            <div class="card-body row g-3">
                <div class="col-md-5"><label class="form-label">พร้อมเพย์</label><input name="promptpay_id" value="{{ $settings['promptpay_id'] }}" class="form-control" placeholder="เบอร์โทร/เลขผู้เสียภาษี"></div>
                <div class="col-md-7"><label class="form-label">บัญชีธนาคาร</label><textarea name="bank_info" rows="2" class="form-control" placeholder="ธนาคาร... เลขที่บัญชี... ชื่อบัญชี...">{{ $settings['bank_info'] }}</textarea></div>
            </div>
            <div class="card-header border-top" id="line"><i class="bi bi-chat-dots"></i> LINE Official Account (แจ้งเตือนผู้ปกครอง/ครู)</div>
            <div class="card-body row g-3">
                <div class="col-12 small text-muted">
                    Webhook URL: <code>{{ route('line.webhook') }}</code> · สถานะ: {!! \App\Services\Line::configured() ? '<span class="text-success fw-semibold">ตั้งค่าแล้ว</span>' : '<span class="text-danger">ยังไม่ตั้งค่า</span>' !!}
                    · <a href="{{ route('settings.messages') }}">ประวัติการส่ง / วิธีตั้งค่า</a>
                </div>
                <div class="col-md-6"><label class="form-label">Channel access token</label><input type="password" name="line_channel_token" class="form-control" placeholder="{{ $settings['line_channel_token'] ? '•••••• (เว้นว่าง = ใช้ค่าเดิม)' : 'วางค่าจาก LINE Developers' }}" autocomplete="off"></div>
                <div class="col-md-4"><label class="form-label">Channel secret</label><input type="password" name="line_channel_secret" class="form-control" placeholder="{{ $settings['line_channel_secret'] ? '•••••• (เว้นว่าง = ใช้ค่าเดิม)' : '' }}" autocomplete="off"></div>
                <div class="col-md-2"><label class="form-label">LINE ID ของ OA</label><input name="line_oa_id" value="{{ $settings['line_oa_id'] }}" class="form-control" placeholder="@school"></div>
                <div class="col-12 d-flex flex-wrap gap-4">
                    <label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="line_notify_gate" value="1" @checked($settings['line_notify_gate'])> แจ้งเมื่อสแกนเข้า/ออกโรงเรียน</label>
                    <label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="line_notify_absent" value="1" @checked($settings['line_notify_absent'])> แจ้งเมื่อเช็คชื่อ ขาด/สาย</label>
                </div>
            </div>
            <div class="card-header border-top" id="line-login"><i class="bi bi-box-arrow-in-right"></i> เข้าสู่ระบบด้วย LINE (LINE Login)</div>
            <div class="card-body row g-3">
                <div class="col-12 small text-muted">
                    สร้างช่อง LINE Login ใน LINE Developers <b>ใต้ Provider เดียวกับ LINE OA</b> แล้วใส่ Callback URL: <code>{{ route('line.callback') }}</code>
                    · สถานะ: {!! \App\Http\Controllers\Auth\LineLoginController::configured() ? '<span class="text-success fw-semibold">เปิดใช้งาน</span>' : '<span class="text-muted">ยังไม่ได้ตั้งค่า</span>' !!}
                </div>
                <div class="col-md-4"><label class="form-label">Channel ID</label><input name="line_login_channel_id" value="{{ $settings['line_login_channel_id'] }}" class="form-control" inputmode="numeric"></div>
                <div class="col-md-4"><label class="form-label">Channel secret</label><input type="password" name="line_login_channel_secret" class="form-control" placeholder="{{ $settings['line_login_channel_secret'] ? '•••••••• (เว้นว่าง = ใช้ค่าเดิม)' : '' }}" autocomplete="off"></div>
            </div>
            <div class="card-header border-top"><i class="bi bi-qr-code-scan"></i> สแกนหน้าประตู และลงเวลาครูด้วย GPS</div>
            <div class="card-body row g-3">
                <div class="col-md-4"><label class="form-label">สแกนหลังเวลานี้ = กลับบ้าน</label><input type="time" name="gate_checkout_after" value="{{ $settings['gate_checkout_after'] }}" class="form-control"></div>
                <div class="col-md-8 d-flex align-items-end"><label class="form-check form-switch mb-2"><input type="checkbox" class="form-check-input" name="gps_required" value="1" @checked($settings['gps_required'])> ครูต้องอยู่ในรัศมีโรงเรียนจึงลงเวลาได้</label></div>
                <div class="col-md-4"><label class="form-label">ละติจูดโรงเรียน</label><input name="school_lat" value="{{ $settings['school_lat'] }}" class="form-control" placeholder="16.4419"></div>
                <div class="col-md-4"><label class="form-label">ลองจิจูดโรงเรียน</label><input name="school_lng" value="{{ $settings['school_lng'] }}" class="form-control" placeholder="102.8360"></div>
                <div class="col-md-4"><label class="form-label">รัศมี (เมตร)</label><input type="number" name="gps_radius" value="{{ $settings['gps_radius'] }}" class="form-control"></div>
                <div class="col-12"><button type="button" class="btn btn-sm btn-light border" onclick="navigator.geolocation.getCurrentPosition(p=>{this.form.school_lat.value=p.coords.latitude.toFixed(6);this.form.school_lng.value=p.coords.longitude.toFixed(6)},()=>alert('อ่านตำแหน่งไม่ได้'))"><i class="bi bi-geo-alt"></i> ใช้ตำแหน่งปัจจุบัน (กดขณะอยู่ที่โรงเรียน)</button></div>
            </div>
            <div class="card-header border-top" id="admission"><i class="bi bi-person-plus"></i> ห้องสมุด และรับสมัครนักเรียน</div>
            <div class="card-body row g-3">
                <div class="col-md-4"><label class="form-label">ระยะยืมหนังสือ (วันทำการ)</label><input type="number" name="library_loan_days" value="{{ $settings['library_loan_days'] }}" class="form-control"></div>
                <div class="col-md-8 d-flex align-items-end"><a href="{{ route('admissions.form') }}" class="btn btn-light border"><i class="bi bi-ui-checks"></i> เปิด/ปิดรับสมัคร ชั้นที่รับ และคำถามในฟอร์ม → ตั้งค่าฟอร์มรับสมัคร</a></div>
            </div>
            <div class="card-header border-top" id="facility"><i class="bi bi-tools"></i> งานพัสดุ / อาคารสถานที่</div>
            <div class="card-body">
                <label class="form-label">ครูที่จัดการครุภัณฑ์ ตรวจสอบพัสดุ และรับเรื่องแจ้งซ่อมได้ (นอกจากผู้ดูแลระบบ)</label>
                @php($managers = \App\Models\User::facilityManagerIds())
                <select name="facility_manager_ids[]" class="form-select" multiple size="5">
                    @foreach ($staff as $u)<option value="{{ $u->id }}" @selected(in_array($u->id, $managers, true))>{{ $u->name }}{{ $u->position ? ' · '.$u->position : '' }}</option>@endforeach
                </select>
                <div class="form-text">กด Ctrl ค้างเพื่อเลือกหลายคน · คนที่เลือกจะได้รับแจ้งเตือน LINE เมื่อมีการแจ้งซ่อมใหม่</div>
                <a href="{{ route('assets.numbering') }}" class="btn btn-light border mt-3"><i class="bi bi-123"></i> ตั้งรูปแบบเลขครุภัณฑ์อัตโนมัติ</a> <a href="{{ route('supplies.numbering') }}" class="btn btn-light border mt-3"><i class="bi bi-123"></i> ตั้งรูปแบบรหัสวัสดุอัตโนมัติ</a>
            </div>
            <div class="card-footer bg-transparent"><button class="btn btn-primary"><i class="bi bi-save"></i> บันทึก</button></div>
        </form>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="bi bi-award"></i> หัวข้อคะแนนความประพฤติ</div>
            <div class="card-body small text-muted pb-0">ปุ่มลัดที่ครูกดบันทึกได้ในคลิกเดียว</div>
            <div class="card-body">
                @foreach ($rules as $r)
                    <form method="POST" action="{{ route('settings.rules.update', $r) }}" class="d-flex gap-1 mb-2 align-items-center">
                        @csrf @method('PUT')
                        <input type="checkbox" name="is_active" value="1" class="form-check-input m-0" @checked($r->is_active) title="เปิดใช้">
                        <input name="name" value="{{ $r->name }}" class="form-control form-control-sm">
                        <input name="points" type="number" value="{{ $r->points }}" class="form-control form-control-sm {{ $r->points > 0 ? 'text-success' : 'text-danger' }}" style="width:70px">
                        <button class="btn btn-sm btn-light border"><i class="bi bi-check-lg"></i></button>
                        <button form="rdel{{ $r->id }}" class="btn btn-sm btn-light border text-danger"><i class="bi bi-trash"></i></button>
                    </form>
                    <form id="rdel{{ $r->id }}" method="POST" action="{{ route('settings.rules.destroy', $r) }}" data-confirm="ลบหัวข้อนี้?">@csrf @method('DELETE')</form>
                @endforeach
                <form method="POST" action="{{ route('settings.rules.store') }}" class="d-flex gap-1 mt-3 pt-3 border-top">
                    @csrf
                    <input name="name" class="form-control form-control-sm" placeholder="หัวข้อใหม่" required>
                    <input name="points" type="number" class="form-control form-control-sm" style="width:70px" placeholder="+/-" required>
                    <button class="btn btn-sm btn-primary"><i class="bi bi-plus"></i></button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
