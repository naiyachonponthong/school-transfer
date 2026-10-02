@extends('layouts.app')
@section('title', 'นักเรียน')

@section('content')
<x-page-banner title="นักเรียน" :subtitle="'ทั้งหมด '.number_format($students->total()).' คน'" eyebrow="STUDENT DIRECTORY">
        @php($room = request('classroom') ? $classrooms->firstWhere('id', (int) request('classroom')) : null)
        @if ($room && $room->isManagedBy(auth()->user()))
            <form method="POST" action="{{ route('student-accounts.create') }}" data-confirm="สร้างบัญชีเข้าระบบให้นักเรียนห้อง {{ $room->name() }} ที่ยังไม่มีบัญชี? (ชื่อผู้ใช้ = รหัสนักเรียน รหัสผ่านสุ่ม แสดงครั้งเดียวในใบแจก)">
                @csrf <input type="hidden" name="classroom_id" value="{{ $room->id }}">
                <button class="btn btn-light border"><i class="bi bi-person-badge"></i> สร้างบัญชีนักเรียนห้องนี้</button>
            </form>
        @endif
        <a href="{{ route('students.import') }}" class="btn btn-light border"><i class="bi bi-upload"></i> นำเข้าจาก Excel</a>
        <a href="{{ route('students.create', ['classroom' => request('classroom')]) }}" class="btn btn-light"><i class="bi bi-person-plus"></i> เพิ่มนักเรียน</a>
</x-page-banner>

<form class="card mb-3" method="GET">
    <div class="card-body d-flex flex-wrap gap-2">
        <div class="input-group" style="max-width:340px">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input name="q" value="{{ request('q') }}" class="form-control" placeholder="ชื่อ ชื่อเล่น รหัส เลขบัตร">
        </div>
        <select name="classroom" class="form-select w-auto" data-autosubmit>
            <option value="">ทุกห้อง</option>
            @foreach ($classrooms as $c)<option value="{{ $c->id }}" @selected(request('classroom') == $c->id)>{{ $c->name() }}</option>@endforeach
        </select>
        <select name="status" class="form-select w-auto" data-autosubmit>
            @foreach (\App\Models\Student::STATUSES as $k => $v)<option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>@endforeach
            <option value="all" @selected($status === 'all')>ทุกสถานะ</option>
        </select>
        <button class="btn btn-primary">ค้นหา</button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-cards table-hover align-middle">
            <thead><tr><th>ห้อง</th><th>เลขที่</th><th>รหัส</th><th>ชื่อ-สกุล</th><th class="d-none d-md-table-cell">ชื่อเล่น</th><th class="d-none d-md-table-cell">เพศ</th><th>สถานะ</th></tr></thead>
            <tbody>
            @forelse ($students as $s)
                <tr data-href="{{ route('students.show', $s) }}" style="cursor:pointer">
                    <td class="fw-semibold">{{ $s->classroom?->name() ?? '-' }}</td>
                    <td>{{ $s->number }}</td>
                    <td class="text-muted">{{ $s->student_code }}</td>
                    <td class="tc-title">
                        <div class="d-flex align-items-center gap-2">
                            <span class="sb-avatar sm">@if($s->photoUrl())<img src="{{ $s->photoUrl() }}" alt="">@else{{ $s->initials() }}@endif</span>
                            <a href="{{ route('students.show', $s) }}" class="text-body fw-semibold text-decoration-none">{{ $s->fullName() }}</a>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell">{{ $s->nickname }}</td>
                    <td class="d-none d-md-table-cell">{{ ['M' => 'ชาย', 'F' => 'หญิง'][$s->gender] ?? '-' }}</td>
                    <td><span class="badge bg-{{ $s->status === 'active' ? 'success' : 'secondary' }}-subtle text-{{ $s->status === 'active' ? 'success' : 'secondary' }}-emphasis">{{ \App\Models\Student::STATUSES[$s->status] ?? $s->status }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty"><i class="bi bi-people"></i>ไม่พบนักเรียน</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $students->links() }}</div>
@endsection
