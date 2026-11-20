@extends('layouts.site')
@section('title', 'ITA การประเมินคุณธรรมและความโปร่งใส')

@section('content')
<div class="site-wrap">
    @include('website.partials.pagehead', [
        'title' => 'การเปิดเผยข้อมูลสาธารณะ (OIT)',
        'sub' => $year ? 'การประเมินคุณธรรมและความโปร่งใสในการดำเนินงาน (ITA) ปีงบประมาณ '.$year->year : null,
        'crumbs' => ['ITA' => null],
    ])

    @if (! $year)
        <div class="site-card text-center p-5 mt-3 text-muted"><i class="bi bi-patch-check fs-1 d-block mb-2"></i>ยังไม่มีข้อมูล ITA</div>
    @else
        <div class="ita-bar">
            @if ($years->count() > 1)
                <nav class="ita-years" aria-label="ปีงบประมาณ">
                    @foreach ($years as $y)<a href="{{ route('web.ita', $y->year) }}" class="{{ $y->id === $year->id ? 'active' : '' }}">{{ $y->year }}</a>@endforeach
                </nav>
            @endif
            @if (! $year->is_published)<span class="badge text-bg-warning">ฉบับร่าง เห็นเฉพาะผู้ดูแล</span>@endif
            <input type="search" class="form-control ita-search" placeholder="ค้นหาหัวข้อ เช่น O12 หรือ แผนจัดซื้อ" aria-label="ค้นหาหัวข้อ" data-ita-search>
        </div>
        @if ($year->note)<p class="text-muted">{{ $year->note }}</p>@endif

        @foreach ($year->items->groupBy('group') as $group => $items)
            <section class="site-section ita-group">
                <div class="site-section-head"><h2>{{ $group ?: 'อื่น ๆ' }}</h2></div>
                <div class="site-card ita-list">
                    @foreach ($items as $it)
                        <div class="ita-item" id="{{ $it->code }}" data-ita-text="{{ mb_strtolower($it->code.' '.$it->title) }}">
                            <a href="#{{ $it->code }}" class="ita-code" title="ลิงก์ของข้อนี้">{{ $it->code }}</a>
                            <div class="ita-body">
                                <b>{{ $it->title }}</b>
                                @if ($it->description)<small class="d-block text-muted">{{ $it->description }}</small>@endif
                                @php $links = $it->evidences->filter(fn ($e) => $e->href()); @endphp
                                @forelse ($links as $e)
                                    <a href="{{ $e->href() }}" target="_blank" rel="noopener" class="ita-ev"><i class="bi bi-{{ ['file' => 'file-earmark-text', 'link' => 'link-45deg', 'post' => 'newspaper'][$e->kind] ?? 'link' }}"></i>{{ $e->title }}</a>
                                @empty
                                    <small class="d-block text-muted mt-1">อยู่ระหว่างจัดทำข้อมูล</small>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
  const s = document.querySelector('[data-ita-search]');
  if (!s) return;
  s.addEventListener('input', () => {
    const q = s.value.trim().toLowerCase();
    document.querySelectorAll('.ita-item').forEach((el) => { el.hidden = q !== '' && !el.dataset.itaText.includes(q); });
    document.querySelectorAll('.ita-group').forEach((g) => { g.hidden = !g.querySelector('.ita-item:not([hidden])'); });
  });
  if (location.hash) { const t = document.getElementById(location.hash.slice(1)); if (t) t.classList.add('is-target'); }
})();
</script>
@endpush
