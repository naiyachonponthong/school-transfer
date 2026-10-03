@extends('layouts.app')
@section('title', 'สารบรรณ')

@section('content')
<div class="page-head">
    <div><h1>สารบรรณ</h1><div class="sub">{{ $canManage ? 'ทะเบียนหนังสือรับ-ส่ง คำสั่ง และบันทึกข้อความ' : 'หนังสือที่เวียนถึงคุณ' }} · ยังไม่รับทราบ {{ $unread->count() }} ฉบับ</div></div>
    @if ($canManage)<div class="actions"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newDoc"><i class="bi bi-plus-lg"></i> ลงทะเบียนหนังสือ</button></div>@endif
</div>

@if ($errors->any())<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>@endif

<form method="GET" class="d-flex flex-wrap gap-2 mb-3">
    <select name="type" class="form-select" style="max-width:200px" data-autosubmit aria-label="ประเภท">
        <option value="">ทุกประเภท</option>
        @foreach (\App\Models\OfficeDocument::TYPES as $k => $v)<option value="{{ $k }}" @selected($type === $k)>{{ $v }}</option>@endforeach
    </select>
    <input name="q" value="{{ request('q') }}" class="form-control" style="max-width:320px" placeholder="ค้นหาเรื่อง หน่วยงาน เลขที่หนังสือ">
    <button class="btn btn-light border"><i class="bi bi-search"></i></button>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>เลขทะเบียน</th><th>ลงวันที่</th><th>เรื่อง</th><th>จาก / ถึง</th><th class="text-center">รับทราบ</th></tr></thead>
            <tbody>
            @forelse ($docs as $d)
                <tr class="{{ isset($unread[$d->id]) ? 'fw-semibold' : '' }}">
                    <td class="text-nowrap"><span class="badge bg-dark">{{ $d->typeLabel() }}</span> {{ $d->number() }}</td>
                    <td class="small text-nowrap">{{ thai_date($d->doc_date) }}</td>
                    <td><a href="{{ route('office.show', $d) }}">{{ $d->subject }}</a>
                        @if ($d->urgency !== 'normal')<span class="badge bg-{{ \App\Models\OfficeDocument::URGENCY[$d->urgency][1] }}">{{ \App\Models\OfficeDocument::URGENCY[$d->urgency][0] }}</span>@endif
                        @if (isset($unread[$d->id]))<span class="badge bg-primary">ใหม่</span>@endif
                        @if ($d->ref_no)<div class="small text-muted fw-normal">ที่ {{ $d->ref_no }}</div>@endif
                    </td>
                    <td class="small">{{ $d->party ?: '-' }}</td>
                    <td class="text-center small">{{ $d->recipients_count ? $d->acknowledged_count.'/'.$d->recipients_count : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty"><i class="bi bi-inbox"></i>ไม่มีหนังสือ</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($docs->hasPages())<div class="card-footer bg-transparent">{{ $docs->links() }}</div>@endif
</div>

@if ($canManage)
<div class="modal fade" id="newDoc" tabindex="-1">
    <div class="modal-dialog modal-lg"><form method="POST" action="{{ route('office.store') }}" enctype="multipart/form-data" class="modal-content">
        @csrf
        <div class="modal-header"><h5 class="modal-title">ลงทะเบียนหนังสือ</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-md-4"><label class="form-label">ประเภท</label><select name="type" class="form-select">@foreach (\App\Models\OfficeDocument::TYPES as $k => $v)<option value="{{ $k }}" @selected(old('type') === $k)>{{ $v }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">ลงวันที่</label><input type="date" name="doc_date" value="{{ old('doc_date', today()->toDateString()) }}" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">ชั้นความเร็ว</label><select name="urgency" class="form-select">@foreach (\App\Models\OfficeDocument::URGENCY as $k => [$v])<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label">เรื่อง</label><input name="subject" value="{{ old('subject') }}" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">เลขที่หนังสือของต้นทาง</label><input name="ref_no" value="{{ old('ref_no') }}" class="form-control" placeholder="เช่น ศธ 04123/ว1234"></div>
            <div class="col-md-6"><label class="form-label">จาก / ถึง</label><input name="party" value="{{ old('party') }}" class="form-control" placeholder="หน่วยงาน"></div>
            <div class="col-12"><label class="form-label">ไฟล์หนังสือ</label><input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,image/*"></div>
            <div class="col-12"><label class="form-label">หมายเหตุ / การปฏิบัติ</label><textarea name="note" rows="2" class="form-control">{{ old('note') }}</textarea></div>
            <div class="col-12">
                <label class="form-label">เวียนให้รับทราบ</label>
                <div class="mb-2"><label class="small fw-semibold"><input type="checkbox" class="form-check-input" name="all_staff" value="1"> บุคลากรทุกคน</label></div>
                <div class="d-flex flex-wrap gap-3" style="max-height:160px;overflow:auto">
                    @foreach ($staff as $u)<label class="small text-nowrap"><input type="checkbox" class="form-check-input" name="recipient_ids[]" value="{{ $u->id }}"> {{ $u->name }}</label>@endforeach
                </div>
            </div>
        </div>
        <div class="modal-footer"><span class="small text-muted me-auto">เลขทะเบียนออกให้อัตโนมัติ แยกตามประเภทและปี</span><button class="btn btn-primary"><i class="bi bi-check-lg"></i> ลงทะเบียน</button></div>
    </form></div>
</div>
@endif
@endsection
