@php
    $lock = \App\Support\Installer::lock();
    $realCron = \App\Support\WebCron::realCronAlive();
    $webCronLast = \Illuminate\Support\Facades\Cache::get('school.webcron_last');
    $link = public_path('storage');
    $storageOk = is_link($link) || is_dir($link);
    $db = config('database.default');
@endphp
<div class="set-head"><h3>ข้อมูลระบบ</h3><p>เวอร์ชัน งานตั้งเวลา และสภาพแวดล้อมของเซิร์ฟเวอร์ ใช้ตอนแจ้งปัญหากับผู้ดูแลระบบ</p></div>
<div class="info-grid">
    <div><small>เวอร์ชันระบบ</small><b>{{ \App\Support\Installer::codeVersion() }}</b></div>
    <div><small>ติดตั้งเมื่อ</small><b>{{ ! empty($lock['installed_at']) ? thai_datetime($lock['installed_at']) : '-' }}</b></div>
    <div><small>PHP</small><b>{{ PHP_VERSION }}</b></div>
    <div><small>ฐานข้อมูล</small><b>{{ $db === 'mysql' ? 'MySQL / MariaDB' : ucfirst($db) }}</b></div>
    <div><small>งานตั้งเวลา (cron)</small><b class="{{ $realCron ? 'text-success' : '' }}">{{ $realCron ? 'cron ของโฮสต์ทำงานอยู่' : ($webCronLast ? 'ทำงานผ่านเว็บ' : 'ยังไม่เคยทำงาน') }}</b></div>
    <div><small>งานผ่านเว็บล่าสุด</small><b>{{ $webCronLast ? thai_datetime($webCronLast) : '-' }}</b></div>
    <div><small>โฟลเดอร์รูปภาพ</small><b>{{ $storageOk ? 'ทางลัด public/storage' : 'ทางสำรอง (ส่งผ่าน PHP)' }}</b></div>
    <div><small>https</small><b class="{{ request()->isSecure() ? 'text-success' : 'text-warning' }}">{{ request()->isSecure() ? 'ใช้งานอยู่' : 'ยังไม่ใช้' }}</b></div>
</div>
@unless ($realCron)
    <div class="set-block">
        <div class="set-block-title"><i class="bi bi-clock-history"></i>ตั้ง cron ของโฮสต์ (แนะนำ)</div>
        <p class="small text-muted">ตอนนี้งานตั้งเวลา (สำรองข้อมูล แจ้งเตือน) ทำงานตอนมีคนเปิดเว็บ ตั้ง cron ทุก 1 นาทีใน cPanel > Cron Jobs ด้วยคำสั่งนี้จะตรงเวลากว่า</p>
        <code class="d-block p-3 rounded-3" style="background:#1f2937;color:#e5e7eb;word-break:break-all">php {{ base_path('artisan') }} schedule:run >> /dev/null 2>&1</code>
    </div>
@endunless
