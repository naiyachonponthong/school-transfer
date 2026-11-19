@extends('install.layout', ['title' => 'กำลังติดตั้ง | ติดตั้ง ระบบบริหารโรงเรียน'])

@section('content')
<div class="inst-head">
    <div class="eyebrow">ขั้นตอนที่ 5 จาก 5</div>
    <h1 id="runTitle">กำลังติดตั้ง</h1>
    <p id="runSub">อย่าปิดหน้านี้ ใช้เวลาประมาณ 1 นาที{{ $school['demo'] ? ' (มีข้อมูลตัวอย่าง อาจนานกว่านี้เล็กน้อย)' : '' }}</p>
</div>
<div class="inst-card">
    <div class="inst-progress"><i id="runBar"></i></div>
    <ul class="run-list" id="runList">
        @foreach ($steps as $key => $label)
            <li data-step="{{ $key }}"><span class="st"><i class="bi bi-clock"></i></span><div><b>{{ $label }}</b><span class="msg"></span></div></li>
        @endforeach
    </ul>
</div>
<div class="alert alert-danger d-none" id="runErr"><i class="bi bi-x-circle-fill"></i><div><b>ติดตั้งไม่สำเร็จในขั้นนี้</b> <span id="runErrMsg"></span>
    <div class="mt-2 d-flex gap-2 flex-wrap"><button class="btn btn-sm btn-primary" id="runRetry"><i class="bi bi-arrow-repeat"></i>ลองอีกครั้ง</button><a class="btn btn-sm btn-light" href="{{ route('install.school') }}">แก้ข้อมูลโรงเรียน</a><a class="btn btn-sm btn-light" href="{{ route('install.database') }}">แก้ฐานข้อมูล</a></div></div></div>
@endsection

@push('scripts')
<script>
(function () {
  const items = [...document.querySelectorAll('#runList li')], base = @json(url('install/run'));
  let i = 0;
  const icon = (li, cls, ic) => { li.className = cls; li.querySelector('.st').innerHTML = ic; };
  async function next() {
    if (i >= items.length) return;
    const li = items[i];
    icon(li, 'doing', '<span class="spinner-border spinner-border-sm"></span>');
    document.getElementById('runErr').classList.add('d-none');
    const j = await INST.post(base + '/' + li.dataset.step, {});
    li.querySelector('.msg').textContent = j.message || '';
    if (!j.ok) {
      icon(li, 'err', '<i class="bi bi-x-lg"></i>');
      document.getElementById('runErrMsg').textContent = j.message || '';
      document.getElementById('runErr').classList.remove('d-none');
      if (j.restart) setTimeout(() => location.href = j.restart, 2500);
      return;
    }
    icon(li, 'ok', '<i class="bi bi-check-lg"></i>');
    i++;
    document.getElementById('runBar').style.width = Math.round(i / items.length * 100) + '%';
    if (j.redirect) { document.getElementById('runTitle').textContent = 'ติดตั้งเสร็จแล้ว'; setTimeout(() => location.href = j.redirect, 600); return; }
    next();
  }
  document.getElementById('runRetry').addEventListener('click', next);
  window.addEventListener('beforeunload', (e) => { if (i > 0 && i < items.length) { e.preventDefault(); e.returnValue = ''; } });
  next();
})();
</script>
@endpush
