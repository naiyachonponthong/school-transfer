@extends('install.layout', ['title' => 'ติดตั้งเสร็จแล้ว | ระบบบริหารโรงเรียน'])

@section('content')
<div class="done-hero">
    <div class="ico"><i class="bi bi-check-lg"></i></div>
    <h1 class="fw-bold" style="letter-spacing:-.02em">ติดตั้งเสร็จแล้ว</h1>
    <p class="text-muted mb-0">ระบบพร้อมใช้งาน ตัวติดตั้งถูกปิดอัตโนมัติ</p>
</div>

<div class="inst-card">
    <h2><i class="bi bi-box-arrow-in-right"></i>เข้าสู่ระบบ</h2>
    <p class="mb-3">ใช้ชื่อผู้ใช้ <b>{{ $lock['login'] ?? '' }}</b> และรหัสผ่านที่ตั้งไว้</p>
    <a href="{{ route('login') }}" class="btn btn-primary btn-lg w-100" id="goLogin"><i class="bi bi-box-arrow-in-right"></i>ไปหน้าเข้าสู่ระบบ</a>
</div>

@if ($license['state'] === 'locked')
    <div class="alert alert-warning"><i class="bi bi-lock-fill"></i><div>
        <b>ยังไม่ได้เปิดใช้งาน License</b> ระบบจะล็อกไว้ก่อน เข้าสู่ระบบด้วยบัญชีผู้ดูแลระบบ แล้วกรอกคีย์หรือขอทดลองใช้ที่ ตั้งค่า &gt; License (ระบบพาไปให้เอง)
    </div></div>
@else
    <div class="alert alert-success"><i class="bi bi-patch-check-fill"></i><div>
        <b>{{ $license['state'] === 'trial' ? 'ทดลองใช้งาน' : 'เปิดใช้งาน License แล้ว' }}</b>
        @if ($license['days_left'] !== null) เหลืออีก {{ $license['days_left'] }} วัน @endif
        · โดเมน {{ $license['domain'] }}
    </div></div>
@endif

@if (! empty($lock['demo']))
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill"></i><div>
        <b>มีบัญชีตัวอย่าง</b> ครู <code>teacher</code> / <code>teacher1234</code> · ผู้ปกครอง <code>0812345678</code> / <code>345678</code> · นักเรียน <code>69001</code> / <code>student1234</code>
        และครูอีกหลายบัญชี ก่อนใช้งานจริงให้ติดตั้งใหม่แบบ เริ่มใช้งานจริง หรือปิดบัญชีตัวอย่างที่เมนู ผู้ใช้</div></div>
@endif

<div class="inst-card">
    <h2><i class="bi bi-clock-history"></i>ตั้งงานตั้งเวลา (cron) แนะนำ</h2>
    <p class="text-muted small">cPanel เมนู Cron Jobs เลือก Once Per Minute แล้ววางคำสั่งนี้ (บางโฮสต์ต้องใช้ <code>/usr/local/bin/php</code> แทน <code>php</code>)</p>
    <div class="code-line"><code id="cronCmd">{{ $cron }}</code><button type="button" class="btn btn-light" id="cronCopy"><i class="bi bi-clipboard"></i>คัดลอก</button></div>
    <p class="small text-muted mt-2 mb-0">ถ้าโฮสต์ไม่มี cron ก็ใช้ได้ ระบบจะสำรองข้อมูลและส่งแจ้งเตือนตามเวลาเองตอนมีคนเปิดเว็บ</p>
</div>

<div class="inst-card">
    <h2><i class="bi bi-list-check"></i>ขั้นต่อไป</h2>
    <div class="req-row"><i class="bi bi-1-circle-fill" style="color:var(--sb-primary)"></i><span class="t">ตั้งค่า: โลโก้ ที่อยู่ ผู้อำนวยการ สีธีม พร้อมเพย์ และ LINE</span></div>
    <div class="req-row"><i class="bi bi-2-circle-fill" style="color:var(--sb-primary)"></i><span class="t">ปีการศึกษาและภาคเรียน แล้วห้องเรียนและรายวิชา</span></div>
    <div class="req-row"><i class="bi bi-3-circle-fill" style="color:var(--sb-primary)"></i><span class="t">ผู้ใช้: เพิ่มบัญชีครูและบุคลากร กำหนดบทบาท</span></div>
    <div class="req-row"><i class="bi bi-4-circle-fill" style="color:var(--sb-primary)"></i><span class="t">นักเรียน: นำเข้าจากไฟล์ Excel ตามแม่แบบ</span></div>
</div>
<p class="small text-muted text-center">ต้องการติดตั้งใหม่ภายหลัง: ลบไฟล์ <code>.env</code> และ <code>storage/installed</code> แล้วเปิดหน้าเว็บ (ข้อมูลในฐานข้อมูลยังอยู่ เลือกใช้ต่อหรือลบได้)</p>
@endsection

@push('scripts')
<script>
document.getElementById('cronCopy').addEventListener('click', async (e) => {
  try { await navigator.clipboard.writeText(document.getElementById('cronCmd').textContent); e.currentTarget.innerHTML = '<i class="bi bi-check2"></i>คัดลอกแล้ว'; } catch (err) {}
});
</script>
@endpush
