@extends('admissions.docs._layout')

@section('doc')
@php($logo = school('logo') ? asset('storage/'.school('logo')) : null)
<div class="page" style="min-height:auto">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem">
        <div style="display:flex;gap:.8rem;align-items:center">
            @if ($logo)<img src="{{ $logo }}" class="logo" alt="">@endif
            <div><b style="font-size:1.1rem">{{ school('school_name') }}</b><div class="small muted">{{ school('school_address') }}@if (school('school_phone')) · โทร {{ school('school_phone') }}@endif</div></div>
        </div>
        <div class="right small">เลขที่ <b>{{ $a->fee_receipt_no }}</b><br>วันที่ {{ thai_date($a->fee_paid_at, true) }}</div>
    </div>
    <h1 class="center" style="margin:1rem 0 .4rem">ใบเสร็จรับเงิน ค่าสมัครเข้าเรียน</h1>
    <div class="line">ได้รับเงินจาก <span class="fill w left">{{ $a->parent_name }}</span> ผู้ปกครองของ <span class="fill w left">{{ $a->fullName() }}</span></div>
    <div class="line">เลขที่ใบสมัคร <span class="fill">{{ $a->app_no }}</span> สมัครเข้าเรียนชั้น <span class="fill">{{ $a->level }}</span> ปีการศึกษา <span class="fill">{{ $a->year }}</span></div>
    <table class="items">
        <thead><tr><th style="width:12mm">ที่</th><th>รายการ</th><th style="width:40mm" class="right">จำนวนเงิน (บาท)</th></tr></thead>
        <tbody>
            <tr><td class="center">1</td><td>ค่าสมัครเข้าเรียน ชั้น {{ $a->level }} ปีการศึกษา {{ $a->year }}</td><td class="right">{{ baht($a->fee_amount) }}</td></tr>
            <tr><th colspan="2" class="right">รวมทั้งสิ้น ({{ \App\Support\Thai::bahtText($a->fee_amount) }})</th><th class="right">{{ baht($a->fee_amount) }}</th></tr>
        </tbody>
    </table>
    <div class="small">ชำระโดย: {{ $a->fee_slip ? 'โอนเงิน/พร้อมเพย์ (ตรวจสลิปแล้ว)' : 'เงินสด' }}</div>
    <div class="right"><div class="sign">ลงชื่อ <span class="fill w"></span> ผู้รับเงิน<br>( <span class="fill w">{{ $a->feeVerifier?->name }}</span> )</div></div>
    <div class="small muted" style="margin-top:1rem">เอกสารนี้ออกจากระบบรับสมัครออนไลน์ของ{{ school('school_name') }}</div>
</div>
@endsection
