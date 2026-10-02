@extends('layouts.app')
@section('title', 'เขียนใบเบิกวัสดุ')

@section('content')
<div class="page-head"><div><h1>เขียนใบเบิกวัสดุ</h1><div class="sub">เลือกวัสดุและจำนวน · งานพัสดุจะได้รับแจ้งทาง LINE และแจ้งกลับเมื่อจ่ายของ</div></div></div>
<form method="POST" action="{{ route('requisitions.store') }}" class="card" style="max-width:820px">
    @csrf
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-5"><label class="form-label">กลุ่มสาระ / งาน</label><input name="department" value="{{ old('department') }}" class="form-control" list="depts"></div>
            <datalist id="depts">@foreach ($departments as $d)<option>{{ $d }}</option>@endforeach</datalist>
            <div class="col-md-7"><label class="form-label">ใช้สำหรับ</label><input name="purpose" value="{{ old('purpose') }}" class="form-control" placeholder="เช่น จัดทำข้อสอบกลางภาค"></div>
        </div>
        <table class="table table-sm align-middle mb-2">
            <thead><tr><th>วัสดุ</th><th style="width:130px">จำนวน</th><th style="width:40px"></th></tr></thead>
            <tbody id="rows">
                @for ($i = 0; $i < 3; $i++)
                    <tr class="item-row">
                        <td><select name="items[{{ $i }}][supply_id]" class="form-select form-select-sm"><option value="">-</option>@foreach ($supplies as $s)<option value="{{ $s->id }}" @disabled($s->stock <= 0)>{{ $s->name }} (คงเหลือ {{ number_format($s->stock) }} {{ $s->unit }})</option>@endforeach</select></td>
                        <td><input type="number" min="1" name="items[{{ $i }}][quantity]" class="form-control form-control-sm"></td>
                        <td><button type="button" class="btn btn-sm btn-link text-danger" data-remove-row><i class="bi bi-x-lg"></i></button></td>
                    </tr>
                @endfor
            </tbody>
        </table>
        <button type="button" class="btn btn-sm btn-light border" data-add-row="#rowTpl" data-target="#rows"><i class="bi bi-plus"></i> เพิ่มรายการ</button>
        <template id="rowTpl">
            <tr class="item-row">
                <td><select name="items[__i__][supply_id]" class="form-select form-select-sm"><option value="">-</option>@foreach ($supplies as $s)<option value="{{ $s->id }}" @disabled($s->stock <= 0)>{{ $s->name }} (คงเหลือ {{ number_format($s->stock) }} {{ $s->unit }})</option>@endforeach</select></td>
                <td><input type="number" min="1" name="items[__i__][quantity]" class="form-control form-control-sm"></td>
                <td><button type="button" class="btn btn-sm btn-link text-danger" data-remove-row><i class="bi bi-x-lg"></i></button></td>
            </tr>
        </template>
        @if ($supplies->isEmpty())<div class="alert alert-info small mt-3 mb-0">งานพัสดุยังไม่ได้เพิ่มรายการวัสดุในระบบ</div>@endif
    </div>
    <div class="card-footer bg-transparent d-flex gap-2">
        <button class="btn btn-primary btn-lg"><i class="bi bi-send"></i> ส่งใบเบิก</button>
        <a href="{{ route('requisitions.index') }}" class="btn btn-light btn-lg border">ยกเลิก</a>
    </div>
</form>
@endsection
