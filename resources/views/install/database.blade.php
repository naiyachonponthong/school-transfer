@extends('install.layout', ['title' => 'ฐานข้อมูล | ติดตั้ง ระบบบริหารโรงเรียน'])

@section('content')
<div class="inst-head">
    <div class="eyebrow">ขั้นตอนที่ 3 จาก 5</div>
    <h1>เชื่อมต่อฐานข้อมูล</h1>
    <p>กรอกข้อมูลฐานข้อมูลที่สร้างไว้ในแผงควบคุมโฮสต์ แล้วกดทดสอบก่อนไปต่อ</p>
</div>

@if ($errors->any())
    <div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i><div>{{ $errors->first('database') ?: $errors->first() }}</div></div>
@endif

<form method="POST" action="{{ route('install.database.save') }}" id="dbForm" data-busy="กำลังเชื่อมต่อ" novalidate>
    @csrf
    <div class="inst-card">
        <h2><i class="bi bi-database"></i>ชนิดฐานข้อมูล</h2>
        @php($drv = old('driver', $cfg['driver']))
        <div class="choice-grid">
            <label class="choice"><input type="radio" name="driver" value="mysql" @checked($drv === 'mysql') @disabled(! $mysqlOk)><span class="ic"><i class="bi bi-hdd-stack"></i></span>
                <span><b>MySQL / MariaDB</b><small>แนะนำ ใช้กับโฮสต์ทั่วไป รองรับหลายเครื่องพร้อมกัน{{ $mysqlOk ? '' : ' (โฮสต์ยังไม่เปิด pdo_mysql)' }}</small></span></label>
            <label class="choice"><input type="radio" name="driver" value="sqlite" @checked($drv === 'sqlite') @disabled(! $sqliteOk)><span class="ic"><i class="bi bi-file-earmark-binary"></i></span>
                <span><b>SQLite (ทดลองเท่านั้น)</b><small>ไม่ต้องสร้างฐานข้อมูล ใช้ทดลองระบบ ไม่เหมาะกับใช้งานจริงที่ครูและผู้ปกครองเข้าพร้อมกันหลายคน{{ $sqliteOk ? '' : ' (โฮสต์ยังไม่เปิด pdo_sqlite)' }}</small></span></label>
        </div>
    </div>

    <div class="inst-card" id="mysqlBox">
        <h2><i class="bi bi-key"></i>ข้อมูลการเชื่อมต่อ</h2>
        <div class="row g-3">
            <div class="col-8"><label class="form-label req" for="host">Host</label><input id="host" name="host" class="form-control" value="{{ old('host', $cfg['host']) }}" placeholder="localhost"><div class="form-text">โฮสต์ส่วนใหญ่ใช้ localhost</div></div>
            <div class="col-4"><label class="form-label" for="port">Port</label><input id="port" name="port" class="form-control" inputmode="numeric" value="{{ old('port', $cfg['port']) }}"></div>
            <div class="col-12"><label class="form-label req" for="database">ชื่อฐานข้อมูล</label><input id="database" name="database" class="form-control" value="{{ old('database', $cfg['database']) }}" placeholder="เช่น user_school" autocomplete="off"></div>
            <div class="col-sm-6"><label class="form-label req" for="username">ชื่อผู้ใช้ฐานข้อมูล</label><input id="username" name="username" class="form-control" value="{{ old('username', $cfg['username']) }}" autocomplete="off"></div>
            <div class="col-sm-6"><label class="form-label" for="dbpass">รหัสผ่านฐานข้อมูล</label>
                <div class="input-group"><input id="dbpass" name="password" type="password" class="form-control" autocomplete="new-password"><button type="button" class="btn btn-light" data-eye="dbpass" aria-label="แสดงรหัสผ่าน"><i class="bi bi-eye"></i></button></div></div>
        </div>
        <details class="inst-help mt-3">
            <summary>ยังไม่มีฐานข้อมูล สร้างอย่างไร</summary>
            <ol>
                <li><b>cPanel</b>: เมนู MySQL Databases &gt; Create New Database ตั้งชื่อ เช่น school</li>
                <li>ในหน้าเดียวกัน ส่วน MySQL Users &gt; Add New User ตั้งชื่อและรหัสผ่าน</li>
                <li>ส่วน Add User To Database เลือกผู้ใช้และฐานข้อมูล แล้วติ๊ก <b>ALL PRIVILEGES</b></li>
                <li><b>DirectAdmin</b>: เมนู MySQL Management &gt; Create new Database (สร้างผู้ใช้ให้ในขั้นเดียว)</li>
                <li>ชื่อจริงมักมีชื่อบัญชีโฮสต์นำหน้า เช่น <code>myhost_school</code> ให้กรอกชื่อเต็ม</li>
            </ol>
        </details>
    </div>

    <div class="inst-card d-none" id="sqliteBox">
        <h2><i class="bi bi-file-earmark-binary"></i>SQLite</h2>
        <p class="mb-0 text-muted">ระบบสร้างไฟล์ <code>database/database.sqlite</code> ให้เอง ต้องสำรองไฟล์นี้เป็นประจำ ใช้งานจริงต้องเป็น MySQL หรือ MariaDB</p>
    </div>

    <div id="dbResult" class="d-none"></div>

    <div class="inst-actions">
        <a href="{{ route('install.license') }}" class="btn btn-light btn-lg"><i class="bi bi-arrow-left"></i>ย้อนกลับ</a>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-soft btn-lg" id="dbTest"><i class="bi bi-plug"></i>ทดสอบการเชื่อมต่อ</button>
            <button type="submit" class="btn btn-primary btn-lg">บันทึกและไปต่อ <i class="bi bi-arrow-right"></i></button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
  const form = document.getElementById('dbForm'), res = document.getElementById('dbResult');
  const sync = () => {
    const sqlite = form.querySelector('[name=driver]:checked')?.value === 'sqlite';
    document.getElementById('mysqlBox').classList.toggle('d-none', sqlite);
    document.getElementById('sqliteBox').classList.toggle('d-none', !sqlite);
  };
  form.querySelectorAll('[name=driver]').forEach((r) => r.addEventListener('change', () => { sync(); res.classList.add('d-none'); }));
  sync();
  document.getElementById('dbTest').addEventListener('click', async (e) => {
    const b = e.currentTarget, label = b.innerHTML;
    b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm"></span> กำลังทดสอบ';
    const j = await INST.post(@json(route('install.database.test')), new FormData(form));
    b.disabled = false; b.innerHTML = label;
    res.className = 'alert ' + (j.ok ? 'alert-success' : 'alert-danger');
    res.innerHTML = '<i class="bi bi-' + (j.ok ? 'check-circle-fill' : 'x-circle-fill') + '"></i><div>' + INST.esc(j.message) + '</div>';
  });
})();
</script>
@endpush
