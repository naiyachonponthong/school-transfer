@extends('layouts.app')
@section('title', 'โครงสร้างองค์กร')

@section('content')
@php
    $fields = function (?\App\Models\Department $d = null) use ($departments, $flat, $staff) {
        return view('org._fields', ['department' => $d, 'departments' => $departments, 'flat' => $flat, 'staff' => $staff])->render();
    };
@endphp
<div class="page-head">
    <div><h1>โครงสร้างองค์กร</h1><div class="sub">{{ $departments->count() }} หน่วยงาน · บุคลากร {{ $staff->count() }} คน · ยังไม่ได้สังกัด {{ $unassigned->count() }} คน</div></div>
    <div class="actions d-print-none">
        <div class="btn-group">
            <a href="{{ route('org.index') }}" class="btn {{ $table ? 'btn-light border' : 'btn-dark' }}"><i class="bi bi-diagram-3"></i> ผัง</a>
            <a href="{{ route('org.index', ['view' => 'table']) }}" class="btn {{ $table ? 'btn-dark' : 'btn-light border' }}"><i class="bi bi-table"></i> ตาราง</a>
        </div>
        <a href="{{ route('staff.directory') }}" class="btn btn-light border"><i class="bi bi-person-lines-fill"></i> ทะเบียนติดต่อ</a>
        <button type="button" class="btn btn-light border" onclick="window.print()"><i class="bi bi-printer"></i> พิมพ์</button>
        @if ($canManage)<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDept"><i class="bi bi-plus-lg"></i> เพิ่มหน่วยงาน</button>@endif
    </div>
</div>

@if ($departments->isEmpty())
    <div class="card"><div class="card-body"><div class="empty"><i class="bi bi-diagram-3"></i>ยังไม่มีหน่วยงาน
        @if ($canManage)
            <form method="POST" action="{{ route('org.preset') }}" class="mt-3">@csrf
                <button class="btn btn-primary"><i class="bi bi-magic"></i> สร้างโครงสร้างมาตรฐาน</button>
                <div class="small text-muted mt-2">ผู้อำนวยการ → 4 ฝ่ายบริหาร → กลุ่มสาระการเรียนรู้ แล้วแก้ชื่อ เพิ่ม หรือลบได้ตามจริง</div>
            </form>
        @endif
    </div></div></div>
@elseif ($table)
    <div class="card mb-3"><div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>หน่วยงาน</th><th>ประเภท</th><th>หัวหน้า</th><th>บุคลากรในหน่วย</th><th class="text-end">รวมหน่วยย่อย</th><th class="d-print-none"></th></tr></thead>
            <tbody>
            @foreach ($flat as $row)
                @php
                    $d = $row['dept'];
                @endphp
                <tr>
                    <td style="padding-left:{{ 16 + $row['depth'] * 22 }}px"><span class="fw-semibold">{{ $d->name }}</span>@if ($d->code) <span class="small text-muted">{{ $d->code }}</span>@endif</td>
                    <td class="small">{{ $d->kindLabel() }}</td>
                    <td class="small">{{ $d->head?->name ?? '-' }}</td>
                    <td class="small text-muted">{{ $d->members->pluck('name')->implode(' · ') ?: '-' }}</td>
                    <td class="text-end">{{ $counts[$d->id] ?? 0 }}</td>
                    <td class="text-end d-print-none">@if ($canManage)<button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#editDept{{ $d->id }}" aria-label="แก้ไข {{ $d->name }}"><i class="bi bi-pencil"></i></button>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div></div>
@else
    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-diagram-3"></i> ผังหน่วยงาน <span class="ms-2 small text-muted fw-normal">ตัวเลขคือจำนวนคนรวมหน่วยย่อย (ไม่นับซ้ำ)</span></div>
        <div class="card-body" style="overflow-x:auto">
            <ul class="org-tree">
                @foreach ($roots as $root)
                    @include('org._node', ['d' => $root])
                @endforeach
            </ul>
        </div>
    </div>
@endif

@if ($departments->isNotEmpty())
    <div class="card">
        <div class="card-header"><i class="bi bi-person-exclamation"></i> ยังไม่ได้สังกัดหน่วยงาน <span class="ms-2 badge bg-secondary-subtle text-secondary-emphasis">{{ $unassigned->count() }} คน</span></div>
        <div class="card-body small">
            @if ($unassigned->isEmpty())
                <span class="text-success"><i class="bi bi-check-circle"></i> บุคลากรทุกคนมีสังกัดแล้ว</span>
            @else
                {{ $unassigned->pluck('name')->implode(' · ') }}
                @if ($canManage)<div class="text-muted mt-1">กดแก้ไขที่หน่วยงานแล้วติ๊กชื่อ หรือกำหนดที่หน้าประวัติของแต่ละคน</div>@endif
            @endif
        </div>
    </div>
@endif

@if ($canManage)
    <div class="modal fade" id="addDept" tabindex="-1">
        <div class="modal-dialog modal-lg"><form method="POST" action="{{ route('org.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">เพิ่มหน่วยงาน</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body row g-3">{!! $fields() !!}</div>
            <div class="modal-footer"><button class="btn btn-primary">เพิ่มหน่วยงาน</button></div>
        </form></div>
    </div>
    @foreach ($departments as $d)
        <div class="modal fade" id="editDept{{ $d->id }}" tabindex="-1">
            <div class="modal-dialog modal-lg"><div class="modal-content">
                <form method="POST" action="{{ route('org.update', $d) }}" id="deptForm{{ $d->id }}">
                    @csrf @method('PUT')
                    <div class="modal-header"><h5 class="modal-title">แก้ไข {{ $d->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
                    <div class="modal-body row g-3">{!! $fields($d) !!}</div>
                </form>
                <div class="modal-footer">
                    <form method="POST" action="{{ route('org.destroy', $d) }}" class="me-auto" data-confirm="ลบหน่วยงาน {{ $d->name }}? หน่วยย่อยจะเลื่อนขึ้นไปอยู่ใต้หน่วยแม่ และบุคลากรจะหลุดจากหน่วยนี้">@csrf @method('DELETE')
                        <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> ลบ</button>
                    </form>
                    <button class="btn btn-primary" form="deptForm{{ $d->id }}">บันทึก</button>
                </div>
            </div></div>
        </div>
    @endforeach
@endif
@endsection
