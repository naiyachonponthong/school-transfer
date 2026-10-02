@extends('layouts.app')
@section('title', $book->title)

@php
    use App\Models\BookCopy;
    use App\Support\Dewey;
    [$collLabel, $collSymbol] = $book->collectionInfo();
    $inService = $book->items->where('condition', '!=', 'withdrawn');
    $free = $book->items->filter(fn ($c) => $c->isCirculating() && ! $c->activeLoan);
    $first = $book->items->first();
@endphp

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $book->title }}{{ $book->volume ? ' ล.'.preg_replace('/^ล\.?\s*/u', '', $book->volume) : '' }}</h1>
        <div class="sub">{{ $book->author ?: 'ไม่ระบุผู้แต่ง' }} · เลขเรียก <span class="font-monospace fw-semibold">{{ $book->callNumberText() ?: '-' }}</span></div>
    </div>
    <div class="actions">
        <a href="{{ route('library.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> ทะเบียนหนังสือ</a>
        <a href="{{ route('library.edit', $book) }}" class="btn btn-light border"><i class="bi bi-pencil"></i> แก้ไขระเบียน</a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#printModal" @disabled($inService->isEmpty())><i class="bi bi-printer"></i> พิมพ์ป้ายติดเล่ม</button>
    </div>
</div>
@error('copy')<div class="alert alert-danger">{{ $message }}</div>@enderror

<div class="row g-3">
    {{-- ========== ระเบียนบรรณานุกรม ========== --}}
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-journal-text"></i> ข้อมูลบรรณานุกรม</div>
            <div class="card-body">
                <div class="d-flex gap-3 mb-3">
                    <div class="flex-shrink-0 rounded-3 overflow-hidden border bg-body-tertiary d-flex align-items-center justify-content-center" style="width:110px;aspect-ratio:3/4">
                        @if ($book->coverUrl())<img src="{{ $book->coverUrl() }}" alt="" class="w-100 h-100" style="object-fit:cover">@else<i class="bi bi-book fs-1 text-muted"></i>@endif
                    </div>
                    @if ($first)<x-library.spine :copy="$first" />@endif
                </div>
                <dl class="row small mb-0 gy-1">
                    @foreach (array_filter([
                        'เลขเรียกหนังสือ' => $book->callNumberText(),
                        'เลขหมู่ (DDC)' => $book->class_number ? $book->class_number.' · '.Dewey::label($book->class_number).(Dewey::division($book->class_number) && isset(Dewey::DIVISIONS[Dewey::division($book->class_number)]) ? ' › '.Dewey::DIVISIONS[Dewey::division($book->class_number)] : '') : null,
                        'ประเภท' => $collLabel.($collSymbol ? ' ('.$collSymbol.')' : ''),
                        'ISBN' => $book->isbn,
                        'ผู้แต่ง' => $book->author,
                        'ผู้แต่งร่วม' => $book->contributors,
                        'พิมพลักษณ์' => $book->imprint(),
                        'ลักษณะรูปเล่ม' => $book->physical(),
                        'ชื่อชุด' => $book->series,
                        'ภาษา' => $book->language,
                        'หมายเหตุ' => $book->note,
                        'รหัสระเบียน' => $book->code,
                    ]) as $label => $value)
                        <dt class="col-4 text-muted fw-normal">{{ $label }}</dt><dd class="col-8 mb-0 {{ in_array($label, ['เลขเรียกหนังสือ', 'ISBN', 'รหัสระเบียน'], true) ? 'font-monospace' : '' }}">{{ $value }}</dd>
                    @endforeach
                </dl>
                @if ($book->subjectList())
                    <div class="small text-muted mt-3 mb-1">หัวเรื่อง</div>
                    <div class="tag-list">@foreach ($book->subjectList() as $s)<a href="{{ route('library.index', ['q' => $s]) }}" class="badge text-bg-light border text-decoration-none">{{ $s }}</a>@endforeach</div>
                @endif
                @if ($book->summary)<div class="small text-muted mt-3 mb-1">สาระสังเขป</div><div class="small" style="white-space:pre-line">{{ $book->summary }}</div>@endif
            </div>
        </div>
    </div>

    {{-- ========== ตัวเล่ม ========== --}}
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-upc-scan"></i> ตัวเล่ม <span class="small text-muted fw-normal ms-1">พร้อมยืม {{ $free->count() }} / {{ $inService->count() }} เล่ม</span>
                @unless ($book->loanable())<span class="badge text-bg-warning ms-2">ใช้ในห้องสมุดเท่านั้น</span>@endunless</div>
            <div class="table-responsive">
                <table class="table table-hover table-cards align-middle mb-0">
                    <thead><tr><th>บาร์โค้ด</th><th>เลขทะเบียน</th><th class="text-center">ฉบับที่</th><th>ชั้นจัดเก็บ</th><th>สภาพ</th><th>สถานะ</th><th class="text-end"></th></tr></thead>
                    <tbody>
                    @forelse ($book->items as $c)
                        <tr class="{{ $c->condition === 'withdrawn' ? 'text-muted' : '' }}">
                            <td class="tc-title font-monospace fw-semibold">{{ $c->barcode }}</td>
                            <td class="font-monospace">{{ $c->accession_no ?? '-' }}</td>
                            <td class="text-center">{{ $c->copy_no }}</td>
                            <td class="small">{{ $c->location ?: '-' }}</td>
                            <td><span class="badge bg-{{ $c->conditionColor() }}-subtle text-{{ $c->conditionColor() }}-emphasis">{{ $c->conditionLabel() }}</span></td>
                            <td class="small">
                                @if ($c->activeLoan)
                                    <span class="{{ $c->activeLoan->isOverdue() ? 'text-danger fw-semibold' : '' }}">ยืมโดย {{ $c->activeLoan->student?->fullName() }} · คืน {{ thai_date($c->activeLoan->due_on) }}</span>
                                @elseif ($c->isCirculating())
                                    <span class="text-success"><i class="bi bi-check-circle"></i> ว่าง</span>
                                @else
                                    -
                                @endif
                                @if (! $c->label_printed_at && $c->condition !== 'withdrawn')<div><span class="badge text-bg-light border">ยังไม่พิมพ์ป้าย</span></div>@endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('library.labels', ['source' => 'ids', 'ids' => $c->id, 'type' => 'all']) }}" class="btn btn-sm btn-light border" title="พิมพ์ป้ายเล่มนี้"><i class="bi bi-printer"></i></a>
                                <button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#copy{{ $c->id }}" title="แก้ไขเล่ม"><i class="bi bi-pencil"></i></button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty"><i class="bi bi-upc"></i>ยังไม่มีตัวเล่ม — เพิ่มด้านล่าง</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <form method="POST" action="{{ route('library.copies.store', $book) }}" class="card mb-3">
            @csrf
            <div class="card-header"><i class="bi bi-plus-lg"></i> เพิ่มตัวเล่ม <span class="small text-muted fw-normal ms-1">ได้บาร์โค้ดและเลขทะเบียนต่อจากล่าสุด</span></div>
            <div class="card-body row g-2 align-items-end">
                <div class="col-4 col-md-2"><label class="form-label">จำนวน</label><input type="number" name="copies_count" min="1" max="200" value="1" class="form-control" required></div>
                <div class="col-8 col-md-3"><label class="form-label">ชั้นจัดเก็บ</label><input name="location" value="{{ $book->items->last()?->location }}" class="form-control" list="locs"></div>
                <div class="col-4 col-md-2"><label class="form-label">ราคา/เล่ม</label><input type="number" step="0.01" min="0" name="price" value="{{ $book->items->last()?->price }}" class="form-control"></div>
                <div class="col-8 col-md-3"><label class="form-label">วิธีได้มา</label><input name="source" class="form-control" list="sources" placeholder="จัดซื้อ"></div>
                <div class="col-12 col-md-2"><button class="btn btn-primary w-100">เพิ่ม</button></div>
            </div>
        </form>
        <datalist id="locs">@foreach ($locations as $l)<option>{{ $l }}</option>@endforeach</datalist>
        <datalist id="sources">@foreach (BookCopy::SOURCES as $s)<option>{{ $s }}</option>@endforeach</datalist>

        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history"></i> ประวัติการยืมล่าสุด</div>
            <div class="list-group list-group-flush small">
                @forelse ($history as $l)
                    <div class="list-group-item d-flex flex-wrap gap-2">
                        <span class="font-monospace text-muted">{{ $l->copy?->barcode }}</span>
                        <span class="fw-semibold">{{ $l->student?->fullName() }}</span><span class="text-muted">{{ $l->student?->classroom?->name() }}</span>
                        <span class="ms-auto">{{ thai_date($l->borrowed_on) }} → {!! $l->returned_on ? thai_date($l->returned_on) : '<span class="text-primary">ยังไม่คืน</span>' !!}</span>
                    </div>
                @empty
                    <div class="list-group-item text-muted">ยังไม่เคยถูกยืม</div>
                @endforelse
            </div>
        </div>
        @if (! $book->loans()->exists())
            <form method="POST" action="{{ route('library.destroy', $book) }}" class="mt-2 text-end" data-confirm="ลบระเบียนหนังสือนี้พร้อมตัวเล่มทั้งหมด? (ใช้กรณีบันทึกผิดเท่านั้น)">@csrf @method('DELETE')
                <button class="btn btn-sm btn-link text-danger"><i class="bi bi-trash"></i> ลบระเบียน (บันทึกผิด)</button></form>
        @endif
    </div>
</div>

{{-- แก้ไขตัวเล่ม --}}
@foreach ($book->items as $c)
<div class="modal fade" id="copy{{ $c->id }}" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('library.copies.update', $c) }}">
            @csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title">ตัวเล่ม {{ $c->barcode }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-2">
                <div class="col-6"><label class="form-label">บาร์โค้ด</label><input name="barcode" value="{{ $c->barcode }}" class="form-control font-monospace" required></div>
                <div class="col-6"><label class="form-label">เลขทะเบียน</label><input name="accession_no" value="{{ $c->accession_no }}" class="form-control font-monospace"></div>
                <div class="col-4"><label class="form-label">ฉบับที่</label><input type="number" name="copy_no" min="1" value="{{ $c->copy_no }}" class="form-control" required></div>
                <div class="col-8"><label class="form-label">ชั้นจัดเก็บ</label><input name="location" value="{{ $c->location }}" class="form-control" list="locs"></div>
                <div class="col-6"><label class="form-label">สภาพ</label><select name="condition" class="form-select">@foreach (BookCopy::CONDITIONS as $k => [$label])<option value="{{ $k }}" @selected($c->condition === $k)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label">ราคา</label><input type="number" step="0.01" min="0" name="price" value="{{ $c->price }}" class="form-control"></div>
                <div class="col-6"><label class="form-label">วันที่ได้รับ</label><input type="date" name="acquired_on" value="{{ $c->acquired_on?->toDateString() }}" class="form-control"></div>
                <div class="col-6"><label class="form-label">วิธีได้มา</label><input name="source" value="{{ $c->source }}" class="form-control" list="sources"></div>
                <div class="col-12"><label class="form-label">หมายเหตุ</label><input name="note" value="{{ $c->note }}" class="form-control"></div>
                <div class="col-12 small text-muted">เปลี่ยนบาร์โค้ด/เลขทะเบียน/ฉบับที่ → ต้องพิมพ์ป้ายใหม่ · หนังสือชำรุดเลิกใช้ เลือก "จำหน่ายออก" (ไม่ต้องลบ)</div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">บันทึก</button></div>
        </form>
        @unless ($c->loans()->exists())
            <form method="POST" action="{{ route('library.copies.destroy', $c) }}" class="px-3 pb-3" data-confirm="ลบตัวเล่ม {{ $c->barcode }}? (บันทึกผิดเท่านั้น)">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger px-0">ลบเล่มนี้</button></form>
        @endunless
    </div></div>
</div>
@endforeach

{{-- พิมพ์ป้ายติดเล่ม (ทุกเล่มของหนังสือนี้) --}}
<div class="modal fade" id="printModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">พิมพ์ป้ายติดเล่ม</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="small text-muted mb-3">เลือกชนิดป้าย — ระบบจัดหน้า A4 ให้อัตโนมัติ ({{ $inService->count() }} เล่ม)</div>
            <div class="row g-2">
                @foreach ([
                    'spine' => ['bi-bookmark-fill text-success', 'ป้ายสันหนังสือ', 'เลขเรียกตามระบบ DDC เรียงบรรทัด แถบสีตามหมวด · 2.5 × 4 ซม.'],
                    'outer' => ['bi-upc-scan text-success', 'บาร์โค้ดปกนอก', 'บาร์โค้ดสำหรับยิงยืม-คืน ติดปกหลัง/ปกหน้า · 5 × 2.5 ซม.'],
                    'inner' => ['bi-file-earmark-text text-success', 'บาร์โค้ดปกใน', 'บาร์โค้ด + ชื่อเรื่อง เลขทะเบียน เลขเรียก ราคา ติดหน้าปกใน · 7 × 4 ซม.'],
                    'all' => ['bi-layers text-success', 'ครบชุด (3 แบบ)', 'พิมพ์ทั้งสามแบบต่อกัน แยกหน้าให้อัตโนมัติ'],
                ] as $type => [$icon, $label, $desc])
                    <div class="col-md-6">
                        <a href="{{ route('library.labels', ['source' => 'book', 'book' => $book->id, 'type' => $type]) }}" class="d-block border rounded-3 p-3 h-100 text-body text-decoration-none list-link">
                            <div class="fw-bold"><i class="bi {{ $icon }}"></i> {{ $label }}</div><div class="small text-muted">{{ $desc }}</div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div></div>
</div>
@endsection
