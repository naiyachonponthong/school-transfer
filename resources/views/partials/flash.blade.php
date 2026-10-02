@if (session('success') || session('warning'))
    <div class="flash-toast-stack" aria-live="polite">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-check-circle-fill"></i><div>{{ session('success') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="ปิดข้อความแจ้งเตือน"></button>
            </div>
        @endif
        @if (session('warning'))
            <div class="alert alert-warning alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i><div>{{ session('warning') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="ปิดข้อความแจ้งเตือน"></button>
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
@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <div class="fw-semibold mb-1"><i class="bi bi-exclamation-octagon-fill"></i> กรุณาตรวจสอบข้อมูล</div>
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
