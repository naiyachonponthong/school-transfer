@extends('layouts.app')
@section('title', 'ประวัติการแก้ไข')

@section('content')
@php
    $show = fn ($v) => match (true) {
        $v === null || $v === '' => '-',
        is_bool($v) => $v ? 'ใช่' : 'ไม่ใช่',
        is_array($v) => json_encode($v, JSON_UNESCAPED_UNICODE),
        default => (string) $v,
    };
@endphp
<div class="page-head">
    <div><h1>ประวัติการแก้ไข</h1><div class="sub">บันทึกอัตโนมัติ: คะแนนหลังล็อก ผลพิเศษ/แก้ตัว ล็อกรายวิชา ข้อมูลนักเรียน อนุมัติการจบ เอกสาร ปพ. การเงิน บัญชีผู้ใช้ และการตั้งค่า · แก้หรือลบประวัติไม่ได้</div></div>
</div>

<form method="GET" class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <div><label class="form-label">หมวด</label><select name="group" class="form-select" data-autosubmit><option value="">ทั้งหมด</option>@foreach (\App\Models\AuditLog::GROUPS as $k => $v)<option value="{{ $k }}" @selected(request('group') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div><label class="form-label">ตั้งแต่</label><input type="date" name="from" value="{{ request('from') }}" class="form-control" data-autosubmit></div>
        <div><label class="form-label">ถึง</label><input type="date" name="to" value="{{ request('to') }}" class="form-control" data-autosubmit></div>
        <div class="flex-grow-1" style="min-width:200px"><label class="form-label">ค้นหา</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="ชื่อนักเรียน รหัสวิชา ผู้ทำรายการ..."></div>
        @if (request('subject'))<input type="hidden" name="subject" value="{{ request('subject') }}"><a href="{{ route('audit.index') }}" class="btn btn-link">ล้างตัวกรองรายการ</a>@endif
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th style="width:150px">เวลา</th><th style="width:160px">ผู้ทำรายการ</th><th style="width:150px">หมวด</th><th>รายละเอียด</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="small text-nowrap">{{ thai_datetime($log->created_at) }}</td>
                    <td class="small">{{ $log->user_name ?? 'ระบบ' }}@if($log->ip)<div class="text-muted">{{ $log->ip }}</div>@endif</td>
                    <td class="small"><span class="badge bg-light text-dark border">{{ $log->groupLabel() }}</span></td>
                    <td class="small">
                        {{ $log->description }}
                        @if ($log->changes)
                            <details class="mt-1">
                                <summary class="text-muted">ดูรายละเอียด</summary>
                                @if (isset($log->changes['cells']))
                                    <table class="table table-sm mb-0 mt-1"><tr class="text-muted"><td>นักเรียน</td><td>ช่อง</td><td>เดิม</td><td>ใหม่</td></tr>
                                        @foreach ($log->changes['cells'] as $c)<tr><td>{{ $c['student'] }}</td><td>{{ $c['assessment'] }}</td><td>{{ $show($c['old']) }}</td><td>{{ $show($c['new']) }}</td></tr>@endforeach
                                    </table>
                                @else
                                    <table class="table table-sm mb-0 mt-1">
                                        @foreach ($log->changes as $field => $pair)
                                            <tr><td class="text-muted" style="width:30%">{{ \App\Models\AuditLog::fieldLabel($field) }}</td>
                                                @if (is_array($pair) && array_is_list($pair) && count($pair) === 2)<td>{{ $show($pair[0]) }} → {{ $show($pair[1]) }}</td>@else<td>{{ $show($pair) }}</td>@endif
                                            </tr>
                                        @endforeach
                                    </table>
                                @endif
                            </details>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4"><div class="empty"><i class="bi bi-clock-history"></i>ยังไม่มีประวัติ</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $logs->links() }}</div>
@endsection
