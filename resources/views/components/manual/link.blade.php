@props(['to'])
{{-- ลิงก์ไปหัวข้ออื่นในคู่มือ --}}
<a href="{{ route('manual.show', $to) }}" class="manual-link"><i class="bi bi-book"></i> {{ $slot->isEmpty() ? (\App\Support\Manual::TOPICS[$to][0] ?? $to) : $slot }}</a>
