<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'หน้าหลัก') · {{ school('school_short') ?: school('school_name') }}</title>
    @include('partials.assets')
    @stack('head')
</head>
<body class="@yield('body_class')">
@php($u = auth()->user())

<aside class="sb-rail">
    <a href="{{ route('home') }}" class="rail-brand" title="{{ school('school_name') }}">
        <span class="sb-logo">@if (school('logo'))<img src="{{ asset('storage/'.school('logo')) }}" alt="">@else<i class="bi bi-mortarboard-fill"></i>@endif</span>
    </a>
    <nav class="rail-scroll">
        @foreach (\App\Support\Menu::rail($u, $navPendingLeaves) as $item)
            <a href="{{ $item['url'] }}" class="rail-item {{ \App\Support\Menu::isActive($item) ? 'active' : '' }}" title="{{ $item['label'] }}">
                <span class="ri"><i class="bi {{ $item['icon'] }}"></i></span>
                <span>{{ $item['label'] }}</span>
                @if ($item['badge'])<span class="rb">{{ $item['badge'] }}</span>@endif
            </a>
        @endforeach
    </nav>
    <div class="rail-bottom">
        @if ($u->hasPermission('settings.manage'))
            <a href="{{ route('settings') }}" class="rail-item {{ request()->routeIs('settings*') ? 'active' : '' }}" title="ตั้งค่า"><span class="ri"><i class="bi bi-gear"></i></span><span>ตั้งค่า</span></a>
        @endif
        <a href="{{ route('profile') }}" class="rail-item {{ request()->routeIs('profile') ? 'active' : '' }}" title="บัญชีของฉัน">
            <span class="ri"><span class="sb-avatar xs">@if($u->avatarUrl())<img src="{{ $u->avatarUrl() }}" alt="">@else{{ $u->initials() }}@endif</span></span><span>บัญชี</span>
        </a>
    </div>
</aside>

<div class="sb-main">
    @if ($demoRole = \App\Support\Demo::role())
        {{-- โหมดทดลองใช้: แถบบางติดขอบบน ไม่ดันเนื้อหาของหน้า --}}
        <div class="sb-demo-bar no-print" role="status">
            <i class="bi bi-play-circle-fill"></i>
            <span class="txt"><b>โหมดทดลองใช้</b> · {{ \App\Support\Demo::ROLES[$demoRole][0] }}{{ $demoRole === 'exec' ? ' (ดูได้อย่างเดียว)' : '' }}<span class="more"> · ข้อมูลทั้งหมดเป็นข้อมูลตัวอย่าง{{ \App\Support\Settings::get('demo_reset') === '1' ? ' และถูกคืนค่าทุกคืน' : '' }}</span></span>
            <form method="POST" action="{{ route('logout') }}">@csrf<button>ออกจากโหมดทดลอง</button></form>
        </div>
    @endif
    <header class="sb-topbar">
        <a href="{{ route('home') }}" class="d-lg-none"><span class="sb-brand-logo">@if (school('logo'))<img src="{{ asset('storage/'.school('logo')) }}" alt="">@else<i class="bi bi-mortarboard-fill"></i>@endif</span></a>
        <div class="school text-truncate">
            {{ school('school_short') ?: school('school_name') }}
            <small>{{ $currentTerm ? $currentTerm->label() : 'ยังไม่ตั้งภาคเรียน' }}</small>
        </div>
        <div class="ms-auto d-flex align-items-center gap-1">
            @if ($u->isStaff())
                <button type="button" class="sb-search-btn" data-search-open title="ค้นหา (Ctrl K)">
                    <i class="bi bi-search"></i><span>ค้นหานักเรียน ครู...</span><kbd>Ctrl K</kbd>
                </button>
            @endif
            @unless ($u->isStudent())
                @php($chatUnread = \App\Models\Conversation::unreadTotal($u))
                <a href="{{ route('chat.index') }}" class="icon-btn" title="ข้อความ">
                    <i class="bi bi-chat-dots"></i>@if ($chatUnread)<span class="dot">{{ $chatUnread }}</span>@endif
                </a>
            @endunless
            @php($helpTopic = \App\Support\Manual::forRoute(request()->route()?->getName(), $u))
            <a href="{{ $helpTopic ? route('manual.show', $helpTopic) : route('manual.index') }}" class="icon-btn" title="{{ $helpTopic ? 'คู่มือของหน้านี้' : 'คู่มือการใช้งาน' }}" data-manual-help>
                <i class="bi bi-question-circle"></i>
            </a>
            <a href="{{ route('notifications') }}" class="icon-btn" title="แจ้งเตือน">
                <i class="bi bi-bell"></i>@if ($navUnread)<span class="dot">{{ $navUnread > 99 ? '99+' : $navUnread }}</span>@endif
            </a>
            <div class="dropdown">
                <button class="btn btn-link text-decoration-none d-flex align-items-center gap-2 p-1" data-bs-toggle="dropdown">
                    <span class="sb-avatar sm">@if($u->avatarUrl())<img src="{{ $u->avatarUrl() }}" alt="">@else{{ $u->initials() }}@endif</span>
                    <span class="d-none d-md-block text-start lh-sm">
                        <span class="d-block text-body fw-semibold small">{{ $u->name }}</span>
                        <span class="d-block text-muted" style="font-size:.74rem">{{ $u->position ?: $u->roleLabel() }}</span>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('profile') }}"><i class="bi bi-person me-2"></i>ข้อมูลส่วนตัว / รหัสผ่าน</a></li>
                    <li><a class="dropdown-item" href="{{ route('menu') }}"><i class="bi bi-grid-3x3-gap me-2"></i>เมนูทั้งหมด</a></li>
                    <li><a class="dropdown-item" href="{{ route('manual.index') }}"><i class="bi bi-book me-2"></i>คู่มือการใช้งาน</a></li>
                    <li class="d-none" data-install-app-item><button type="button" class="dropdown-item" data-install-app><i class="bi bi-download me-2"></i>ติดตั้งแอป</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <main class="sb-content">
        @include('partials.flash')
        @yield('content')
    </main>
</div>

@include('partials.bottom-nav')

@if ($u->isStaff())
<div class="modal fade" id="searchModal" tabindex="-1" data-url="{{ route('search') }}">
    <div class="modal-dialog modal-dialog-scrollable" style="margin-top:10vh">
        <div class="modal-content">
            <div class="d-flex align-items-center border-bottom px-3">
                <i class="bi bi-search text-muted"></i>
                <input type="search" class="form-control search-input" placeholder="พิมพ์ชื่อ ชื่อเล่น รหัสนักเรียน หรือเลขบัตร..." autocomplete="off">
                <kbd class="bg-light text-muted border small">Esc</kbd>
            </div>
            <div id="searchResults" class="modal-body p-0" style="max-height:60vh"></div>
            <div class="px-3 py-2 small text-muted border-top bg-light">
                <i class="bi bi-lightbulb"></i> กด <kbd class="bg-white text-muted border">/</kbd> หรือ <kbd class="bg-white text-muted border">Ctrl K</kbd> ได้จากทุกหน้า · ใช้ลูกศร ↑ ↓ แล้ว Enter
            </div>
        </div>
    </div>
</div>
@endif

<script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
