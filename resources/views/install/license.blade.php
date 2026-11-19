@extends('install.layout', ['title' => 'License | ติดตั้ง ระบบบริหารโรงเรียน'])

@section('content')
@php($mode = old('mode', $current ? 'keep' : ($skipped ? 'skip' : 'trial')))
<div class="inst-head">
    <div class="eyebrow">ขั้นตอนที่ 2 จาก 5</div>
    <h1>เปิดใช้งาน License</h1>
    <p>กรอกคีย์ที่ได้รับจากผู้พัฒนา หรือเริ่มทดลองใช้ {{ config('license.trial_days') }} วันก่อนก็ได้ ระบบจะผูกสิทธิ์ไว้กับโดเมนนี้</p>
</div>

@error('license_key')
    <div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i><div>{{ $message }}</div></div>
@enderror

@if ($current)
    <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div>
        เปิดใช้งานแล้ว: {{ ! empty($current['trial']) ? 'ทดลองใช้ '.config('license.trial_days').' วัน' : ($current['plan'] ?? 'License') }}
        กด บันทึกและไปต่อ เพื่อทำขั้นถัดไป หรือเลือกใหม่ด้านล่าง
    </div></div>
@endif

<div class="inst-card">
    <h2><i class="bi bi-globe2"></i>โดเมนที่จะผูก License</h2>
    <p class="mb-0"><b class="font-monospace">{{ $domain }}</b> <span class="text-muted small">ถ้าจะย้ายไปโดเมนจริงภายหลัง ให้แจ้งผู้พัฒนาปลดโดเมนเดิมก่อน</span></p>
</div>

<form method="POST" action="{{ route('install.license.save') }}" id="licForm" data-busy="กำลังติดต่อศูนย์ License" novalidate>
    @csrf
    <div class="inst-card">
        <h2><i class="bi bi-key"></i>เลือกวิธีเปิดใช้งาน</h2>
        <div class="choice-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
            @if ($current)
                <label class="choice"><input type="radio" name="mode" value="keep" @checked($mode === 'keep')><span class="ic"><i class="bi bi-check2-circle"></i></span>
                    <span><b>ใช้ที่เปิดไว้แล้ว</b><small>ไม่ต้องติดต่อศูนย์ License ซ้ำ</small></span></label>
            @endif
            <label class="choice"><input type="radio" name="mode" value="key" @checked($mode === 'key')><span class="ic"><i class="bi bi-patch-check"></i></span>
                <span><b>มีคีย์แล้ว</b><small>กรอกคีย์ที่ซื้อจากผู้พัฒนา ใช้ได้ตามอายุของคีย์</small></span></label>
            <label class="choice"><input type="radio" name="mode" value="trial" @checked($mode === 'trial')><span class="ic"><i class="bi bi-hourglass-split"></i></span>
                <span><b>ทดลองใช้ {{ config('license.trial_days') }} วัน</b><small>เริ่มได้ทันที 1 โดเมนขอได้ครั้งเดียว ติดตั้งใหม่ไม่ได้เวลาเพิ่ม</small></span></label>
            <label class="choice"><input type="radio" name="mode" value="skip" @checked($mode === 'skip')><span class="ic"><i class="bi bi-skip-forward"></i></span>
                <span><b>ข้ามไว้ก่อน</b><small>ติดตั้งได้ แต่ระบบจะล็อกจนกว่าผู้ดูแลระบบจะเปิดใช้งานที่ ตั้งค่า &gt; License</small></span></label>
        </div>

        <div class="mt-3 {{ $mode === 'key' ? '' : 'd-none' }}" id="keyField">
            <label class="form-label req" for="license_key">คีย์ License</label>
            <input type="text" id="license_key" name="license_key" class="form-control text-uppercase font-monospace @error('license_key') is-invalid @enderror"
                   value="{{ old('license_key') }}" placeholder="{{ config('license.prefix') }}-XXXX-XXXX-XXXX" maxlength="50" autocomplete="off">
            <div class="form-text">ระบบจะติดต่อศูนย์ License เพื่อตรวจสอบทันที</div>
        </div>
        <div class="alert alert-warning small mt-3 mb-0 {{ $mode === 'skip' ? '' : 'd-none' }}" id="skipNote"><i class="bi bi-exclamation-triangle-fill"></i>
            <div>ติดตั้งเสร็จแล้วระบบจะล็อกไว้ก่อน เข้าสู่ระบบด้วยบัญชีผู้ดูแลระบบแล้วไปที่ ตั้งค่า &gt; License เพื่อเปิดใช้งาน เหมาะกับโฮสต์ที่ยังต่ออินเทอร์เน็ตออกไม่ได้</div>
        </div>
    </div>

    <div class="inst-actions">
        <a href="{{ route('install.index') }}" class="btn btn-light btn-lg"><i class="bi bi-arrow-left"></i>ย้อนกลับ</a>
        <button type="submit" class="btn btn-primary btn-lg">บันทึกและไปต่อ<i class="bi bi-arrow-right"></i></button>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
  const f = document.getElementById('licForm');
  const sync = () => {
    const m = f.querySelector('[name=mode]:checked')?.value;
    document.getElementById('keyField').classList.toggle('d-none', m !== 'key');
    document.getElementById('skipNote').classList.toggle('d-none', m !== 'skip');
  };
  f.addEventListener('change', (e) => { if (e.target.name === 'mode') sync(); });
})();
</script>
@endpush
