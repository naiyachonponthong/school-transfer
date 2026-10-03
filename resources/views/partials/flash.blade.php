{{-- แจ้งเตือนแบบป๊อปอัป: สำเร็จ/คำเตือนปิดเองอัตโนมัติ ข้อผิดพลาดค้างไว้จนกว่าจะกดปิด (สคริปต์อยู่ใน app.js) --}}
@if (session('success') || session('warning') || $errors->any())
    <div class="sb-toasts" aria-live="polite">
        @if (session('success'))
            <div class="sb-toast success" role="status" data-life="5000">
                <span class="ic"><i class="bi bi-check-lg"></i></span>
                <div class="tx"><b>สำเร็จ</b>{{ session('success') }}</div>
                <button type="button" class="x" data-toast-close aria-label="ปิดข้อความแจ้งเตือน"><i class="bi bi-x-lg"></i></button>
                <span class="bar"></span>
            </div>
        @endif
        @if (session('warning'))
            <div class="sb-toast warning" role="alert" data-life="8000">
                <span class="ic"><i class="bi bi-exclamation-triangle"></i></span>
                <div class="tx"><b>แจ้งเตือน</b>{{ session('warning') }}</div>
                <button type="button" class="x" data-toast-close aria-label="ปิดข้อความแจ้งเตือน"><i class="bi bi-x-lg"></i></button>
                <span class="bar"></span>
            </div>
        @endif
        @if ($errors->any())
            <div class="sb-toast danger" role="alert">
                <span class="ic"><i class="bi bi-exclamation-octagon"></i></span>
                <div class="tx">
                    <b>กรุณาตรวจสอบข้อมูล</b>
                    @if ($errors->count() === 1){{ $errors->first() }}@else<ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>@endif
                </div>
                <button type="button" class="x" data-toast-close aria-label="ปิดข้อความแจ้งเตือน"><i class="bi bi-x-lg"></i></button>
            </div>
        @endif
    </div>
@endif
@if (session('credential'))
    <div class="alert alert-info d-flex align-items-start gap-2" role="alert">
        <i class="bi bi-key-fill fs-5"></i>
        <div>
            <div class="fw-semibold">ข้อมูลเข้าสู่ระบบ (แจ้งให้ผู้ใช้ทราบ แล้วให้เปลี่ยนรหัสผ่านเอง)</div>
            ชื่อผู้ใช้: <code class="fs-6">{{ session('credential.username') }}</code>
            &nbsp; รหัสผ่าน: <code class="fs-6">{{ session('credential.password') }}</code>
        </div>
    </div>
@endif
