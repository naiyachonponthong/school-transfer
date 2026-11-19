<form method="POST" action="{{ route('settings.update') }}" id="gateForm">
    @csrf
    <input type="hidden" name="section" value="gate">
    <div class="set-head"><h3>สแกนหน้าประตูและลงเวลาครูด้วย GPS</h3><p>เวลากลับบ้านของการสแกนบัตร และตำแหน่งโรงเรียนสำหรับลงเวลาผ่านมือถือ</p></div>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">สแกนหลังเวลานี้ = กลับบ้าน</label><input type="time" name="gate_checkout_after" value="{{ old('gate_checkout_after', $settings['gate_checkout_after']) }}" class="form-control"></div>
        <div class="col-md-8 d-flex align-items-end"><label class="form-check form-switch mb-2"><input type="checkbox" class="form-check-input" name="gps_required" value="1" @checked($settings['gps_required'])> ครูต้องอยู่ในรัศมีโรงเรียนจึงลงเวลาได้</label></div>
        <div class="col-md-4"><label class="form-label">ละติจูดโรงเรียน</label><input name="school_lat" value="{{ old('school_lat', $settings['school_lat']) }}" class="form-control @error('school_lat') is-invalid @enderror" placeholder="16.4419"></div>
        <div class="col-md-4"><label class="form-label">ลองจิจูดโรงเรียน</label><input name="school_lng" value="{{ old('school_lng', $settings['school_lng']) }}" class="form-control @error('school_lng') is-invalid @enderror" placeholder="102.8360"></div>
        <div class="col-md-4"><label class="form-label">รัศมี (เมตร)</label><input type="number" name="gps_radius" value="{{ old('gps_radius', $settings['gps_radius']) }}" class="form-control @error('gps_radius') is-invalid @enderror"></div>
        <div class="col-12"><button type="button" class="btn btn-sm btn-soft" id="useLocation"><i class="bi bi-geo-alt"></i>ใช้ตำแหน่งปัจจุบัน (กดขณะอยู่ที่โรงเรียน)</button></div>
    </div>
    <div class="set-block">
        <div class="set-block-title"><i class="bi bi-hdd-network"></i>เครื่องสแกนหน้าประตู</div>
        <a href="{{ route('gate.devices') }}" class="btn btn-light border"><i class="bi bi-gear"></i>จัดการเครื่องสแกนใบหน้า / บัตร</a>
    </div>
    <div class="set-actions"><button class="btn btn-primary"><i class="bi bi-save"></i>บันทึก</button></div>
</form>
@push('scripts')
<script>
document.getElementById('useLocation').addEventListener('click', () => {
  const f = document.getElementById('gateForm');
  navigator.geolocation.getCurrentPosition((p) => { f.school_lat.value = p.coords.latitude.toFixed(6); f.school_lng.value = p.coords.longitude.toFixed(6); }, () => alert('อ่านตำแหน่งไม่ได้ ตรวจว่าอนุญาตให้เว็บใช้ตำแหน่ง และเปิดผ่าน https'));
});
</script>
@endpush
