<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ประกาศความเป็นส่วนตัว · {{ school('school_name') }}</title>
    @include('partials.assets')
</head>
<body>
<div class="container py-5" style="max-width:720px">
    <div class="card">
        <div class="card-body p-4">
            <h1 class="h4 fw-bold mb-1">ประกาศความเป็นส่วนตัว</h1>
            <p class="text-muted small">{{ school('school_name') }}</p>
            <div class="border rounded-3 p-3 mb-3 bg-light" style="white-space:pre-line;max-height:50vh;overflow:auto">{{ $notice ?: 'โรงเรียนยังไม่ได้ตั้งประกาศความเป็นส่วนตัว' }}</div>

            @if ($needsAccept)
                <form method="POST" action="{{ route('privacy.accept') }}">
                    @csrf
                    <label class="d-flex gap-2 mb-3"><input type="checkbox" class="form-check-input" name="agree" value="1" required> <span>ข้าพเจ้าได้อ่านและรับทราบประกาศความเป็นส่วนตัวนี้แล้ว</span></label>
                    @error('agree')<div class="small text-danger mb-2">{{ $message }}</div>@enderror
                    <button class="btn btn-primary btn-lg w-100">รับทราบและเข้าใช้งาน</button>
                </form>
                <form method="POST" action="{{ route('logout') }}" class="text-center mt-3">@csrf<button class="btn btn-link btn-sm text-muted">ออกจากระบบ</button></form>
            @else
                <a href="{{ route('home') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
            @endif
        </div>
    </div>
</div>
</body>
</html>
