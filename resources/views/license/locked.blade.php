@extends('install.layout', ['title' => 'ระบบถูกล็อก | ระบบบริหารโรงเรียน', 'step' => 0, 'brandSub' => 'ระบบบริหารจัดการโรงเรียนครบวงจร'])

@section('content')
<div class="inst-head">
    <div class="eyebrow">ระบบถูกล็อกชั่วคราว</div>
    <h1>ใช้งานระบบไม่ได้ในขณะนี้</h1>
    <p>ข้อมูลทั้งหมดยังอยู่ครบ เมื่อ License กลับมาใช้ได้ ระบบจะเปิดให้ใช้งานต่อทันที</p>
</div>

<div class="alert alert-danger"><i class="bi bi-lock-fill"></i><div>{{ $status['message'] }}</div></div>

<div class="inst-card">
    <h2><i class="bi bi-list-check"></i>ต้องทำอะไรต่อ</h2>
    <div class="req-row"><i class="bi bi-1-circle-fill" style="color:var(--sb-primary)"></i><span class="t">แจ้งผู้ดูแลระบบของโรงเรียน ให้เข้าสู่ระบบแล้วไปที่ ตั้งค่า &gt; License</span></div>
    <div class="req-row"><i class="bi bi-2-circle-fill" style="color:var(--sb-primary)"></i><span class="t">
        @switch($status['reason'])
            @case('tampered') ไฟล์ของระบบถูกแก้ไข ให้อัปโหลดไฟล์ชุดเดิมจากผู้พัฒนาทับอีกครั้ง (ข้อมูลในฐานข้อมูลไม่หาย) @break
            @case('offline') ตรวจว่าโฮสต์ต่ออินเทอร์เน็ตออกภายนอกได้ ระบบต้องติดต่อศูนย์ License วันละครั้ง @break
            @case('domain_mismatch') License นี้ผูกกับโดเมนอื่น ติดต่อผู้พัฒนาเพื่อย้ายโดเมน @break
            @case('clock') ตั้งวันเวลาของเครื่องแม่ข่ายให้ถูกต้อง แล้วลองใหม่ @break
            @default กรอกคีย์ License ที่ได้รับจากผู้พัฒนา หรือติดต่อผู้พัฒนาเพื่อต่ออายุ
        @endswitch
    </span></div>
</div>

<div class="inst-actions"><span></span>
    @auth
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-light btn-lg"><i class="bi bi-box-arrow-right"></i>ออกจากระบบ</button></form>
    @else
        <a href="{{ route('login') }}" class="btn btn-primary btn-lg"><i class="bi bi-box-arrow-in-right"></i>เข้าสู่ระบบในฐานะผู้ดูแลระบบ</a>
    @endauth
</div>
@endsection
