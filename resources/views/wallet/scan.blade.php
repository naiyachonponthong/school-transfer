@extends('layouts.app')
@section('title', 'สแกนจ่าย')

@section('content')
<div class="page-head">
    <div><h1>สแกนจ่าย</h1><div class="sub">ส่องกล้องไปที่ QR บนหน้าจอของร้าน</div></div>
</div>

<div class="card" style="max-width:560px;margin:0 auto"><div class="card-body text-center">
    <div id="reader" class="mx-auto rounded-3 overflow-hidden" style="max-width:420px"></div>
    <div id="scanMsg" class="small text-muted mt-3" role="status" aria-live="polite">กำลังเปิดกล้อง…</div>
    <div class="small text-muted mt-2">ถ้ากล้องไม่ขึ้น ใช้กล้องของมือถือสแกน QR ของร้านได้เช่นกัน ระบบจะพามาหน้ายืนยันการจ่าย</div>
</div></div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    const msg = document.getElementById('scanMsg');
    const prefix = @json(url('/pay')).replace(/\/$/, '') + '/';
    let done = false;
    const cam = new Html5Qrcode('reader');
    cam.start({ facingMode: 'environment' }, { fps: 10, qrbox: 240 }, (text) => {
        if (done) return;
        // รับเฉพาะ QR ของร้านในระบบนี้ (ไม่เปิดลิงก์อื่นที่สแกนเจอ)
        if (text.startsWith(prefix) && /^[A-Za-z0-9]+$/.test(text.slice(prefix.length))) {
            done = true; msg.className = 'small text-success mt-3'; msg.textContent = 'พบ QR ของร้านแล้ว กำลังเปิดรายการ…';
            cam.stop().catch(() => {}).finally(() => { location.href = text; });
        } else {
            msg.className = 'small text-danger mt-3'; msg.textContent = 'QR นี้ไม่ใช่ QR จ่ายเงินของร้านในโรงเรียน';
        }
    }).then(() => { msg.textContent = 'ส่องกล้องไปที่ QR บนหน้าจอของร้าน'; })
      .catch(() => { msg.className = 'small text-danger mt-3'; msg.textContent = 'เปิดกล้องไม่ได้ (ต้องเป็น https และอนุญาตให้ใช้กล้อง)'; });
})();
</script>
@endpush
