@props(['name' => 'photo', 'current' => null, 'hint' => 'แตะเพื่อถ่ายรูป / เลือกรูป'])
{{-- กล่องเลือกรูปพร้อมพรีวิว (สคริปต์ใน app.js) · มือถือเปิดกล้องหลังได้ทันที --}}
<label {{ $attributes->merge(['class' => 'photo-drop']) }}>
    @if ($current)<img src="{{ $current }}" alt="">@endif
    <div class="hint {{ $current ? 'd-none' : '' }}"><i class="bi bi-camera"></i>{{ $hint }}<div class="small mt-1">JPG / PNG ไม่เกิน 8 MB</div></div>
    <input type="file" name="{{ $name }}" accept="image/*" capture="environment">
</label>
@error($name)<div class="text-danger small mt-1">{{ $message }}</div>@enderror
