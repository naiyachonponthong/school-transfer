@extends('layouts.app')
@section('title', 'ข้อมูลส่วนตัว')

@section('content')
<div class="page-head"><h1>ข้อมูลส่วนตัว</h1></div>
<div class="row g-3">
    <div class="col-xl-8">
        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="card">
            @csrf @method('PUT')
            <div class="card-body row g-3">
                <div class="col-12 d-flex align-items-center gap-3">
                    <label class="position-relative" style="cursor:pointer" title="เปลี่ยนรูปโปรไฟล์">
                        <span class="sb-avatar xl">@if($user->avatarUrl())<img src="{{ $user->avatarUrl() }}" alt="" id="avatarPreview">@else<span id="avatarInitial">{{ $user->initials() }}</span>@endif</span>
                        <span class="position-absolute bottom-0 end-0 bg-primary text-white rounded-circle d-grid" style="width:24px;height:24px;place-items:center;font-size:.75rem"><i class="bi bi-camera-fill"></i></span>
                        <input type="file" name="avatar" accept="image/*" class="d-none" data-preview="#avatarNew">
                    </label>
                    <img id="avatarNew" class="d-none rounded-circle" style="width:64px;height:64px;object-fit:cover" alt="">
                    <div><div class="fw-semibold">{{ $user->username }}</div><div class="small text-muted">{{ $user->roleLabel() }} · แตะรูปเพื่อเปลี่ยน</div></div>
                </div>
                <div class="col-md-4"><label class="form-label">ชื่อ-สกุล</label><input name="name" value="{{ old('name', $user->name) }}" class="form-control" required @readonly($user->isStudent())>@if ($user->isStudent())<div class="form-text">แก้ชื่อได้ที่ครูประจำชั้น</div>@endif</div>
                <div class="col-md-4"><label class="form-label">เบอร์โทร</label><input name="phone" value="{{ old('phone', $user->phone) }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">อีเมล</label><input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control"></div>
            </div>
            <div class="card-header border-top"><i class="bi bi-key"></i> เปลี่ยนรหัสผ่าน</div>
            <div class="card-body row g-3">
                <div class="col-md-4"><label class="form-label">รหัสผ่านเดิม</label><input type="password" name="current_password" class="form-control" autocomplete="current-password"></div>
                <div class="col-md-4"><label class="form-label">รหัสผ่านใหม่ <span class="text-muted fw-normal small">(อย่างน้อย 8 ตัว มีตัวอักษรและตัวเลข)</span></label><input type="password" name="password" class="form-control" autocomplete="new-password" minlength="8"></div>
                <div class="col-md-4"><label class="form-label">ยืนยันรหัสผ่านใหม่</label><input type="password" name="password_confirmation" class="form-control" autocomplete="new-password"></div>
            </div>
            <div class="card-footer bg-transparent"><button class="btn btn-primary"><i class="bi bi-save"></i> บันทึก</button></div>
        </form>
    </div>

    {{-- เชื่อม LINE รับแจ้งเตือน --}}
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-chat-dots" style="color:#06c755"></i> รับแจ้งเตือนทาง LINE</div>
            <div class="card-body">
                @if ($user->hasLine())
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill text-success fs-4"></i>
                        <div class="flex-grow-1">เชื่อม LINE แล้ว เมื่อ {{ thai_datetime($user->line_linked_at) }}</div>
                        <form method="POST" action="{{ route('profile.line.unlink') }}" data-confirm="ยกเลิกการรับแจ้งเตือนทาง LINE?">@csrf @method('DELETE')<button class="btn btn-sm btn-light border">ยกเลิก</button></form>
                    </div>
                @elseif ($user->activeLineCode())
                    <ol class="mb-3 small">
                        <li>เพิ่มเพื่อน LINE ของโรงเรียน @if (school('line_oa_id'))<a href="https://line.me/R/ti/p/{{ urlencode(school('line_oa_id')) }}" target="_blank" class="fw-semibold">{{ school('line_oa_id') }}</a>@endif</li>
                        <li>พิมพ์รหัสนี้ในแชทของโรงเรียน</li>
                    </ol>
                    <div class="text-center">
                        <div class="display-5 fw-bold text-primary" style="letter-spacing:.3em">{{ $user->line_link_code }}</div>
                        @if (school('line_oa_id'))
                            <a href="https://line.me/R/ti/p/{{ urlencode(school('line_oa_id')) }}" target="_blank" class="btn mt-2 text-white" style="background:#06c755"><i class="bi bi-chat-dots"></i> เปิด LINE เพิ่มเพื่อน</a>
                        @endif
                        <div class="small text-muted mt-2">เชื่อมสำเร็จแล้ว รีเฟรชหน้านี้เพื่อดูสถานะ</div>
                    </div>
                @else
                    <p class="small text-muted">{{ $user->isParent() ? 'รับแจ้งเตือนเมื่อบุตรหลานมาถึง/กลับจากโรงเรียน ขาดเรียน ผลใบลา ห้องพยาบาล ค่าเทอม และประกาศ' : 'รับแจ้งเตือนใบลา ผลการลางาน และประกาศของโรงเรียน' }}</p>
                    <div class="d-flex flex-wrap gap-2">
                        @if (\App\Http\Controllers\Auth\LineLoginController::configured())
                            <a href="{{ route('line.login') }}" class="btn text-white" style="background:#06c755"><i class="bi bi-box-arrow-in-right"></i> เชื่อมด้วยบัญชี LINE</a>
                        @endif
                        <form method="POST" action="{{ route('profile.line') }}">@csrf<button class="btn {{ \App\Http\Controllers\Auth\LineLoginController::configured() ? 'btn-light border' : 'text-white' }}" @style(['background:#06c755' => ! \App\Http\Controllers\Auth\LineLoginController::configured()])><i class="bi bi-link-45deg"></i> เชื่อมด้วยรหัส 6 หลัก</button></form>
                    </div>
                @endif
            </div>
        </div>

        @if ($user->isStaff())
            <div class="card mt-3">
                <div class="card-header"><i class="bi bi-shield-lock"></i> ยืนยันตัวตน 2 ขั้น
                    <span class="ms-auto badge fw-normal {{ $user->hasTwoFactor() ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }}">{{ $user->hasTwoFactor() ? 'เปิดใช้อยู่' : 'ยังไม่ได้เปิด' }}</span>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">เข้าสู่ระบบด้วยรหัสผ่านและรหัส 6 หลักจากแอปบนมือถือ แนะนำสำหรับบัญชีที่ดูแลเงิน ผู้ใช้ หรือการตั้งค่า</p>
                    <a href="{{ route('two-factor.setup') }}" class="btn {{ $user->hasTwoFactor() ? 'btn-light border' : 'btn-primary' }}"><i class="bi bi-shield-check"></i> {{ $user->hasTwoFactor() ? 'จัดการ' : 'เปิดใช้' }}</a>
                </div>
            </div>
        @endif

        {{-- แจ้งเตือนบนอุปกรณ์นี้ (Web Push) --}}
        <div class="card mt-3" id="pushCard" data-key="{{ \App\Services\WebPush::publicKey() }}" data-sw="{{ asset('sw.js') }}" data-subscribe="{{ route('push.subscribe') }}">
            <div class="card-header"><i class="bi bi-bell"></i> แจ้งเตือนบนอุปกรณ์นี้
                <span class="ms-auto badge bg-secondary-subtle text-secondary-emphasis fw-normal" id="pushState">กำลังตรวจสอบ…</span>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2">รับแจ้งเตือนเด้งบนมือถือหรือคอมพิวเตอร์เครื่องนี้โดยไม่ต้องเปิดหน้าเว็บค้างไว้ (เปิดได้หลายเครื่อง · iPhone ต้องติดตั้งแอปลงหน้าจอโฮมก่อน)</p>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary d-none" id="pushOn"><i class="bi bi-bell-fill"></i> เปิดรับแจ้งเตือน</button>
                    <button type="button" class="btn btn-light border d-none" id="pushOff">ปิดบนเครื่องนี้</button>
                    <form method="POST" action="{{ route('push.test') }}" class="d-none" id="pushTest">@csrf<button class="btn btn-light border"><i class="bi bi-send"></i> ส่งทดสอบ</button></form>
                </div>
                <div class="small text-muted mt-2">อุปกรณ์ที่เปิดรับอยู่ {{ $user->pushSubscriptions()->count() }} เครื่อง</div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const card = document.getElementById('pushCard'), state = document.getElementById('pushState');
    const on = document.getElementById('pushOn'), off = document.getElementById('pushOff'), test = document.getElementById('pushTest');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const show = (label, cls, buttons) => {
        state.textContent = label; state.className = 'ms-auto badge fw-normal ' + cls;
        on.classList.toggle('d-none', !buttons.includes('on')); off.classList.toggle('d-none', !buttons.includes('off')); test.classList.toggle('d-none', !buttons.includes('off'));
    };
    if (!card.dataset.key) return show('เซิร์ฟเวอร์ยังไม่พร้อมใช้งาน', 'bg-secondary-subtle text-secondary-emphasis', []);
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        return show('เบราว์เซอร์นี้ไม่รองรับ', 'bg-secondary-subtle text-secondary-emphasis', []);
    }
    const keyBytes = (b64) => Uint8Array.from(atob(b64.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - b64.length % 4) % 4)), (c) => c.charCodeAt(0));
    const call = (method, endpoint) => fetch(card.dataset.subscribe, {
        method, credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, body: JSON.stringify({ endpoint }),
    });
    const refresh = async () => {
        if (Notification.permission === 'denied') return show('ถูกปิดกั้นในเบราว์เซอร์', 'bg-danger-subtle text-danger-emphasis', []);
        const reg = await navigator.serviceWorker.getRegistration(card.dataset.sw);
        const sub = reg && await reg.pushManager.getSubscription();
        sub ? show('เปิดอยู่บนเครื่องนี้', 'bg-success-subtle text-success-emphasis', ['off']) : show('ยังไม่ได้เปิด', 'bg-secondary-subtle text-secondary-emphasis', ['on']);
    };
    on.addEventListener('click', async () => {
        try {
            if (await Notification.requestPermission() !== 'granted') return refresh();
            const reg = await navigator.serviceWorker.register(card.dataset.sw);
            await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(card.dataset.key) });
            const res = await call('POST', sub.endpoint);
            if (!res.ok) { await sub.unsubscribe(); alert('เปิดรับแจ้งเตือนไม่สำเร็จ'); }
        } catch (e) { alert('เปิดรับแจ้งเตือนไม่สำเร็จ: ' + e.message); }
        refresh();
    });
    off.addEventListener('click', async () => {
        const reg = await navigator.serviceWorker.getRegistration(card.dataset.sw);
        const sub = reg && await reg.pushManager.getSubscription();
        if (sub) { await call('DELETE', sub.endpoint); await sub.unsubscribe(); }
        refresh();
    });
    refresh();
})();
</script>
@endpush
