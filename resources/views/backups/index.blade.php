@extends('layouts.app')
@section('title', 'สำรองข้อมูล')

@section('content')
<div class="page-head">
    <div><h1>สำรองข้อมูล</h1><div class="sub">ระบบสำรองอัตโนมัติทุกคืน (ฐานข้อมูล 02:00 · ไฟล์อัปโหลด 02:15) เก็บย้อนหลัง 14 วัน — ต้องตั้ง Task Scheduler/cron ให้รัน <code>php artisan schedule:run</code> ทุกนาที</div></div>
    <div class="actions">
        <form method="POST" action="{{ route('backups.run') }}">@csrf<button class="btn btn-primary"><i class="bi bi-cloud-arrow-up"></i> สำรองตอนนี้</button></form>
    </div>
</div>

<div class="alert alert-warning small">
    <i class="bi bi-exclamation-triangle"></i> ไฟล์สำรองอยู่ในเครื่องเดียวกับระบบ ถ้าเครื่องเสียจะหายไปด้วย — <b>ดาวน์โหลดไปเก็บที่อื่นเป็นประจำ</b> (ไดรฟ์อื่น / แฟลชไดรฟ์ / คลาวด์ของโรงเรียน) · ไฟล์มีข้อมูลส่วนบุคคลของนักเรียน เก็บในที่ที่ปลอดภัย
</div>

<div class="card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>ไฟล์</th><th>ประเภท</th><th class="text-end">ขนาด</th><th>เวลา</th><th></th></tr></thead>
            <tbody>
            @forelse ($files as $f)
                <tr>
                    <td class="fw-semibold small">{{ $f['name'] }}</td>
                    <td><span class="badge bg-light text-dark border">{{ $f['type'] }}</span></td>
                    <td class="text-end small">{{ number_format($f['size'] / 1024, 0) }} KB</td>
                    <td class="small">{{ thai_datetime(\Illuminate\Support\Carbon::createFromTimestamp($f['time'])->setTimezone(config('app.timezone'))) }}</td>
                    <td class="text-end"><a href="{{ route('backups.download', $f['name']) }}" class="btn btn-sm btn-light border"><i class="bi bi-download"></i> ดาวน์โหลด</a></td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty"><i class="bi bi-archive"></i>ยังไม่มีไฟล์สำรอง กด "สำรองตอนนี้" ได้เลย</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-arrow-counterclockwise"></i> วิธีกู้คืน (ทำที่เครื่องเซิร์ฟเวอร์)</div>
    <div class="card-body small">
        <p class="mb-2">เพื่อความปลอดภัย การกู้คืนทำได้จากคำสั่งในโฟลเดอร์ของระบบเท่านั้น ระบบจะสำรองข้อมูลปัจจุบันไว้ก่อนกู้ทุกครั้ง:</p>
        <pre class="bg-light p-2 rounded mb-2"><code>php artisan backup:restore</code></pre>
        <p class="mb-0 text-muted">เลือกไฟล์จากรายการ: ไฟล์ <code>.sql</code>/<code>.sqlite</code> = กู้ฐานข้อมูลทั้งหมด · ไฟล์ <code>files-*.zip</code> = กู้รูปและไฟล์แนบ · ถ้าไฟล์อยู่ที่อื่นให้คัดลอกมาไว้ที่ <code>storage\app\backups</code> ก่อน</p>
    </div>
</div>
@endsection
