@extends('layouts.app')
@section('title', 'ทะเบียนคุม ปพ.7')

@section('content')
<div class="page-head">
    <div><h1>ทะเบียนคุมใบรับรองผลการศึกษา (ปพ.7)</h1><div class="sub">ออกใบใหม่ได้จากหน้าข้อมูลนักเรียน → ปุ่ม "ปพ.7"</div></div>
    <div class="actions"><a href="{{ route('students.index') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> ออกใบรับรอง</a></div>
</div>
<form class="mb-3" method="GET"><input name="q" value="{{ request('q') }}" class="form-control" style="max-width:320px" placeholder="ค้นหาชื่อ / วัตถุประสงค์"></form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>เลขที่</th><th>วันที่ออก</th><th>นักเรียน</th><th>ออกให้เพื่อ</th><th>ผู้ออก</th><th></th></tr></thead>
            <tbody>
            @forelse ($issues as $i)
                <tr>
                    <td class="fw-semibold">{{ $i->code() }}</td>
                    <td>{{ thai_date($i->issued_on) }}</td>
                    <td>@if($i->student_id)<a href="{{ route('students.show', $i->student_id) }}">{{ $i->student_name }}</a>@else{{ $i->student_name }}@endif</td>
                    <td>{{ $i->purpose }}</td>
                    <td class="small text-muted">{{ $i->issuer?->name ?? '-' }}</td>
                    <td class="text-end"><a href="{{ route('certificates.show', $i) }}" class="btn btn-sm btn-light border"><i class="bi bi-printer"></i> พิมพ์ซ้ำ</a></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><i class="bi bi-file-earmark-check"></i>ยังไม่เคยออกใบรับรอง</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $issues->links() }}</div>
@endsection
