{{-- ปฐ.03 สมุดรายงานประจำตัวเด็กปฐมวัย ของเด็กหนึ่งคน (ใช้ทั้งพิมพ์รายคนและทั้งห้อง) --}}
@php
    $student = $d['student'];
    $a = $d['preschool'];
    $m = $d['measurement'];
    $P = \App\Support\Preschool::class;
    $standards = $P::standards();
    $domains = $a?->domains() ?? array_fill_keys(array_keys($P::DOMAINS), null);
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
@endphp
<div class="card doc-page mb-3" style="max-width:860px;margin:auto">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-3">
            @if (school('logo'))<img src="{{ asset('storage/'.school('logo')) }}" alt="" style="height:64px" class="mb-2">@endif
            <h2 class="h5 fw-bold mb-1">สมุดรายงานประจำตัวเด็กปฐมวัย (ปฐ.03)</h2>
            <div>{{ school('school_name') }}</div>
            <div class="text-muted small">{{ $term?->label() }}</div>
        </div>

        <div class="row small mb-3 g-1">
            <div class="col-sm-6"><span class="text-muted">ชื่อ-สกุล:</span> <b>{{ $student->fullName() }}</b></div>
            <div class="col-sm-3"><span class="text-muted">รหัส:</span> {{ $student->student_code }}</div>
            <div class="col-sm-3"><span class="text-muted">ชั้น:</span> {{ $student->classroom?->name() }} เลขที่ {{ $student->number }}</div>
            <div class="col-sm-6"><span class="text-muted">ครูประจำชั้น:</span> {{ $student->classroom?->homeroomTeacher?->name ?? '-' }}</div>
            @if ($student->birthdate)<div class="col-sm-6"><span class="text-muted">วันเกิด:</span> {{ thai_date($student->birthdate) }}</div>@endif
        </div>

        <div class="fw-semibold small mb-1">ผลการประเมินพัฒนาการ</div>
        <table class="table table-bordered table-sm align-middle small mb-2">
            <thead class="table-light">
                <tr class="text-center"><th>มาตรฐานคุณลักษณะที่พึงประสงค์</th>@foreach ($P::LEVELS as $v => $l)<th style="width:74px">{{ $l }}<br><span class="fw-normal">({{ $v }})</span></th>@endforeach</tr>
            </thead>
            <tbody>
            @foreach ($P::DOMAINS as $dkey => [$dname, $nos])
                <tr class="table-light"><td colspan="{{ count($P::LEVELS) + 1 }}" class="fw-semibold">{{ $dname }}<span class="float-end fw-normal">สรุป: <b>{{ $P::label($domains[$dkey]) }}</b></span></td></tr>
                @foreach ($nos as $no)
                    @php($lv = $a?->level($no))
                    <tr>
                        <td>มาตรฐานที่ {{ $no }} {{ $standards[$no] }}</td>
                        @foreach (array_keys($P::LEVELS) as $v)<td class="text-center fw-bold">{{ $lv === $v ? '✓' : '' }}</td>@endforeach
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table>

        <div class="row g-3 small mt-1">
            <div class="col-sm-7">
                <div class="fw-semibold mb-1">สรุปพัฒนาการ 4 ด้าน</div>
                <table class="table table-bordered table-sm mb-0">
                    @foreach ($P::DOMAINS as $dkey => [$dname])
                        <tr><td>{{ $dname }}</td><td class="text-center fw-bold" style="width:110px">{{ $P::label($domains[$dkey]) }}</td></tr>
                    @endforeach
                </table>
            </div>
            <div class="col-sm-5">
                <div class="fw-semibold mb-1">เวลามาเรียน</div>
                <table class="table table-bordered table-sm text-center mb-1">
                    <tr>@foreach (\App\Models\Attendance::STATUSES as [$label])<th class="fw-normal">{{ $label }}</th>@endforeach</tr>
                    <tr>@foreach (array_keys(\App\Models\Attendance::STATUSES) as $k)<td>{{ $d['attendance'][$k] ?? 0 }}</td>@endforeach</tr>
                </table>
                <div class="mb-3">รวม {{ $d['days'] }} วัน{{ $d['attendancePercent'] !== null ? ' · มาเรียนร้อยละ '.$d['attendancePercent'] : '' }}</div>
                <div class="fw-semibold mb-1">น้ำหนัก / ส่วนสูง</div>
                <div class="border rounded p-2 text-center">{{ $m ? $num($m->weight).' กก. / '.$num($m->height).' ซม.' : '-' }}</div>
            </div>
        </div>
        <div class="small mt-3">
            <div class="fw-semibold">ความคิดเห็นของครูประจำชั้น</div>
            @if ($a?->comment)<div>{{ $a->comment }}</div>@else<div class="text-muted">..........................................................................................................................................................................................</div>@endif
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
