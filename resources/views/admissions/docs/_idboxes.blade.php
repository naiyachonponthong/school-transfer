{{-- เลขประจำตัวประชาชน 13 ช่อง แบ่งกลุ่ม 1-4-5-2-1 แบบแบบฟอร์มราชการ --}}
@php($d = str_split(str_pad(preg_replace('/\D/', '', (string) ($id ?? '')), 13, ' ')))
<span class="idboxes">
    @foreach ([1, 4, 5, 2, 1] as $g => $n)
        @if ($g)<i></i>@endif
        @foreach (array_splice($d, 0, $n) as $c)<span>{{ trim($c) }}</span>@endforeach
    @endforeach
</span>
