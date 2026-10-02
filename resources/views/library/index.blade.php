@extends('layouts.app')
@section('title', 'ทะเบียนหนังสือ')

@php use App\Support\Dewey; @endphp

@section('content')
<x-page-banner title="ทะเบียนหนังสือ" :subtitle="number_format($stats['titles']).' ชื่อเรื่อง · '.number_format($stats['copies']).' เล่ม · จัดหมวดหมู่ระบบทศนิยมของดิวอี้ (DDC)'" eyebrow="SCHOOL LIBRARY">
    <a href="{{ route('library.loans') }}" class="btn btn-light border"><i class="bi bi-arrow-left-right"></i> ยืม-คืน</a>
    <a href="{{ route('library.labels') }}" class="btn btn-light border"><i class="bi bi-printer"></i> พิมพ์ป้าย @if ($stats['labels'])<span class="badge bg-danger">{{ $stats['labels'] }}</span>@endif</a>
    <a href="{{ route('library.create') }}" class="btn btn-light"><i class="bi bi-plus-lg"></i> ลงทะเบียนหนังสือ</a>
    <div class="dropdown">
        <button class="btn btn-light border dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-gear"></i></button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ route('library.numbering', 'library-barcode') }}"><i class="bi bi-upc me-2"></i>รูปแบบบาร์โค้ด</a></li>
            <li><a class="dropdown-item" href="{{ route('library.numbering', 'library-accession') }}"><i class="bi bi-123 me-2"></i>รูปแบบเลขทะเบียน</a></li>
            @if ($stats['noAccession'])
                <li><hr class="dropdown-divider"></li>
                <li><form method="POST" action="{{ route('library.accession') }}" data-confirm="ออกเลขทะเบียนให้ {{ $stats['noAccession'] }} เล่มที่ยังไม่มี (เรียงตามลำดับที่ลงระบบ)?">@csrf
                    <button class="dropdown-item"><i class="bi bi-magic me-2"></i>ออกเลขทะเบียนให้เล่มที่ยังไม่มี ({{ $stats['noAccession'] }})</button></form></li>
            @endif
        </ul>
    </div>
</x-page-banner>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-primary"><i class="bi bi-book"></i></div><div><div class="stat-value">{{ number_format($stats['titles']) }}</div><div class="stat-label">ชื่อเรื่อง</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-teal"><i class="bi bi-stack"></i></div><div><div class="stat-value">{{ number_format($stats['copies']) }}</div><div class="stat-label">เล่มทั้งหมด</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-info"><i class="bi bi-box-arrow-up-right"></i></div><div><div class="stat-value">{{ $stats['out'] }}</div><div class="stat-label">กำลังถูกยืม</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-danger"><i class="bi bi-alarm"></i></div><div><div class="stat-value">{{ $stats['overdue'] }}</div><div class="stat-label">เกินกำหนด</div></div></div></div></div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="d-flex flex-wrap gap-2 mb-3">
            <input type="hidden" name="class" value="{{ request('class') }}"><input type="hidden" name="collection" value="{{ request('collection') }}">
            <input name="q" value="{{ request('q') }}" class="form-control flex-grow-1" style="max-width:420px" placeholder="ชื่อเรื่อง ผู้แต่ง ISBN เลขหมู่ หัวเรื่อง หรือสแกนบาร์โค้ด/เลขทะเบียน" autofocus>
            <select name="sort" class="form-select w-auto" data-autosubmit><option value="">เรียงตามชื่อเรื่อง</option><option value="call" @selected(request('sort') === 'call')>เรียงตามเลขหมู่ (ลำดับบนชั้น)</option></select>
            <button class="btn btn-primary"><i class="bi bi-search"></i></button>
        </form>
        <div class="d-flex flex-wrap gap-1">
            <a href="{{ route('library.index', request()->only('q', 'sort')) }}" class="btn btn-sm {{ ! request('class') && ! request('collection') ? 'btn-dark' : 'btn-light border' }}">ทั้งหมด</a>
            @foreach (Dewey::CLASSES as $c => $label)
                <a href="{{ route('library.index', ['class' => $c] + request()->only('q', 'sort')) }}" title="{{ $label }}"
                   class="btn btn-sm {{ request('class') === $c ? 'btn-dark' : 'btn-light border' }}"><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:{{ Dewey::COLORS[$c] }}"></span>{{ $c }} <span class="d-none d-xl-inline">{{ \Illuminate\Support\Str::limit($label, 14) }}</span> <span class="text-muted">{{ $byClass[$c] ?? 0 }}</span></a>
            @endforeach
            @foreach (Dewey::COLLECTIONS as $k => [$label, $symbol])
                @continue($k === 'general')
                <a href="{{ route('library.index', ['collection' => $k] + request()->only('q', 'sort')) }}" class="btn btn-sm {{ request('collection') === $k ? 'btn-dark' : 'btn-light border' }}">{{ $symbol }} {{ \Illuminate\Support\Str::before($label, ' (') }} <span class="text-muted">{{ $byCollection[$k] ?? 0 }}</span></a>
            @endforeach
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-cards table-hover align-middle mb-0">
            <thead><tr><th style="width:56px"></th><th>ชื่อเรื่อง / ผู้แต่ง</th><th>เลขเรียก</th><th>หมวด</th><th class="d-none d-lg-table-cell">ISBN</th><th class="text-center">ว่าง/ทั้งหมด</th></tr></thead>
            <tbody>
            @forelse ($books as $b)
                <tr data-href="{{ route('library.show', $b) }}" style="cursor:pointer">
                    <td class="tc-hide"><span class="media-thumb" style="width:42px;height:56px">@if($b->coverUrl())<img src="{{ $b->coverUrl() }}" alt="">@else<i class="bi bi-book"></i>@endif</span></td>
                    <td class="tc-title"><a href="{{ route('library.show', $b) }}" class="fw-semibold text-body text-decoration-none">{{ $b->title }}{{ $b->volume ? ' ล.'.preg_replace('/^ล\.?\s*/u', '', $b->volume) : '' }}</a>
                        <div class="small text-muted">{{ $b->author }}{{ $b->pub_year ? ' · '.$b->pub_year : '' }}</div></td>
                    <td class="font-monospace small fw-semibold">{{ $b->callNumberText() ?: '-' }}</td>
                    <td class="small"><span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:{{ Dewey::COLORS[$b->mainClass()] ?? '#d1d5db' }}"></span>{{ $b->category ?: '-' }}</td>
                    <td class="small font-monospace d-none d-lg-table-cell">{{ $b->isbn ?: '-' }}</td>
                    <td class="text-center"><span class="badge bg-{{ $b->available() ? 'success' : 'secondary' }}">{{ $b->available() }}/{{ $b->circulating_count }}</span>
                        @unless ($b->loanable())<div class="small text-muted">อ่านในห้องสมุด</div>@endunless</td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><i class="bi bi-book"></i>ไม่พบหนังสือ<div class="mt-2"><a href="{{ route('library.create') }}" class="btn btn-primary btn-sm">ลงทะเบียนหนังสือ</a></div></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $books->links() }}</div>
@endsection
