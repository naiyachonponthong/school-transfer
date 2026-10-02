@props(['grade' => null, 'original' => null])
{{-- ป้ายเกรด: ถ้ามีผลแก้ตัว แสดงผลเดิมขีดฆ่าไว้ข้างหน้า เช่น ~~0~~ 1 --}}
@if ($grade === null)
    <span {{ $attributes->merge(['class' => 'grade-badge bg-light text-muted']) }}>-</span>
@else
    @if ($original !== null && $original !== $grade)<span class="small text-muted text-decoration-line-through me-1" title="ผลก่อนแก้ตัว">{{ $original }}</span>@endif<span {{ $attributes->merge(['class' => 'grade-badge bg-'.\App\Support\Grade::color($grade).'-subtle text-'.\App\Support\Grade::color($grade).'-emphasis']) }}>{{ $grade }}</span>
@endif
