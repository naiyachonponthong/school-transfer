@extends('layouts.app')
@section('title', 'เมนูทั้งหมด')

@section('content')
<x-page-banner title="เมนูทั้งหมด" subtitle="ทุกฟีเจอร์ของระบบในที่เดียว" eyebrow="EXPLORE YOUR SCHOOL" />

@foreach (\App\Support\Menu::groups(auth()->user(), $navPendingLeaves) as $group => $items)
    <div class="card mb-3 menu-group">
        <div class="card-body">
            <div class="card-title-sm">{{ $group }}</div>
            <div class="app-grid">
                @foreach ($items as $item)<x-app-tile :item="$item" />@endforeach
            </div>
        </div>
    </div>
@endforeach

<div class="card d-lg-none">
    <div class="list-group list-group-flush">
        <a href="{{ route('profile') }}" class="list-group-item list-group-item-action py-3"><i class="bi bi-person me-2"></i> ข้อมูลส่วนตัว / เปลี่ยนรหัสผ่าน</a>
        <form method="POST" action="{{ route('logout') }}">@csrf
            <button class="list-group-item list-group-item-action py-3 text-danger"><i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ</button>
        </form>
    </div>
</div>
@endsection
