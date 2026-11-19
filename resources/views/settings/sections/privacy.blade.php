<form method="POST" action="{{ route('settings.update') }}">
    @csrf
    <input type="hidden" name="section" value="privacy">
    <div class="set-head"><h3>ประกาศความเป็นส่วนตัว (PDPA)</h3><p>ใส่ข้อความแล้วผู้ใช้ทุกคนต้องกดรับทราบก่อนเข้าใช้งานครั้งถัดไป เว้นว่าง = ไม่บังคับ</p></div>
    <textarea name="privacy_notice" rows="10" class="form-control @error('privacy_notice') is-invalid @enderror" placeholder="เช่น โรงเรียนเก็บข้อมูลส่วนบุคคลของนักเรียนและผู้ปกครองเพื่อ...">{{ old('privacy_notice', $settings['privacy_notice']) }}</textarea>
    <div class="d-flex flex-wrap gap-3 mt-2 small">
        <label class="form-check"><input type="checkbox" class="form-check-input" name="privacy_bump" value="1"> แก้ไขสาระสำคัญ ให้ทุกคนรับทราบใหม่ (ตอนนี้ฉบับที่ {{ $settings['privacy_version'] }})</label>
        <span class="text-muted">รับทราบฉบับปัจจุบันแล้ว {{ \Illuminate\Support\Facades\DB::table('privacy_consents')->where('version', (int) $settings['privacy_version'])->count() }} คน</span>
    </div>
    <div class="set-actions"><button class="btn btn-primary"><i class="bi bi-save"></i>บันทึกประกาศ</button></div>
</form>
