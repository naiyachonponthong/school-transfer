@php
    $license = \App\Support\License\Guard::status();
    $integrity = \App\Support\License\Integrity::status();
    $isAdmin = auth()->user()?->isAdmin();
    [$cls, $icon, $label] = match ($license['state']) {
        'ok' => ['ok', 'patch-check-fill', 'ใช้งานได้'],
        'trial' => ['warn', 'hourglass-split', 'ทดลองใช้งาน'],
        'grace' => ['warn', $license['grace_kind'] === 'upgrade' ? 'hourglass-split' : 'wifi-off', $license['grace_kind'] === 'upgrade'
            ? 'ยังไม่ได้เปิดใช้งาน (ผ่อนผันอีก '.$license['grace_days_left'].' วัน)'
            : 'ติดต่อศูนย์ License ไม่ได้ (ผ่อนผันอีก '.$license['grace_days_left'].' วัน)'],
        default => ['bad', 'lock-fill', 'ถูกล็อก'],
    };
@endphp
<div class="set-head"><h3>License</h3><p>สิทธิ์การใช้งานระบบของโรงเรียนนี้ ผูกกับโดเมนเดียว ตรวจกับศูนย์ License วันละครั้ง</p></div>

@if (session('license_locked'))
    <div class="alert alert-danger d-flex gap-2"><i class="bi bi-lock-fill"></i><div>{{ session('license_locked') }} ระบบถูกล็อกจนกว่าจะเปิดใช้งาน License สำเร็จ</div></div>
@endif

<div class="license-status {{ $cls }} mb-3">
    <span class="ico"><i class="bi bi-{{ $icon }}"></i></span>
    <div>
        <div class="fw-bold fs-5">{{ $label }}</div>
        <div class="small text-muted">{{ $license['message'] ?? match (true) {
            $license['state'] === 'grace' && $license['grace_kind'] === 'upgrade' => 'กรอกคีย์ หรือขอทดลองใช้ ก่อนครบกำหนด ไม่งั้นระบบจะล็อก',
            $license['state'] === 'grace' => 'ตรวจว่าโฮสต์ต่ออินเทอร์เน็ตออกภายนอกได้ ระบบจะลองติดต่อใหม่เอง',
            $license['days_left'] !== null => 'เหลืออีก '.$license['days_left'].' วัน',
            default => 'ไม่มีกำหนดหมดอายุ',
        } }}</div>
    </div>
</div>

<div class="info-grid mb-2">
    <div><small>คีย์</small><b class="font-monospace">{{ $license['key'] ?: 'ยังไม่มี' }}</b></div>
    <div><small>แพ็กเกจ</small><b>{{ $license['plan'] ?: '-' }}</b></div>
    <div><small>โดเมนที่ผูกไว้</small><b>{{ $license['domain'] ?: \App\Support\License\Client::domain() }}</b></div>
    <div><small>หมดอายุ</small><b>{{ $license['expires_at'] ? thai_date(date('Y-m-d', $license['expires_at'])) : 'ไม่มีกำหนด' }}</b></div>
    <div><small>ตรวจกับศูนย์ล่าสุด</small><b>{{ $license['checked_at'] ? thai_datetime(date('Y-m-d H:i:s', $license['checked_at'])) : 'ยังไม่เคยตรวจ' }}</b></div>
</div>

@if ($license['unsigned'])
    <div class="alert alert-warning d-flex gap-2 small"><i class="bi bi-exclamation-triangle-fill"></i><div>
        โฮสต์นี้ไม่มีส่วนขยาย sodium ระบบจึงตรวจลายเซ็นในเครื่องไม่ได้ และต้องติดต่อศูนย์ License ทุกวันแทน เปิด sodium ที่ Select PHP Version &gt; Extensions จะปลอดภัยกว่า
    </div></div>
@endif

<div class="set-block">
    <div class="set-block-title"><i class="bi bi-key"></i>เปิดใช้งาน</div>
    @if (! $isAdmin)
        <div class="alert alert-info d-flex gap-2 small mb-0"><i class="bi bi-info-circle-fill"></i><div>จัดการ License ได้เฉพาะผู้ดูแลระบบ</div></div>
    @else
        <form method="POST" action="{{ route('settings.license.activate') }}">
            @csrf
            <label class="form-label" for="license_key">คีย์ License</label>
            <div class="input-group" style="max-width:560px">
                <input type="text" id="license_key" name="license_key" class="form-control text-uppercase font-monospace @error('license_key') is-invalid @enderror"
                       value="{{ old('license_key') }}" placeholder="{{ config('license.prefix') }}-XXXX-XXXX-XXXX" maxlength="50" autocomplete="off">
                <button class="btn btn-primary"><i class="bi bi-check2-circle"></i>เปิดใช้งาน</button>
            </div>
            @error('license_key')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            <div class="form-text">คีย์ที่ได้รับจากผู้พัฒนา ใช้ได้กับโดเมนเดียว ถ้าย้ายโดเมนให้แจ้งผู้พัฒนาปลดโดเมนเดิมก่อน</div>
        </form>
        <div class="d-flex gap-2 flex-wrap mt-3">
            @if ($license['key'])
                <form method="POST" action="{{ route('settings.license.refresh') }}">@csrf<button class="btn btn-soft"><i class="bi bi-arrow-repeat"></i>ตรวจสอบเดี๋ยวนี้</button></form>
            @else
                <form method="POST" action="{{ route('settings.license.trial') }}" data-confirm="เริ่มทดลองใช้งาน {{ config('license.trial_days') }} วันสำหรับโดเมนนี้? (1 โดเมนขอได้ครั้งเดียว)">
                    @csrf<button class="btn btn-soft"><i class="bi bi-hourglass-split"></i>ขอทดลองใช้ {{ config('license.trial_days') }} วัน</button>
                </form>
            @endif
        </div>
    @endif
</div>

<div class="set-block">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
        <div class="set-block-title mb-0"><i class="bi bi-file-earmark-lock"></i>ความถูกต้องของไฟล์ระบบ</div>
        @if ($isAdmin)
            <form method="POST" action="{{ route('settings.license.integrity') }}">@csrf<button class="btn btn-sm btn-light border"><i class="bi bi-arrow-repeat"></i>ตรวจไฟล์เดี๋ยวนี้</button></form>
        @endif
    </div>
    @php
        $iTone = match ($integrity['state']) { 'ok' => 'success', 'changed' => 'warning', 'tampered' => 'danger', default => 'light' };
        $iText = match ($integrity['state']) {
            'ok' => 'ไฟล์ครบและตรงกับชุดที่ผู้พัฒนาส่งมา ('.$integrity['checked'].' ไฟล์)',
            'changed' => 'มีไฟล์ถูกแก้ '.count($integrity['changed']).' ไฟล์ ระบบยังใช้งานได้ แต่จะรายงานให้ผู้พัฒนาทราบ',
            'tampered' => 'ไฟล์สำคัญของระบบถูกแก้ '.count($integrity['critical']).' ไฟล์ ระบบจึงถูกล็อก',
            default => $integrity['reason'],
        };
        $bad = array_merge($integrity['critical'], $integrity['changed']);
    @endphp
    @error('integrity')<div class="alert alert-warning small">{{ $message }}</div>@enderror
    <div class="alert alert-{{ $iTone }} d-flex gap-2 small mb-0"><i class="bi bi-{{ $integrity['state'] === 'ok' ? 'check-circle-fill' : 'info-circle-fill' }}"></i><div>
        {{ $iText }}
        @if ($bad)
            <ul class="ps-3 mt-2 mb-0 font-monospace">@foreach (array_slice($bad, 0, 10) as $f)<li>{{ $f }}</li>@endforeach</ul>
            @if (count($bad) > 10)<div class="mt-1">และอีก {{ count($bad) - 10 }} ไฟล์</div>@endif
            <div class="mt-2">วิธีแก้: อัปโหลดไฟล์ชุดเดิมจากผู้พัฒนาทับอีกครั้ง (ข้อมูลในฐานข้อมูลไม่หาย)</div>
        @endif
    </div></div>
</div>

<div class="set-block small text-muted">
    <div class="fw-semibold mb-1 text-body">ข้อควรรู้</div>
    <ul class="ps-3 mb-0 d-grid gap-1">
        <li>ระบบติดต่อศูนย์ License วันละครั้ง ถ้าโฮสต์ต่อเน็ตออกไม่ได้ ใช้งานต่อได้อีก {{ config('license.grace_days') }} วันแล้วจะถูกล็อก</li>
        <li>ข้อมูลไม่หายเมื่อถูกล็อก เมื่อ License กลับมาใช้ได้ ระบบเปิดให้ใช้ต่อทันที</li>
        <li>อัปโหลดไฟล์ด้วย FTP ให้ใช้โหมด Binary หรือแตก zip บนโฮสต์ ไม่งั้นระบบจะเห็นว่าไฟล์ถูกแก้</li>
    </ul>
</div>
