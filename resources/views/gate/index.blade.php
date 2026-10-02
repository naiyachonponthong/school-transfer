<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>จุดสแกนหน้าประตู · {{ school('school_short') }}</title>
    @include('partials.assets')
    <style>
        body { background: #111827; color: #fff; min-height: 100vh; }
        .gate { display: grid; grid-template-columns: 1.1fr 1fr; min-height: 100vh; }
        .gate-left { padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
        .gate-right { background: #fff; color: var(--sb-text); padding: 1.5rem; display: flex; flex-direction: column; }
        #reader { width: 100%; max-width: 520px; border-radius: 20px; overflow: hidden; background: #000; aspect-ratio: 4/3; margin: 0 auto; }
        #reader video { object-fit: cover; }
        .clock { font-size: 3.2rem; font-weight: 700; line-height: 1; }
        .result { border-radius: 24px; padding: 1.5rem; text-align: center; transition: background .2s; min-height: 330px; display: flex; flex-direction: column; justify-content: center; align-items: center; }
        .result.idle { background: #f4f5f7; color: var(--sb-muted); }
        .result.present { background: #ecfdf5; color: #065f46; }
        .result.late { background: #fffbeb; color: #92400e; }
        .result.out { background: #eff6ff; color: #1e40af; }
        .result.repeat { background: #f5f3ff; color: #5b21b6; }
        .result.error { background: #fef2f2; color: #991b1b; }
        .result .photo { width: 130px; height: 130px; border-radius: 50%; object-fit: cover; margin-bottom: .75rem; border: 5px solid #fff; box-shadow: var(--sb-shadow-lg); background: var(--sb-primary-100); display: grid; place-items: center; font-size: 3rem; font-weight: 700; color: var(--sb-primary-700); }
        .result .big { font-size: 2rem; font-weight: 700; }
        .mode-switch .btn { min-width: 110px; }
        .recent-row { display: flex; align-items: center; gap: .6rem; padding: .45rem 0; border-bottom: 1px solid #f0f1f4; font-size: .9rem; }
        @media (max-width: 900px) { .gate { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="gate">
    <div class="gate-left">
        <div class="d-flex align-items-center gap-2">
            <span class="sb-logo"><i class="bi bi-qr-code-scan"></i></span>
            <div>
                <div class="fw-bold">จุดสแกนหน้าประตู</div>
                <div class="small opacity-75">{{ school('school_name') }} · {{ \App\Support\Thai::fullDate(today()) }}</div>
            </div>
            <a href="{{ route('home') }}" class="btn btn-sm btn-outline-light ms-auto"><i class="bi bi-x-lg"></i> ออก</a>
        </div>
        <div class="text-center">
            <div class="clock" data-clock>--:--</div>
            <div class="small opacity-75 mt-1">มาสายหลัง {{ $lateTime }} น. · สแกนกลับบ้านได้ตั้งแต่ {{ $checkoutAfter }} น.</div>
        </div>
        <div class="text-center mode-switch">
            <div class="btn-group">
                <input type="radio" class="btn-check" name="mode" id="mAuto" value="" checked>
                <label class="btn btn-outline-light" for="mAuto">อัตโนมัติ</label>
                <input type="radio" class="btn-check" name="mode" id="mIn" value="in">
                <label class="btn btn-outline-light" for="mIn">ขาเข้า</label>
                <input type="radio" class="btn-check" name="mode" id="mOut" value="out">
                <label class="btn btn-outline-light" for="mOut">ขาออก</label>
            </div>
        </div>
        <div id="reader"></div>
        <form id="manual" class="d-flex gap-2 mx-auto" style="max-width:520px;width:100%" autocomplete="off">
            <input id="code" class="form-control form-control-lg" placeholder="สแกนด้วยเครื่องอ่าน หรือพิมพ์รหัสนักเรียน" autofocus>
            <button class="btn btn-primary btn-lg">ตกลง</button>
        </form>
        <div class="text-center small opacity-75" id="camHint">กำลังเปิดกล้อง... (ถ้าใช้เครื่องอ่านบาร์โค้ด USB สแกนได้เลย)</div>
    </div>

    <div class="gate-right">
        <div id="result" class="result idle">
            <i class="bi bi-person-badge" style="font-size:4rem;opacity:.3"></i>
            <div class="fs-5 mt-2">ยื่นบัตรนักเรียนให้กล้องสแกน</div>
        </div>
        <div class="d-flex gap-3 my-3 text-center">
            <div class="flex-fill card"><div class="card-body py-2"><div class="fs-3 fw-bold text-success" id="inCount">{{ $inCount }}</div><div class="small text-muted">สแกนเข้า / {{ $total }}</div></div></div>
            <div class="flex-fill card"><div class="card-body py-2"><div class="fs-3 fw-bold text-primary" id="outCount">{{ $outCount }}</div><div class="small text-muted">สแกนออก</div></div></div>
        </div>
        <div class="fw-semibold mb-1">ล่าสุด</div>
        <div id="recent" style="overflow:auto;flex:1">
            @foreach ($recent as $r)
                <div class="recent-row">
                    <span class="badge bg-{{ \App\Models\Attendance::color($r->status) }}">{{ $r->checkout_at ? 'ออก' : \App\Models\Attendance::label($r->status) }}</span>
                    <span class="flex-grow-1">{{ $r->student->fullName() }} <span class="text-muted">{{ $r->student->classroom?->name() }}</span></span>
                    <span class="text-muted">{{ substr($r->checkout_at ?? $r->checked_at, 0, 5) }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    const url = @json(route('gate.scan'));
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const box = document.getElementById('result');
    const recent = document.getElementById('recent');
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    let last = '', lastAt = 0, busy = false, resetTimer;

    const clock = document.querySelector('[data-clock]');
    setInterval(() => { clock.textContent = new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit' }); }, 1000);

    // เสียงสั้น ๆ ให้รู้ว่าสแกนติด (สูง = สำเร็จ, ต่ำ = ผิดพลาด)
    const beep = (ok) => {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const o = ctx.createOscillator(); const g = ctx.createGain();
            o.frequency.value = ok ? 1046 : 220; o.connect(g); g.connect(ctx.destination);
            g.gain.setValueAtTime(.2, ctx.currentTime); o.start(); o.stop(ctx.currentTime + (ok ? .12 : .35));
        } catch (e) {}
    };

    async function submit(code) {
        code = code.trim();
        if (!code || busy) return;
        if (code === last && Date.now() - lastAt < 4000) return; // กันสแกนซ้ำทันที
        last = code; lastAt = Date.now(); busy = true;
        const mode = document.querySelector('input[name=mode]:checked').value;
        try {
            const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ code, mode }) });
            const d = await res.json();
            if (!d.ok) { box.className = 'result error'; box.innerHTML = `<i class="bi bi-x-octagon" style="font-size:4rem"></i><div class="big mt-2">${esc(d.message)}</div><div class="text-muted">${esc(code)}</div>`; beep(false); return; }
            const s = d.student;
            const icon = { present: 'bi-check-circle-fill', late: 'bi-alarm-fill', out: 'bi-house-heart-fill', repeat: 'bi-arrow-repeat' }[d.kind];
            box.className = 'result ' + d.kind;
            box.innerHTML = (s.photo ? `<img class="photo" src="${esc(s.photo)}">` : `<div class="photo">${esc(s.initials)}</div>`)
                + `<div class="big">${esc(s.nickname || s.name)}</div><div>${esc(s.name)} · ห้อง ${esc(s.classroom)} เลขที่ ${esc(s.number)}</div>`
                + `<div class="fs-4 fw-bold mt-2"><i class="bi ${icon}"></i> ${esc(d.message)} · ${esc(d.time)} น.</div>`;
            beep(true);
            if (d.kind !== 'repeat') {
                const counter = document.getElementById(d.kind === 'out' ? 'outCount' : 'inCount');
                counter.textContent = Number(counter.textContent) + 1;
                const label = { present: ['มา', 'success'], late: ['สาย', 'warning'], out: ['ออก', 'primary'] }[d.kind];
                recent.insertAdjacentHTML('afterbegin', `<div class="recent-row"><span class="badge bg-${label[1]}">${label[0]}</span><span class="flex-grow-1">${esc(s.name)} <span class="text-muted">${esc(s.classroom)}</span></span><span class="text-muted">${esc(d.time)}</span></div>`);
            }
        } catch (e) {
            box.className = 'result error'; box.innerHTML = '<div class="big">เชื่อมต่อไม่ได้</div><div>ตรวจสอบอินเทอร์เน็ตแล้วลองใหม่</div>'; beep(false);
        } finally {
            busy = false;
            clearTimeout(resetTimer);
            resetTimer = setTimeout(() => { box.className = 'result idle'; box.innerHTML = '<i class="bi bi-person-badge" style="font-size:4rem;opacity:.3"></i><div class="fs-5 mt-2">ยื่นบัตรนักเรียนให้กล้องสแกน</div>'; }, 6000);
        }
    }

    // เครื่องอ่านบาร์โค้ด USB ทำงานเหมือนคีย์บอร์ด: พิมพ์แล้วกด Enter
    const input = document.getElementById('code');
    document.getElementById('manual').addEventListener('submit', (e) => { e.preventDefault(); submit(input.value); input.value = ''; input.focus(); });
    document.addEventListener('click', (e) => { if (!e.target.closest('input,button,label,a')) input.focus(); });

    if (window.Html5Qrcode) {
        const cam = new Html5Qrcode('reader');
        cam.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 240, height: 240 } }, (text) => submit(text), () => {})
            .then(() => { document.getElementById('camHint').textContent = 'หันบัตรให้ QR อยู่ในกรอบ · หรือใช้เครื่องอ่าน USB ได้พร้อมกัน'; })
            .catch(() => { document.getElementById('reader').style.display = 'none'; document.getElementById('camHint').textContent = 'เปิดกล้องไม่ได้ ใช้เครื่องอ่านบาร์โค้ด USB หรือพิมพ์รหัสนักเรียนแทน'; });
    }
})();
</script>
</body>
</html>
