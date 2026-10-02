@extends('layouts.app')
@section('title', 'คลังวัสดุ')

@section('content')
<div class="page-head">
    <div><h1>คลังวัสดุสิ้นเปลือง</h1><div class="sub">{{ $supplies->where('is_active', true)->count() }} รายการ · ใกล้หมด {{ $lowCount }} · ใบเบิกรอจ่าย {{ $pending }}</div></div>
    <div class="actions">
        <a href="{{ route('requisitions.index', ['status' => 'pending']) }}" class="btn btn-light border"><i class="bi bi-bag-check"></i> ใบเบิกรอจ่าย <span class="badge text-bg-warning">{{ $pending }}</span></a>
        <a href="{{ route('supplies.report') }}" class="btn btn-light border"><i class="bi bi-bar-chart"></i> สรุปรายเดือน</a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newSupply"><i class="bi bi-plus-lg"></i> เพิ่มวัสดุ</button>
    </div>
</div>

<form method="GET" class="d-flex gap-2 mb-3">
    <input name="q" value="{{ request('q') }}" class="form-control" style="max-width:280px" placeholder="ค้นหาชื่อวัสดุ">
    <a href="{{ route('supplies.index', request('low') ? [] : ['low' => 1]) }}" class="btn {{ request('low') ? 'btn-danger' : 'btn-outline-danger' }}">ใกล้หมด {{ $lowCount }}</a>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>วัสดุ</th><th>หมวด</th><th class="text-end">คงเหลือ</th><th class="text-end">ขั้นต่ำ</th><th style="width:330px">รับเข้า / ปรับยอด</th><th></th></tr></thead>
            <tbody>
            @forelse ($supplies as $s)
                <tr class="{{ $s->is_active ? '' : 'text-muted' }}">
                    <td><a href="{{ route('supplies.show', $s) }}" class="fw-semibold text-reset">{{ $s->name }}</a>@unless($s->is_active) <span class="badge text-bg-light border">เลิกใช้</span>@endunless</td>
                    <td class="small">{{ $s->category ?: '-' }}</td>
                    <td class="text-end fw-semibold {{ $s->isLow() ? 'text-danger' : '' }}">{{ number_format($s->stock) }} <span class="fw-normal small">{{ $s->unit }}</span>@if($s->isLow()) <i class="bi bi-exclamation-triangle-fill" title="ใกล้หมด"></i>@endif</td>
                    <td class="text-end small">{{ $s->min_stock ?: '-' }}</td>
                    <td>
                        <form method="POST" action="{{ route('supplies.move', $s) }}" class="d-flex gap-1">
                            @csrf
                            <select name="type" class="form-select form-select-sm" style="width:100px"><option value="in">รับเข้า</option><option value="adjust">ปรับยอด ±</option></select>
                            <input type="number" name="quantity" class="form-control form-control-sm" style="width:80px" required placeholder="จำนวน">
                            <input name="note" class="form-control form-control-sm" placeholder="หมายเหตุ เช่น ซื้อ ร้าน ก.">
                            <button class="btn btn-sm btn-light border"><i class="bi bi-check-lg"></i></button>
                        </form>
                    </td>
                    <td class="text-end"><button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#s{{ $s->id }}"><i class="bi bi-pencil"></i></button></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><i class="bi bi-boxes"></i>ยังไม่มีวัสดุ กด "เพิ่มวัสดุ" (ใส่ยอดยกมาได้)</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@foreach ($supplies->push(new \App\Models\Supply(['unit' => 'ชิ้น', 'is_active' => true])) as $s)
<div class="modal fade" id="{{ $s->exists ? 's'.$s->id : 'newSupply' }}" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="{{ $s->exists ? route('supplies.update', $s) : route('supplies.store') }}" class="modal-content">
        @csrf @if ($s->exists) @method('PUT') @endif
        <div class="modal-header"><h5 class="modal-title">{{ $s->exists ? 'แก้ไขวัสดุ' : 'เพิ่มวัสดุ' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-8"><label class="form-label">ชื่อวัสดุ</label><input name="name" value="{{ $s->name }}" class="form-control" required placeholder="เช่น กระดาษ A4 80 แกรม"></div>
            <div class="col-4"><label class="form-label">หน่วยนับ</label><input name="unit" value="{{ $s->unit }}" class="form-control" required list="units"></div>
            <div class="col-8"><label class="form-label">หมวด</label><input name="category" value="{{ $s->category }}" class="form-control" list="cats" placeholder="เช่น วัสดุสำนักงาน"></div>
            <div class="col-4"><label class="form-label">แจ้งเมื่อเหลือ ≤</label><input type="number" min="0" name="min_stock" value="{{ $s->min_stock }}" class="form-control"></div>
            @unless ($s->exists)
                <div class="col-6"><label class="form-label">ยอดยกมา</label><input type="number" min="0" name="initial_stock" value="0" class="form-control"></div>
            @else
                <div class="col-12"><input type="hidden" name="is_active" value="0"><label><input type="checkbox" name="is_active" value="1" @checked($s->is_active)> ยังใช้งาน (ให้ครูเบิกได้)</label></div>
            @endunless
        </div>
        <div class="modal-footer"><button class="btn btn-primary">บันทึก</button></div>
    </form></div>
</div>
@endforeach
<datalist id="units"><option>ชิ้น</option><option>รีม</option><option>กล่อง</option><option>ด้าม</option><option>แท่ง</option><option>ม้วน</option><option>ขวด</option><option>แพ็ค</option><option>อัน</option></datalist>
<datalist id="cats">@foreach ($categories as $c)<option>{{ $c }}</option>@endforeach<option>วัสดุสำนักงาน</option><option>วัสดุคอมพิวเตอร์</option><option>วัสดุงานบ้านงานครัว</option><option>วัสดุการศึกษา</option></datalist>
@endsection
