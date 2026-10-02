@extends('layouts.app')
@section('title', 'พิมพ์ป้ายติดเล่ม')

@php
    use App\Http\Controllers\LibraryLabelController as L;
    $q = fn (array $over) => route('library.labels', array_merge(request()->query(), $over));
@endphp

@section('content')
<style>@page { size: A4; margin: 10mm; }</style>
<div class="page-head no-print">
    <div><h1>พิมพ์ป้ายติดเล่ม</h1><div class="sub">ป้ายสันหนังสือ · บาร์โค้ดปกนอก · บาร์โค้ดปกใน — ขนาดจริงบนกระดาษ A4 (สติกเกอร์ A4 หรือกระดาษธรรมดาแล้วตัด)</div></div>
    <div class="actions"><a href="{{ route('library.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> ทะเบียนหนังสือ</a></div>
</div>

<div class="row g-3 no-print mb-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-collection"></i> เล่มที่จะพิมพ์</div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-1 mb-3">
                    <a href="{{ route('library.labels', ['type' => $type]) }}" class="btn btn-sm {{ $source === 'pending' ? 'btn-dark' : 'btn-light border' }}">ยังไม่ได้พิมพ์ ({{ $pendingCount }})</a>
                    <a href="{{ route('library.labels', ['type' => $type, 'source' => 'range']) }}" class="btn btn-sm {{ $source === 'range' ? 'btn-dark' : 'btn-light border' }}">ช่วงเลขทะเบียน</a>
                    @if ($book)<span class="btn btn-sm btn-dark">{{ \Illuminate\Support\Str::limit($book->title, 30) }}</span>@endif
                    @if ($source === 'ids')<span class="btn btn-sm btn-dark">เล่มที่เลือก</span>@endif
                </div>
                @if ($source === 'range')
                    <form method="GET" class="row g-2 mb-2">
                        <input type="hidden" name="source" value="range"><input type="hidden" name="type" value="{{ $type }}">
                        <div class="col-5"><input name="from" value="{{ request('from') }}" class="form-control font-monospace" placeholder="ตั้งแต่ เช่น 00001" required></div>
                        <div class="col-5"><input name="to" value="{{ request('to') }}" class="form-control font-monospace" placeholder="ถึง เช่น 00050"></div>
                        <div class="col-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i></button></div>
                    </form>
                @endif
                <div class="fs-4 fw-bold">{{ $copies->count() }} <span class="fs-6 fw-normal text-muted">เล่ม</span></div>
                @if ($copies->isNotEmpty())
                    <div class="small text-muted">เลขทะเบียน {{ $copies->first()->accession_no ?? '-' }} – {{ $copies->last()->accession_no ?? '-' }}</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-tags"></i> ชนิดป้าย</div>
            <div class="card-body">
                <div class="row g-2 mb-3">
                    @foreach (L::TYPES as $t => [$label, $desc])
                        <div class="col-sm-6">
                            <a href="{{ $q(['type' => $t]) }}" class="d-block border rounded-3 p-2 h-100 text-body text-decoration-none {{ $type === $t ? 'border-primary border-2 bg-primary-subtle' : '' }}">
                                <div class="fw-bold small"><i class="bi {{ ['spine' => 'bi-bookmark-fill', 'outer' => 'bi-upc-scan', 'inner' => 'bi-file-earmark-text', 'all' => 'bi-layers'][$t] }} text-success"></i> {{ $label }}</div>
                                <div class="small text-muted">{{ $desc }}</div>
                            </a>
                        </div>
                    @endforeach
                </div>
                <form method="GET" class="d-flex flex-wrap gap-3 align-items-end">
                    @foreach (request()->except(['skip', 'band', 'cut']) as $k => $val)<input type="hidden" name="{{ $k }}" value="{{ $val }}">@endforeach
                    <div><label class="form-label small">เว้นช่องแรก (สติกเกอร์ที่ใช้ไปแล้ว)</label><input type="number" name="skip" min="0" max="41" value="{{ $skip }}" class="form-control form-control-sm" style="width:110px"></div>
                    <input type="hidden" name="band" value="0"><input type="hidden" name="cut" value="0">
                    <label class="form-check form-switch small"><input type="checkbox" name="band" value="1" class="form-check-input" @checked($band) onchange="this.form.submit()"> แถบสีตามหมวดบนป้ายสัน</label>
                    <label class="form-check form-switch small"><input type="checkbox" name="cut" value="1" class="form-check-input" @checked($cut) onchange="this.form.submit()"> เส้นขอบสำหรับตัด</label>
                    <button class="btn btn-sm btn-light border">ใช้</button>
                </form>
            </div>
            <div class="card-footer bg-transparent d-flex flex-wrap gap-2">
                <button class="btn btn-primary" onclick="print()" @disabled($copies->isEmpty())><i class="bi bi-printer"></i> พิมพ์ ({{ $sheets->sum(fn ($s) => $s->count()) }} หน้า)</button>
                @if ($copies->isNotEmpty())
                    <form method="POST" action="{{ route('library.labels.printed') }}" data-confirm="บันทึกว่าพิมพ์และติดป้ายครบ {{ $copies->count() }} เล่มแล้ว?">@csrf
                        @foreach ($copies as $c)<input type="hidden" name="ids[]" value="{{ $c->id }}">@endforeach
                        <button class="btn btn-outline-success"><i class="bi bi-check2-all"></i> ติดป้ายครบแล้ว (เอาออกจากคิว)</button></form>
                @endif
                <span class="small text-muted align-self-center">ตั้งเครื่องพิมพ์: A4 · ขนาดจริง 100% · ไม่ย่อให้พอดีหน้า · เปิด "พิมพ์พื้นหลัง" ให้แถบสีออก</span>
            </div>
        </div>
    </div>
</div>

@if ($copies->isEmpty())
    <div class="card no-print"><div class="empty"><i class="bi bi-check2-circle"></i>{{ $source === 'pending' ? 'ทุกเล่มพิมพ์ป้ายแล้ว' : 'ไม่พบเล่มตามที่เลือก' }}</div></div>
@endif

@foreach ($sheets as $t => $pages)
    @foreach ($pages as $page)
        <div class="label-paper">
            <div class="label-sheet {{ $t }}-sheet {{ $cut ? '' : 'no-cut' }}">
                @foreach ($page as $c)
                    @if (! $c)
                        <div class="{{ ['spine' => 'spine', 'outer' => 'lbl-outer', 'inner' => 'lbl-inner'][$t] }} blank"></div>
                    @elseif ($t === 'spine')
                        <x-library.spine :copy="$c" :band="$band" />
                    @elseif ($t === 'outer')
                        <x-library.outer :copy="$c" />
                    @else
                        <x-library.inner :copy="$c" />
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach
@endforeach
@endsection
