@extends('layouts.app')
@section('title', 'ทะเบียนคุมเอกสาร')

@section('content')
<div class="page-head">
    <div><h1>ทะเบียนคุมเอกสาร</h1><div class="sub">ปพ.7 ออกจากหน้าข้อมูลนักเรียน → "ปพ.7" · ปพ.1 ฉบับจริงบันทึกชุดที่/เลขที่จากหน้า ปพ.1</div></div>
    <div class="actions"><a href="{{ route('students.index') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> ออกใบรับรอง</a></div>
</div>
<form class="mb-3" method="GET"><input name="q" value="{{ request('q') }}" class="form-control" style="max-width:320px" placeholder="ค้นหาชื่อ / วัตถุประสงค์"></form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>เอกสาร</th><th>เลขที่</th><th>วันที่ออก</th><th>นักเรียน</th><th>ออกให้เพื่อ / หมายเหตุ</th><th>ผู้ออก</th><th></th></tr></thead>
            <tbody>
            @forelse ($issues as $i)
                <tr>
                    <td class="small">{{ $i->typeLabel() }}</td>
                    <td class="fw-semibold">{{ $i->code() }}@if($i->form_number)<div class="small text-muted fw-normal">แบบพิมพ์ชุด {{ $i->form_series }} เลขที่ {{ $i->form_number }}</div>@endif</td>
                    <td>{{ thai_date($i->issued_on) }}</td>
                    <td>@if($i->student_id)<a href="{{ route('students.show', $i->student_id) }}">{{ $i->student_name }}</a>@else{{ $i->student_name }}@endif</td>
                    <td>{{ $i->purpose }}</td>
                    <td class="small text-muted">{{ $i->issuer?->name ?? '-' }}</td>
                    <td class="text-end">
                        @if ($i->type === 'pp7')<a href="{{ route('certificates.show', $i) }}" class="btn btn-sm btn-light border"><i class="bi bi-printer"></i> พิมพ์ซ้ำ</a>
                        @elseif ($i->student_id)<a href="{{ route('transcript', ['student' => $i->student_id, 'stage' => $i->snapshot['stage'] ?? null]) }}" class="btn btn-sm btn-light border"><i class="bi bi-file-earmark-text"></i> ปพ.1</a>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty"><i class="bi bi-file-earmark-check"></i>ยังไม่เคยออกเอกสาร</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $issues->links() }}</div>
@endsection
