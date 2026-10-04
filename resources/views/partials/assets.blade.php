<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
<link rel="manifest" href="{{ route('app.manifest') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('assets/icons/icon-192.png') }}?v={{ filemtime(public_path('assets/icons/icon-192.png')) }}">
<link rel="apple-touch-icon" href="{{ asset('assets/icons/apple-touch-icon.png') }}?v={{ filemtime(public_path('assets/icons/apple-touch-icon.png')) }}">
<meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ school('school_short') ?: school('school_name') }}">
{{-- สีธีมที่ตั้งค่าไว้ ต้องมาหลัง app.css --}}
<style id="theme-vars">:root{ {!! \App\Support\Theme::css() !!} }</style>
<meta name="theme-color" content="{{ \App\Support\Theme::color() }}">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="{{ asset('assets/js/install-app.js') }}?v={{ filemtime(public_path('assets/js/install-app.js')) }}" defer></script>
