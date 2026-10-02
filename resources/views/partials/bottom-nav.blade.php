{{-- แถบเมนูล่างมือถือ: ปุ่มกลางเปิดเมนูทั้งหมดแบบตารางไอคอน --}}
@php($on = fn (...$r) => request()->routeIs(...$r) ? 'active' : '')
<nav class="sb-bottom-nav">
    @if ($u->isParent())
        <a href="{{ route('parent.home') }}" class="{{ $on('parent.home') }}"><i class="bi bi-house-heart"></i>หน้าหลัก</a>
        <a href="{{ route('feed.index') }}" class="{{ $on('feed.*') }}"><i class="bi bi-newspaper"></i>ฟีดข่าว</a>
        <a href="{{ route('menu') }}" class="center {{ $on('menu') }}"><i class="bi bi-grid-3x3-gap-fill"></i>เมนู</a>
        <a href="{{ route('notifications') }}" class="{{ $on('notifications') }}"><i class="bi bi-bell"></i>แจ้งเตือน
            @if ($navUnread)<span class="dot">{{ $navUnread }}</span>@endif</a>
        <a href="{{ route('profile') }}" class="{{ $on('profile') }}"><i class="bi bi-person-circle"></i>บัญชี</a>
    @elseif ($u->isStudent())
        <a href="{{ route('student.home') }}" class="{{ $on('student.home') }}"><i class="bi bi-house"></i>หน้าหลัก</a>
        <a href="{{ route('student.homework') }}" class="{{ $on('student.homework') }}"><i class="bi bi-journal-text"></i>การบ้าน</a>
        <a href="{{ route('menu') }}" class="center {{ $on('menu') }}"><i class="bi bi-grid-3x3-gap-fill"></i>เมนู</a>
        <a href="{{ route('notifications') }}" class="{{ $on('notifications') }}"><i class="bi bi-bell"></i>แจ้งเตือน
            @if ($navUnread)<span class="dot">{{ $navUnread }}</span>@endif</a>
        <a href="{{ route('profile') }}" class="{{ $on('profile') }}"><i class="bi bi-person-circle"></i>บัญชี</a>
    @else
        <a href="{{ route('home') }}" class="{{ $on('home') }}"><i class="bi bi-grid-1x2"></i>หน้าหลัก</a>
        <a href="{{ route('attendance.index') }}" class="{{ $on('attendance.index') }}"><i class="bi bi-check2-square"></i>เช็คชื่อ</a>
        <a href="{{ route('menu') }}" class="center {{ $on('menu') }}"><i class="bi bi-grid-3x3-gap-fill"></i>เมนู</a>
        <a href="{{ route('notifications') }}" class="{{ $on('notifications') }}"><i class="bi bi-bell"></i>แจ้งเตือน
            @if ($navUnread)<span class="dot">{{ $navUnread }}</span>@endif</a>
        <a href="{{ route('profile') }}" class="{{ $on('profile') }}"><i class="bi bi-person-circle"></i>บัญชี</a>
    @endif
</nav>
