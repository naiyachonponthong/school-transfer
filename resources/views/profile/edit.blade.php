@extends('layouts.app')
@section('title', 'ข้อมูลส่วนตัว')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="page-head"><h1>ข้อมูลส่วนตัว</h1></div>
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
                <div class="col-12"><label class="form-label">ชื่อ-สกุล</label><input name="name" value="{{ old('name', $user->name) }}" class="form-control" required @readonly($user->isStudent())>@if ($user->isStudent())<div class="form-text">แก้ชื่อได้ที่ครูประจำชั้น</div>@endif</div>
                <div class="col-md-6"><label class="form-label">เบอร์โทร</label><input name="phone" value="{{ old('phone', $user->phone) }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">อีเมล</label><input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control"></div>
            </div>
            <div class="card-header border-top"><i class="bi bi-key"></i> เปลี่ยนรหัสผ่าน</div>
            <div class="card-body row g-3">
                <div class="col-12"><label class="form-label">รหัสผ่านเดิม</label><input type="password" name="current_password" class="form-control" autocomplete="current-password"></div>
                <div class="col-md-6"><label class="form-label">รหัสผ่านใหม่</label><input type="password" name="password" class="form-control" autocomplete="new-password" minlength="6"></div>
                <div class="col-md-6"><label class="form-label">ยืนยันรหัสผ่านใหม่</label><input type="password" name="password_confirmation" class="form-control" autocomplete="new-password"></div>
            </div>
            <div class="card-footer bg-transparent"><button class="btn btn-primary"><i class="bi bi-save"></i> บันทึก</button></div>
        </form>

        {{-- เชื่อม LINE รับแจ้งเตือน --}}
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-chat-dots" style="color:#06c755"></i> รับแจ้งเตือนทาง LINE</div>
            <div class="card-body">
                @if ($user->hasLine())
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill text-success fs-4"></i>
                        <div class="flex-grow-1">เชื่อม LINE แล้ว เมื่อ {{ thai_datetime($user->line_linked_at) }}</div>
                        <form method="POST" action="{{ route('profile.line.unlink') }}" data-confirm="ยกเลิกการรับแจ้งเตือนทาง LINE?">@csrf @method('DELETE')<button class="btn btn-sm btn-light border">ยกเลิก</button></form>
                    </div>
                @elseif ($user->line_link_code)
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
                    <form method="POST" action="{{ route('profile.line') }}">@csrf<button class="btn text-white" style="background:#06c755"><i class="bi bi-link-45deg"></i> เชื่อม LINE</button></form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
