@extends('layouts.app')
@section('title', 'ตั้งค่า')

@push('head')
<link rel="stylesheet" href="{{ asset('assets/css/settings.css') }}?v={{ @filemtime(public_path('assets/css/settings.css')) }}">
@endpush

@section('content')
@php
    $currentGroup = collect($groups)->first(fn ($g) => in_array($section, $g[3], true)) ?? $groups[0];
    [$secLabel, $secIcon, , $secLink] = $sections[$section];
@endphp
<section class="settings-hero">
    <div>
        <span class="settings-eyebrow">{{ mb_strtoupper(school('school_short') ?: 'SCHOOL') }} / SETTINGS</span>
        <h1>ตั้งค่าโรงเรียน<br>ให้พร้อมใช้ในที่เดียว</h1>
        <p>เลือกหมวดที่ต้องการ แล้วปรับระบบให้เหมาะกับโรงเรียนของคุณ</p>
    </div>
    <img src="{{ \App\Support\Artwork::url('settings') }}" alt="" aria-hidden="true" width="280" height="220">
</section>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h2 class="fs-5 fw-bold mb-0">เลือกหมวดการตั้งค่า</h2>
    <label class="settings-search"><i class="bi bi-search"></i><input type="search" id="settingsSearch" placeholder="ค้นหาการตั้งค่า เช่น LINE, เกรด, โลโก้" aria-label="ค้นหาการตั้งค่า"></label>
</div>

<div id="settingsResults" class="card mb-3" hidden><div class="card-body d-flex gap-2 flex-wrap">
    @foreach ($sections as $key => [$label, $icon, $words])
        <a class="btn btn-soft settings-result" data-search="{{ $label }} {{ $words }}" href="{{ \App\Support\SettingsMenu::url($key) }}"><i class="bi bi-{{ $icon }}"></i>{{ $label }}</a>
    @endforeach
    <span id="settingsNoResults" class="text-muted align-self-center" hidden>ไม่พบการตั้งค่าที่ค้นหา</span>
</div></div>

<nav class="settings-groups" aria-label="หมวดการตั้งค่า">
    @foreach ($groups as [$gLabel, $gDesc, $gIcon, $gKeys])
        <a class="settings-group {{ in_array($section, $gKeys, true) ? 'active' : '' }}" href="{{ \App\Support\SettingsMenu::url($gKeys[0]) }}">
            <span class="settings-group-icon"><i class="bi bi-{{ $gIcon }}"></i></span>
            <strong>{{ $gLabel }}</strong>
            <span>{{ $gDesc }}</span>
            <small>{{ count($gKeys) }} รายการ <i class="bi bi-arrow-up-right"></i></small>
        </a>
    @endforeach
</nav>

<section class="settings-workspace" id="workspace">
    <div class="mb-3"><span class="settings-eyebrow text-muted">กำลังจัดการ</span><h2 class="fs-5 fw-bold mt-1 mb-0">{{ $currentGroup[0] }}</h2></div>
    <nav class="settings-subnav mb-4" aria-label="รายการการตั้งค่า">
        @foreach ($currentGroup[3] as $key)
            <a class="{{ $section === $key ? 'active' : '' }}" href="{{ \App\Support\SettingsMenu::url($key) }}" @if ($section === $key) aria-current="page" @endif><i class="bi bi-{{ $sections[$key][1] }}"></i>{{ $sections[$key][0] }}</a>
        @endforeach
    </nav>

    @if ($secLink)
        @include('settings.sections.link', ['label' => $secLabel, 'icon' => $secIcon, 'route' => $secLink, 'key' => $section])
    @else
        @include('settings.sections.'.$section)
    @endif
</section>
@endsection

@push('scripts')
<script>
document.getElementById('settingsSearch').addEventListener('input', function () {
    const q = this.value.trim().toLocaleLowerCase(); let count = 0;
    document.getElementById('settingsResults').hidden = !q;
    document.querySelectorAll('.settings-result').forEach((link) => { const show = link.dataset.search.toLocaleLowerCase().includes(q); link.hidden = !show; if (show) count++; });
    document.getElementById('settingsNoResults').hidden = count > 0;
});
</script>
@endpush
