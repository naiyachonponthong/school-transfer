@extends('layouts.app')
@section('title', 'ทะเบียนหนังสือ')

@section('content')
<x-page-banner title="ทะเบียนหนังสือ" :subtitle="number_format($stats['titles']).' รายการ · '.number_format($stats['copies']).' เล่ม'" eyebrow="SCHOOL LIBRARY">
        <a href="{{ route('library.loans') }}" class="btn btn-light border"><i class="bi bi-arrow-left-right"></i> ยืม-คืน</a>
        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#bookNew"><i class="bi bi-plus-lg"></i> เพิ่มหนังสือ</button>
</x-page-banner>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-primary"><i class="bi bi-book"></i></div><div><div class="stat-value">{{ number_format($stats['titles']) }}</div><div class="stat-label">รายการ</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-teal"><i class="bi bi-stack"></i></div><div><div class="stat-value">{{ number_format($stats['copies']) }}</div><div class="stat-label">เล่มทั้งหมด</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-info"><i class="bi bi-box-arrow-up-right"></i></div><div><div class="stat-value">{{ $stats['out'] }}</div><div class="stat-label">กำลังถูกยืม</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-danger"><i class="bi bi-alarm"></i></div><div><div class="stat-value">{{ $stats['overdue'] }}</div><div class="stat-label">เกินกำหนด</div></div></div></div></div>
</div>
<form class="d-flex gap-2 mb-3" method="GET">
    <input name="q" value="{{ request('q') }}" class="form-control" style="max-width:320px" placeholder="ชื่อหนังสือ ผู้แต่ง รหัส">
    <select name="category" class="form-select w-auto" data-autosubmit><option value="">ทุกหมวด</option>@foreach ($categories as $c)<option @selected(request('category') === $c)>{{ $c }}</option>@endforeach</select>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-cards table-hover align-middle">
            <thead><tr><th>รหัส</th><th>ชื่อหนังสือ</th><th>ผู้แต่ง</th><th>หมวด</th><th>ชั้นวาง</th><th class="text-center">คงเหลือ</th><th></th></tr></thead>
            <tbody>
            @forelse ($books as $b)
                <tr>
                    <td class="small text-muted">{{ $b->code }}</td><td class="fw-semibold">{{ $b->title }}</td><td class="small">{{ $b->author }}</td>
                    <td class="small">{{ $b->category }}</td><td class="small">{{ $b->location }}</td>
                    <td class="text-center"><span class="badge bg-{{ $b->available() ? 'success' : 'secondary' }}">{{ $b->available() }}/{{ $b->copies }}</span></td>
                    <td class="text-end"><button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#book{{ $b->id }}"><i class="bi bi-pencil"></i></button></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty"><i class="bi bi-book"></i>ยังไม่มีหนังสือ</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $books->links() }}</div>

@foreach ($books->getCollection()->push(new \App\Models\Book(['copies' => 1])) as $b)
<div class="modal fade" id="book{{ $b->id ?? 'New' }}" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ $b->exists ? route('library.update', $b) : route('library.store') }}">
            @csrf @if ($b->exists) @method('PUT') @endif
            <div class="modal-header"><h5 class="modal-title">{{ $b->exists ? 'แก้ไขหนังสือ' : 'เพิ่มหนังสือ' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-2">
                <div class="col-5"><label class="form-label">รหัส/บาร์โค้ด</label><input name="code" value="{{ $b->code }}" class="form-control" required></div>
                <div class="col-7"><label class="form-label">ชื่อหนังสือ</label><input name="title" value="{{ $b->title }}" class="form-control" required></div>
                <div class="col-6"><label class="form-label">ผู้แต่ง</label><input name="author" value="{{ $b->author }}" class="form-control"></div>
                <div class="col-6"><label class="form-label">สำนักพิมพ์</label><input name="publisher" value="{{ $b->publisher }}" class="form-control"></div>
                <div class="col-5"><label class="form-label">หมวด</label><input name="category" value="{{ $b->category }}" class="form-control" list="bookCats"></div>
                <div class="col-4"><label class="form-label">ชั้นวาง</label><input name="location" value="{{ $b->location }}" class="form-control"></div>
                <div class="col-3"><label class="form-label">จำนวนเล่ม</label><input name="copies" type="number" min="1" value="{{ $b->copies }}" class="form-control" required></div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">บันทึก</button></div>
        </form>
        @if ($b->exists)
            <form method="POST" action="{{ route('library.destroy', $b) }}" class="px-3 pb-3" data-confirm="ลบหนังสือนี้?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger px-0">ลบ</button></form>
        @endif
    </div></div>
</div>
@endforeach
<datalist id="bookCats">@foreach (['นวนิยาย', 'การ์ตูนความรู้', 'วิทยาศาสตร์', 'คณิตศาสตร์', 'ประวัติศาสตร์', 'ภาษาอังกฤษ', 'สารคดี', 'แบบเรียน'] as $c)<option>{{ $c }}</option>@endforeach</datalist>
@endsection
