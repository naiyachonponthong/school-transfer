{{-- แถบขั้นตอน: ขั้นที่ผ่านแล้วกดย้อนไปแก้ได้ --}}
@php($all = array_merge($steps, ['review']))
<ol class="apply-steps mb-3">
    @foreach ($all as $i => $s)
        @php($done = in_array($s, $a->steps_done ?? [], true))
        @php($label = $s === 'review' ? 'ตรวจสอบและยืนยัน' : \App\Support\AdmissionForm::STEPS[$s][0])
        <li class="{{ $s === $current ? 'cur' : ($done ? 'done' : '') }}">
            <a href="{{ route('apply.step', $s) }}" @if($s === $current) aria-current="step" @endif>
                <span class="n">@if ($done && $s !== $current)<i class="bi bi-check-lg"></i>@else{{ $i + 1 }}@endif</span>
                <span class="t">{{ $label }}</span>
            </a>
        </li>
    @endforeach
</ol>
