{{-- ปพ.6 ของนักเรียนหนึ่งคน (ใช้ทั้งพิมพ์รายคนและทั้งห้อง) --}}
@php
    $student = $d['student'];
    $ev = $d['evaluation'];
    $m = $d['measurement'];
    $Ev = \App\Support\Evaluation::class;
@endphp
<div class="card doc-page mb-3" style="max-width:860px;margin:auto">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-3">
            @if (school('logo'))<img src="{{ asset('storage/'.school('logo')) }}" alt="" style="height:64px" class="mb-2">@endif
            <h2 class="h5 fw-bold mb-1">แบบรายงานการพัฒนาคุณภาพผู้เรียนรายบุคคล (ปพ.6)</h2>
            <div>{{ school('school_name') }}</div>
            <div class="text-muted small">{{ $term?->label() }}</div>
        </div>

        <div class="row small mb-3 g-1">
            <div class="col-sm-6"><span class="text-muted">ชื่อ-สกุล:</span> <b>{{ $student->fullName() }}</b></div>
            <div class="col-sm-3"><span class="text-muted">รหัส:</span> {{ $student->student_code }}</div>
            <div class="col-sm-3"><span class="text-muted">ชั้น:</span> {{ $student->classroom?->name() }} เลขที่ {{ $student->number }}</div>
            <div class="col-sm-6"><span class="text-muted">ครูประจำชั้น:</span> {{ $student->classroom?->homeroomTeacher?->name ?? '-' }}</div>
        </div>

        <div class="fw-semibold small mb-1">ผลการเรียนรายวิชา</div>
        <table class="table table-bordered table-sm align-middle small mb-2">
            <thead class="table-light">
                <tr class="text-center"><th style="width:90px">รหัสวิชา</th><th>รายวิชา</th><th style="width:70px">หน่วยกิต</th><th style="width:70px">เวลาเรียน (ชม.)</th><th style="width:80px">คะแนน</th><th style="width:80px">ผลการเรียน</th></tr>
            </thead>
            <tbody>
            @forelse ($d['academic'] as $g)
                <tr>
                    <td class="text-center">{{ $g['course']->subject->code }}</td>
                    <td>{{ $g['course']->subject->name }} <span class="text-muted">({{ $g['course']->subject->typeLabel() }})</span></td>
                    <td class="text-center">{{ $g['course']->subject->credit }}</td>
                    <td class="text-center">{{ $g['course']->subject->hours ?? '-' }}</td>
                    <td class="text-center">{{ $g['total'] !== null ? rtrim(rtrim(number_format($g['total'], 2), '0'), '.') : '-' }}</td>
                    <td class="text-center fw-bold text-nowrap">@if($g['original'] !== null && $g['original'] !== $g['grade'])<span class="fw-normal text-muted text-decoration-line-through me-1">{{ $g['original'] }}</span>@endif{{ $g['grade'] ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-3">ยังไม่มีผลการเรียน</td></tr>
            @endforelse
            </tbody>
            <tfoot>
                <tr class="fw-semibold"><td colspan="2" class="text-end">หน่วยกิตที่ลงทะเบียน / ที่ได้</td><td class="text-center">{{ $d['credits'] }}</td><td class="text-center">{{ $d['earned'] }}</td><td class="text-end">ผลการเรียนเฉลี่ย</td><td class="text-center fs-6">{{ $d['gpa'] !== null ? number_format($d['gpa'], 2) : '-' }}</td></tr>
            </tfoot>
        </table>

        @if ($d['activities']->isNotEmpty())
            <div class="fw-semibold small mb-1 mt-3">กิจกรรมพัฒนาผู้เรียน</div>
            <table class="table table-bordered table-sm align-middle small mb-2">
                <thead class="table-light"><tr class="text-center"><th>กิจกรรม</th><th style="width:110px">เวลา (ชม.)</th><th style="width:110px">ผลการประเมิน</th></tr></thead>
                <tbody>
                @foreach ($d['activities'] as $g)
                    <tr>
                        <td>{{ $g['course']->subject->name }} @if($g['course']->subject->activityKindLabel())<span class="text-muted">({{ $g['course']->subject->activityKindLabel() }})</span>@endif</td>
                        <td class="text-center">{{ $g['course']->subject->hours ?? '-' }}</td>
                        <td class="text-center fw-bold text-nowrap">@if($g['original'] !== null && $g['original'] !== $g['grade'])<span class="fw-normal text-muted text-decoration-line-through me-1">{{ $g['original'] }}</span>@endif{{ $g['grade'] ?? '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        <div class="row g-3 small mt-1">
            <div class="col-sm-7">
                <div class="fw-semibold mb-1">คุณลักษณะอันพึงประสงค์</div>
                <table class="table table-bordered table-sm mb-0">
                    @foreach ($Ev::TRAITS as $no => $name)
                        <tr><td>{{ $no }}. {{ $name }}</td><td class="text-center" style="width:90px">{{ $Ev::label($ev?->trait($no)) }}</td></tr>
                    @endforeach
                    <tr class="fw-semibold"><td class="text-end">สรุปผลการประเมิน</td><td class="text-center">{{ $Ev::label($ev?->traitsSummary()) }}</td></tr>
                </table>
            </div>
            <div class="col-sm-5">
                <div class="fw-semibold mb-1">การอ่าน คิดวิเคราะห์ และเขียน</div>
                <div class="border rounded p-2 text-center fw-bold mb-3">{{ $Ev::label($ev?->rtw) }}</div>

                <div class="fw-semibold mb-1">เวลามาเรียน</div>
                <table class="table table-bordered table-sm text-center mb-1">
                    <tr>@foreach (\App\Models\Attendance::STATUSES as [$label])<th class="fw-normal">{{ $label }}</th>@endforeach</tr>
                    <tr>@foreach (array_keys(\App\Models\Attendance::STATUSES) as $k)<td>{{ $d['attendance'][$k] ?? 0 }}</td>@endforeach</tr>
                </table>
                <div class="mb-3">รวม {{ $d['days'] }} วัน{{ $d['attendancePercent'] !== null ? ' · มาเรียนร้อยละ '.$d['attendancePercent'] : '' }}</div>

                <div class="d-flex gap-3">
                    <div class="flex-fill"><div class="fw-semibold mb-1">น้ำหนัก / ส่วนสูง</div><div class="border rounded p-2 text-center">{{ $m ? rtrim(rtrim(number_format($m->weight, 1), '0'), '.').' กก. / '.rtrim(rtrim(number_format($m->height, 1), '0'), '.').' ซม.' : '-' }}</div></div>
                    <div><div class="fw-semibold mb-1">ความประพฤติ</div><div class="border rounded p-2 text-center fw-bold">{{ $d['behavior'] }} / {{ \App\Models\Student::BASE_BEHAVIOR }}</div></div>
                </div>
            </div>
        </div>

        <div class="small mt-3">
            <div class="fw-semibold">ความคิดเห็นของครูประจำชั้น</div>
            <div class="text-muted">..........................................................................................................................................................................................</div>
            <div class="fw-semibold mt-2">ความคิดเห็นของผู้ปกครอง</div>
            <div class="text-muted">..........................................................................................................................................................................................</div>
        </div>

        <div class="row g-3 mt-4">
            <x-sign class="col-4" role="ครูประจำชั้น" :name="$student->classroom?->homeroomTeacher?->name" />
            <x-sign class="col-4" role="ผู้อำนวยการโรงเรียน" :name="school('director_name')" />
            <x-sign class="col-4" role="ผู้ปกครอง" />
        </div>
    </div>
</div>
