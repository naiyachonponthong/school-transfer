@extends('layouts.app')
@section('title', 'สแกนตรวจสอบพัสดุ '.$year)

@section('content')
<div class="page-head">
    <div><h1>สแกนตรวจสอบพัสดุ {{ $year }}</h1><div class="sub">เลือกผลก่อน แล้วสแกน QR บนครุภัณฑ์ทีละชิ้น (หรือพิมพ์เลขครุภัณฑ์)</div></div>
    <div class="actions"><a href="{{ route('asset-checks.index', ['year' => $year]) }}" class="btn btn-light border"><i class="bi bi-list-check"></i> ดูรายงาน</a></div>
</div>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card"><div class="card-body">
            <div class="btn-group w-100 mb-3" role="group">
                @foreach (\App\Models\AssetCheck::RESULTS as $k => [$label, $color])
                    <input type="radio" class="btn-check" name="result" id="r-{{ $k }}" value="{{ $k }}" @checked($k === 'found')>
                    <label class="btn btn-outline-{{ $color }}" for="r-{{ $k }}">{{ $label }}</label>
                @endforeach
            </div>
            <div id="reader" class="mb-2"></div>
            <form id="manual" class="d-flex gap-2" autocomplete="off">
                <input id="code" class="form-control" placeholder="เลขครุภัณฑ์ (หรือใช้เครื่องอ่าน QR)">
                <button class="btn btn-primary">บันทึก</button>
            </form>
            <div class="small text-muted mt-2" id="camHint">กำลังเปิดกล้อง...</div>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-header">สแกนแล้วรอบนี้ <span id="count" class="badge text-bg-primary">0</span></div>
            <div class="list-group list-group-flush" id="log"><div class="list-group-item small text-muted" id="empty">ยังไม่ได้สแกน</div></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(() => {
    const url = @json(route('asset-checks.record'));
    const year = @json($year);
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const log = document.getElementById('log');
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let last = '', lastAt = 0, count = 0;
    const beep = (ok) => { try { const c = new (window.AudioContext || window.webkitAudioContext)(); const o = c.createOscillator(); o.frequency.value = ok ? 1046 : 220; o.connect(c.destination); o.start(); o.stop(c.currentTime + (ok ? .1 : .3)); } catch (e) {} };

    async function submit(code) {
        code = code.trim();
        if (!code || (code === last && Date.now() - lastAt < 4000)) return; // กันสแกนซ้ำทันที
        last = code; lastAt = Date.now();
        const result = document.querySelector('input[name=result]:checked').value;
        try {
            const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ code, result, year }) });
            const d = await res.json();
            document.getElementById('empty')?.remove();
            if (!d.ok) { beep(false); log.insertAdjacentHTML('afterbegin', `<div class="list-group-item small text-danger"><i class="bi bi-x-circle"></i> ${esc(d.message || 'บันทึกไม่สำเร็จ')}</div>`); return; }
            beep(true);
            document.getElementById('count').textContent = ++count;
            log.insertAdjacentHTML('afterbegin', `<div class="list-group-item small d-flex gap-2"><span class="badge text-bg-${esc(d.color)} align-self-start">${esc(d.result)}</span><span><b>${esc(d.asset.code)}</b> ${esc(d.asset.name)}<div class="text-muted">${esc(d.asset.location || '')}</div></span></div>`);
        } catch (e) { beep(false); }
    }

    const input = document.getElementById('code');
    document.getElementById('manual').addEventListener('submit', (e) => { e.preventDefault(); submit(input.value); input.value = ''; input.focus(); });
    if (window.Html5Qrcode) {
        const cam = new Html5Qrcode('reader');
        cam.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 220, height: 220 } }, (text) => submit(text), () => {})
            .then(() => { document.getElementById('camHint').textContent = 'หันกล้องไปที่สติกเกอร์ QR · เปลี่ยนผลด้านบนก่อนสแกนชิ้นที่ชำรุด'; })
            .catch(() => { document.getElementById('reader').style.display = 'none'; document.getElementById('camHint').textContent = 'เปิดกล้องไม่ได้ (ต้องใช้ https) — พิมพ์เลขครุภัณฑ์หรือใช้เครื่องอ่าน QR แทน'; });
    }
})();
</script>
@endpush
