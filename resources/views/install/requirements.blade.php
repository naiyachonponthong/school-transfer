@extends('install.layout', ['title' => 'ตรวจเครื่อง | ติดตั้ง ระบบบริหารโรงเรียน'])

@section('content')
@php
    $bad = collect($groups)->flatten(1)->where('required', true)->where('ok', false)->count();
@endphp
<div class="inst-head">
    <div class="eyebrow">ขั้นตอนที่ 1 จาก 4</div>
    <h1>ตรวจความพร้อมของโฮสต์</h1>
    <p>ระบบตรวจให้อัตโนมัติ รายการสีแดงต้องแก้ก่อนไปต่อ สีเหลืองเป็นคำแนะนำ</p>
</div>

@if ($ok)
    <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div><b>โฮสต์พร้อมติดตั้ง</b> กดปุ่มด้านล่างเพื่อตั้งค่าฐานข้อมูล</div></div>
@else
    <div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i><div><b>ยังมี {{ $bad }} รายการที่ต้องแก้</b> ส่วนใหญ่แก้ได้เองใน cPanel เมนู Select PHP Version (เลือกเวอร์ชันและติ๊ก Extension) และ File Manager (ตั้ง Permissions) แก้แล้วกด ตรวจอีกครั้ง</div></div>
@endif

@foreach ($groups as $name => $items)
    <div class="inst-card">
        <h2><i class="bi bi-{{ ['PHP' => 'filetype-php', 'Extension' => 'puzzle', 'สิทธิ์เขียนไฟล์' => 'folder-check', 'ค่าในโฮสต์' => 'sliders'][$name] ?? 'check2' }}"></i>{{ $name }}</h2>
        @if ($name === 'Extension')
            <div class="chip-grid2">
                @foreach ($items as $i)
                    <span class="chip2 {{ $i['ok'] ? '' : ($i['required'] ? 'bad' : 'warn') }}" title="{{ $i['note'] }}"><i class="bi bi-{{ $i['ok'] ? 'check-circle-fill' : ($i['required'] ? 'x-circle-fill' : 'exclamation-circle-fill') }}"></i>{{ $i['label'] }}</span>
                @endforeach
            </div>
            @if (collect($items)->where('ok', false)->count())
                <div class="small text-muted mt-2">ที่ยังไม่เปิด: {{ collect($items)->where('ok', false)->map(fn ($i) => $i['label'].($i['required'] ? '' : ' (แนะนำ)'))->join(', ') }}</div>
            @endif
        @else
            @foreach ($items as $i)
                <div class="req-row">
                    <i class="bi bi-{{ $i['ok'] ? 'check-circle-fill ok-ico' : ($i['required'] ? 'x-circle-fill bad-ico' : 'exclamation-circle-fill warn-ico') }}"></i>
                    <span class="t">{{ $i['label'] }}</span>
                    <span class="s">{{ $i['note'] }}</span>
                </div>
            @endforeach
        @endif
    </div>
@endforeach

<div class="inst-actions">
    <a href="{{ route('install.index') }}" class="btn btn-light btn-lg"><i class="bi bi-arrow-repeat"></i>ตรวจอีกครั้ง</a>
    @if ($ok)
        <a href="{{ route('install.database') }}" class="btn btn-primary btn-lg" id="goDb">ไปต่อ: ตั้งค่าฐานข้อมูล <i class="bi bi-arrow-right"></i></a>
    @else
        <button class="btn btn-primary btn-lg" disabled>แก้รายการสีแดงก่อน</button>
    @endif
</div>
@endsection
