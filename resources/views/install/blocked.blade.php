@extends('install.layout', ['title' => 'ระบบยังไม่พร้อม | ระบบบริหารโรงเรียน', 'step' => 0, 'brandSub' => 'ระบบบริหารจัดการโรงเรียนครบวงจร'])

@section('content')
<div class="inst-head">
    <div class="eyebrow">ระบบยังไม่พร้อม</div>
    <h1>เชื่อมต่อฐานข้อมูลไม่ได้</h1>
    <p>ระบบมีไฟล์ตั้งค่าอยู่แล้ว แต่ยังเปิดฐานข้อมูลไม่ได้ หรือฐานข้อมูลยังไม่มีข้อมูลโรงเรียน</p>
</div>
<div class="inst-card">
    <h2><i class="bi bi-tools"></i>วิธีแก้</h2>
    <div class="req-row"><i class="bi bi-1-circle-fill" style="color:var(--sb-primary)"></i><span class="t">ถ้าเคยใช้งานได้: เซิร์ฟเวอร์ฐานข้อมูลอาจล่มชั่วคราว รอสักครู่แล้วรีเฟรช หรือแจ้งผู้ให้บริการโฮสต์</span></div>
    <div class="req-row"><i class="bi bi-2-circle-fill" style="color:var(--sb-primary)"></i><span class="t">ถ้าเพิ่งย้ายโฮสต์หรือเปลี่ยนรหัสฐานข้อมูล: แก้ค่า DB_ ในไฟล์ <code>.env</code> ให้ตรง</span></div>
    <div class="req-row"><i class="bi bi-3-circle-fill" style="color:var(--sb-primary)"></i><span class="t">ถ้ายังไม่เคยติดตั้ง: ลบไฟล์ <code>.env</code> แล้วรีเฟรช ระบบจะเปิดตัวติดตั้งให้</span></div>
</div>
<div class="inst-actions"><span></span><a href="{{ url()->current() }}" class="btn btn-primary btn-lg"><i class="bi bi-arrow-repeat"></i>ลองอีกครั้ง</a></div>
@endsection
