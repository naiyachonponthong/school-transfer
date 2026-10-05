{{-- ทุนการศึกษาของนักเรียนหนึ่งคน · $all = บุคลากรเห็นทุกสถานะ · ผู้ปกครอง/นักเรียนเห็นเฉพาะทุนที่อนุมัติแล้ว --}}
@php
    $all = $all ?? false;
    $studentAwards = \App\Models\ScholarshipAward::with('scholarship')->where('student_id', $student->id)
        ->when(! $all, fn ($q) => $q->where('status', 'approved'))->latest('id')->get();
@endphp
@if ($studentAwards->isNotEmpty())
    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-mortarboard"></i> ทุนการศึกษา</div>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>ทุน</th><th>ปีการศึกษา</th><th>วิธีมอบ</th><th class="text-end">มูลค่า</th><th>สถานะ</th></tr></thead>
            <tbody>
            @foreach ($studentAwards as $a)
                <tr>
                    <td>@if ($all)<a href="{{ route('scholarships.show', $a->scholarship) }}">{{ $a->scholarship->name }}</a>@else{{ $a->scholarship->name }}@endif<div class="small text-muted">{{ $a->scholarship->donor }}</div></td>
                    <td>{{ $a->scholarship->year }}</td>
                    <td class="small">{{ \App\Models\Scholarship::MODES[$a->scholarship->mode] ?? '' }}</td>
                    <td class="text-end">{{ $a->scholarship->valueLabel() }}</td>
                    <td><span class="badge bg-{{ $a->statusColor() }}">{{ $a->statusLabel() }}</span>@if ($a->paid_at)<div class="small text-muted">รับแล้ว {{ thai_date($a->paid_at) }}</div>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
@endif
