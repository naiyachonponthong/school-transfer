@extends('layouts.app')
@section('title', $topic[0].' · คู่มือ')

@php use App\Support\Manual; @endphp

@section('content')
<div class="page-head">
    <div>
        <div class="small text-muted mb-1"><a href="{{ route('manual.index') }}" class="text-decoration-none">คู่มือการใช้งาน</a> › {{ $topic[2] }}</div>
        <h1><i class="bi {{ $topic[1] }} text-primary"></i> {{ $topic[0] }}</h1>
        <div class="sub">{{ $topic[4] }}</div>
    </div>
    <div class="actions">
        <a href="{{ route('manual.print', ['topic' => $key]) }}" target="_blank" class="btn btn-light border"><i class="bi bi-printer"></i> พิมพ์หัวข้อนี้</a>
        <a href="{{ route('manual.index') }}" class="btn btn-light border"><i class="bi bi-grid"></i> สารบัญ</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-3 d-none d-lg-block">
        <nav class="card side-sticky manual-toc" style="max-height:calc(100vh - 110px);overflow:auto">
            <div class="card-body p-2">
                <form method="GET" action="{{ route('manual.index') }}" class="p-1"><input name="q" class="form-control form-control-sm" placeholder="ค้นหาในคู่มือ"></form>
                @foreach (collect($topics)->groupBy(fn ($t) => $t[2], true) as $group => $items)
                    <div class="grp">{{ $group }}</div>
                    @foreach ($items as $k => $t)
                        <a href="{{ route('manual.show', $k) }}" class="{{ $k === $key ? 'active' : '' }}"><i class="bi {{ $t[1] }} me-1"></i>{{ $t[0] }}</a>
                    @endforeach
                @endforeach
            </div>
        </nav>
    </div>

    <div class="col-lg-9">
        <div class="card">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-wrap gap-1 mb-2 small">
                    <span class="text-muted me-1">ใช้กับ:</span>
                    @foreach (str_split($topic[3]) as $r)<span class="badge text-bg-light border">{{ Manual::ROLE_LABELS[$r] }}</span>@endforeach
                </div>
                <div class="manual-onpage mb-3 d-none" id="onPage"><div class="small text-muted mb-1">ในหัวข้อนี้</div></div>
                <article class="manual-body" id="manualBody">
                    @include('manual.topics.'.$key)
                </article>
            </div>
            <div class="card-footer bg-transparent d-flex flex-wrap gap-2 justify-content-between">
                @if ($prev)<a href="{{ route('manual.show', $prev) }}" class="btn btn-light border"><i class="bi bi-chevron-left"></i> {{ $topics[$prev][0] }}</a>@else<span></span>@endif
                @if ($next)<a href="{{ route('manual.show', $next) }}" class="btn btn-light border">{{ $topics[$next][0] }} <i class="bi bi-chevron-right"></i></a>@endif
            </div>
        </div>
        <div class="small text-muted mt-2 text-center">ยังไม่เจอคำตอบ? ดู <a href="{{ route('manual.show', 'faq') }}">คำถามที่พบบ่อย</a> หรือติดต่อผู้ดูแลระบบของโรงเรียน</div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// สารบัญในหน้า จากหัวข้อย่อย
(() => {
    const box = document.getElementById('onPage');
    const heads = document.querySelectorAll('#manualBody .manual-section');
    if (heads.length < 2) return;
    heads.forEach((s) => {
        const a = document.createElement('a');
        a.href = '#' + s.id;
        a.textContent = s.querySelector('h2').textContent.replace(/^#/, '');
        box.appendChild(a);
    });
    box.classList.remove('d-none');
})();
</script>
@endpush
