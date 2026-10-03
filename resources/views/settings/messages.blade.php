@extends('layouts.app')
@section('title', 'LINE แจ้งเตือน')

@section('content')
<div class="page-head">
    <div><h1>LINE แจ้งเตือน</h1><div class="sub">ผู้ปกครองเชื่อม LINE แล้ว {{ $linked }} บัญชี (จากผู้ปกครอง {{ $parents }} คน) · เดือนนี้ส่งสำเร็จ <b>{{ number_format($sentThisMonth) }}</b> ข้อความ{{ $queued ? ' · รอในคิว '.number_format($queued) : '' }}</div></div>
    <div class="actions">
        <a href="{{ route('settings') }}#line" class="btn btn-light border"><i class="bi bi-gear"></i> ตั้งค่า LINE</a>
        <form method="POST" action="{{ route('settings.line-test') }}">@csrf<button class="btn btn-primary"><i class="bi bi-send"></i> ส่งทดสอบหาตัวเอง</button></form>
    </div>
</div>

@unless ($configured)
    <div class="alert alert-warning">
        <b>ยังไม่ได้ตั้งค่า LINE Official Account</b> — ระบบจะบันทึกข้อความไว้แต่ยังไม่ส่งจริง (สถานะ "skipped")
        <ol class="mb-0 mt-2 small">
            <li>สร้าง LINE Official Account ของโรงเรียน แล้วเปิดใช้ Messaging API ที่ <b>LINE Developers Console</b></li>
            <li>คัดลอก <b>Channel secret</b> และออก <b>Channel access token (long-lived)</b> มาใส่ในหน้าตั้งค่า</li>
            <li>ตั้ง Webhook URL เป็น <code>{{ route('line.webhook') }}</code> (ต้องเป็น https ที่เข้าถึงได้จากอินเทอร์เน็ต) แล้วเปิด "Use webhook"</li>
            <li>ผู้ปกครอง/ครูเพิ่มเพื่อน OA แล้วพิมพ์รหัส 6 หลักจากหน้า "บัญชีของฉัน" เพื่อเชื่อมบัญชี</li>
        </ol>
        <div class="small mt-2">หมายเหตุ: LINE Notify ปิดบริการแล้ว (มี.ค. 2568) ระบบจึงใช้ Messaging API ซึ่งมีโควตาข้อความฟรีต่อเดือนตามแพ็กเกจของ OA</div>
    </div>
@endunless

<div class="card">
    <div class="card-header"><i class="bi bi-clock-history"></i> ประวัติการส่ง</div>
    <div class="table-responsive">
        <table class="table table-cards align-middle small">
            <thead><tr><th>เวลา</th><th>ผู้รับ</th><th>ข้อความ</th><th>สถานะ</th></tr></thead>
            <tbody>
            @forelse ($logs as $l)
                <tr>
                    <td class="text-nowrap">{{ thai_datetime($l->created_at) }}</td>
                    <td>{{ $l->user?->name }}</td>
                    <td style="max-width:460px"><div class="text-truncate">{{ $l->text }}</div></td>
                    <td><span class="badge bg-{{ ['sent' => 'success', 'skipped' => 'secondary', 'failed' => 'danger'][$l->status] ?? 'secondary' }}">{{ $l->status }}</span>
                        @if ($l->error)<div class="text-muted" style="font-size:.75rem">{{ $l->error }}</div>@endif</td>
                </tr>
            @empty
                <tr><td colspan="4"><div class="empty"><i class="bi bi-chat-dots"></i>ยังไม่มีการส่ง</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $logs->links() }}</div>
@endsection
