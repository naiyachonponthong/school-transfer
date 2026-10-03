@extends('layouts.app')
@section('title', 'อนุมัติผลการเรียน')

@section('content')
<div class="page-head">
    <div><h1>อนุมัติผลการเรียน</h1><div class="sub">{{ $term?->label() ?? 'ยังไม่ได้ตั้งภาคเรียน' }} · รอตรวจ {{ $pending->count() }} · ครูยังไม่ส่ง {{ $waiting->count() }} · อนุมัติแล้ว {{ $approved->count() }}</div></div>
    <div class="actions">
        <form method="GET"><select name="term" class="form-select" data-autosubmit aria-label="ภาคเรียน">
            @foreach ($terms as $t)<option value="{{ $t->id }}" @selected($term?->id === $t->id)>{{ $t->label() }}</option>@endforeach
        </select></form>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-hourglass-split text-warning"></i> รอตรวจ</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>รายวิชา</th><th>ห้อง</th><th>ครูผู้สอน</th><th>ส่งเมื่อ</th><th class="text-end"></th></tr></thead>
            <tbody>
            @forelse ($pending as $c)
                <tr>
                    <td><a href="{{ route('gradebook.show', $c) }}">{{ $c->subject->name }}</a><div class="small text-muted">{{ $c->subject->code }} · คะแนนเต็ม {{ rtrim(rtrim(number_format($c->maxTotal(), 2), '0'), '.') }}</div></td>
                    <td>{{ $c->classroom->name() }}</td>
                    <td>{{ $c->teacher?->name ?? '-' }}</td>
                    <td class="small">{{ thai_datetime($c->submitted_at) }}</td>
                    <td class="text-end text-nowrap">
                        <form method="POST" action="{{ route('courses.return', $c) }}" class="d-inline" onsubmit="const n=prompt('สิ่งที่ต้องแก้ไข');if(!n)return false;this.return_note.value=n;">
                            @csrf<input type="hidden" name="return_note">
                            <button class="btn btn-sm btn-light border"><i class="bi bi-arrow-counterclockwise"></i> ตีกลับ</button>
                        </form>
                        <form method="POST" action="{{ route('courses.approve', $c) }}" class="d-inline" data-confirm="อนุมัติและล็อกผลการเรียน {{ $c->subject->name }} {{ $c->classroom->name() }}?">
                            @csrf<button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> อนุมัติ</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty py-4"><i class="bi bi-check2-all"></i>ไม่มีรายวิชารอตรวจ</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-pencil-square"></i> ครูยังไม่ส่ง</div>
            @forelse ($waiting as $c)
                <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom small">
                    <div class="flex-grow-1">{{ $c->subject->name }} <span class="text-muted">{{ $c->classroom->name() }} · {{ $c->teacher?->name ?? 'ยังไม่กำหนดครู' }}</span>
                        @if ($c->return_note)<div class="text-danger">ตีกลับ: {{ $c->return_note }}</div>@endif
                    </div>
                </div>
            @empty
                <div class="empty py-4"><i class="bi bi-check2-all"></i>ส่งครบทุกรายวิชาแล้ว</div>
            @endforelse
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-lock-fill"></i> อนุมัติแล้ว</div>
            @forelse ($approved as $c)
                <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom small">
                    <div class="flex-grow-1">{{ $c->subject->name }} <span class="text-muted">{{ $c->classroom->name() }}</span></div>
                    <a href="{{ route('gradebook.show', $c) }}" class="btn btn-sm btn-light border">สมุดคะแนน</a>
                </div>
            @empty
                <div class="empty py-4"><i class="bi bi-hourglass"></i>ยังไม่มีรายวิชาที่อนุมัติ</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
