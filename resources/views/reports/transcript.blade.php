@extends('layouts.app')
@section('title', $info['code'].' '.$student->fullName())

@section('content')
@php
    $byCredit = $info['credits'];
    $unit = $byCredit ? 'หน่วยกิต' : 'ชม.';
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.');
    $dash = '-';
    $Ev = \App\Support\Evaluation::class;
    $latest = $issues->first();
    $graduated = $student->status === 'graduated';
@endphp
<div class="d-flex flex-wrap gap-2 mb-3 no-print">
    <a href="{{ url()->previous() }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
    @if ($stages->count() > 1)
        <div class="btn-group">
            @foreach ($stages as $s)
                <a href="{{ route('transcript', ['student' => $student, 'stage' => $s]) }}" class="btn btn-sm {{ $s === $stage ? 'btn-dark' : 'btn-light border' }}">{{ \App\Support\Curriculum::STAGES[$s]['code'] }}</a>
            @endforeach
        </div>
    @endif
    <button onclick="print()" class="btn btn-primary ms-auto"><i class="bi bi-printer"></i> พิมพ์ / บันทึก PDF</button>
</div>

@if (auth()->user()->isAdmin())
    <div class="card mb-3 no-print" style="max-width:960px;margin:auto">
        <div class="card-body">
            <div class="fw-semibold mb-2"><i class="bi bi-journal-bookmark"></i> ทะเบียนคุมแบบพิมพ์ ปพ.1 ฉบับจริง</div>
            @foreach ($issues as $i)
                <div class="small text-muted">ชุดที่ {{ $i->form_series }} เลขที่ {{ $i->form_number }} · ออกเมื่อ {{ thai_date($i->issued_on) }} ({{ $i->purpose }})</div>
            @endforeach
            <form method="POST" action="{{ route('transcript.issue', $student) }}" class="row g-2 align-items-end mt-1">
                @csrf
                <input type="hidden" name="stage" value="{{ $stage }}">
                <div class="col-sm-2"><label class="form-label small">ชุดที่</label><input name="form_series" class="form-control form-control-sm" required></div>
                <div class="col-sm-2"><label class="form-label small">เลขที่</label><input name="form_number" class="form-control form-control-sm" required></div>
                <div class="col-sm-5"><label class="form-label small">หมายเหตุ</label><input name="purpose" class="form-control form-control-sm" placeholder="เช่น จบการศึกษา / ขอฉบับใหม่แทนฉบับที่ชำรุด"></div>
                <div class="col-sm-3"><button class="btn btn-sm btn-soft w-100">บันทึกการออกฉบับจริง</button></div>
            </form>
            <div class="small text-muted mt-2">ฉบับจริงต้องพิมพ์ลงแบบพิมพ์ ปพ.1 ของกระทรวงที่มีชุดที่/เลขที่ควบคุม · หน้านี้คือฉบับตรวจสอบที่เนื้อหาครบตามแบบ</div>
        </div>
    </div>
@endif

<div class="card doc-page transcript" style="max-width:960px;margin:auto">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between small">
            <span>{{ $info['code'] }}</span>
            <span>ชุดที่ {{ $latest?->form_series ?? '..........' }} เลขที่ {{ $latest?->form_number ?? '..........' }}</span>
        </div>
        <div class="text-center mb-2">
            @if (school('logo'))<img src="{{ asset('storage/'.school('logo')) }}" alt="" style="height:56px" class="mb-1">@endif
            <h2 class="h6 fw-bold mb-0">ระเบียนแสดงผลการเรียน{{ \App\Support\Curriculum::NAME }}</h2>
            <div class="fw-semibold">ระดับ{{ $info['name'] }}</div>
        </div>

        {{-- ข้อมูลสถานศึกษา / นักเรียน --}}
        <div class="d-flex gap-3 small mb-2">
            <table class="table table-bordered table-sm mb-0 flex-grow-1">
                <tr><td class="text-muted" style="width:20%">ชื่อสถานศึกษา</td><td style="width:30%">{{ school('school_name') }}</td><td class="text-muted" style="width:20%">ชื่อ-ชื่อสกุล</td><td><b>{{ $student->fullName() }}</b></td></tr>
                <tr><td class="text-muted">สังกัด</td><td>{{ school('school_affiliation') ?: $dash }}</td><td class="text-muted">เลขประจำตัวประชาชน</td><td>{{ $student->citizen_id ?: $dash }}</td></tr>
                <tr><td class="text-muted">ตำบล/อำเภอ</td><td>{{ school('school_address') ?: $dash }}</td><td class="text-muted">เลขประจำตัวนักเรียน</td><td>{{ $student->student_code }}</td></tr>
                <tr><td class="text-muted">จังหวัด</td><td>{{ school('school_province') ?: $dash }}</td><td class="text-muted">วัน เดือน ปีเกิด</td><td>{{ $student->birthdate ? thai_date($student->birthdate, true) : $dash }}</td></tr>
                <tr><td class="text-muted">วันเข้าเรียน</td><td>{{ $student->admitted_on ? thai_date($student->admitted_on, true) : $dash }}</td><td class="text-muted">เพศ / สัญชาติ / ศาสนา</td><td>{{ ['M' => 'ชาย', 'F' => 'หญิง'][$student->gender] ?? $dash }} / {{ $student->nationality ?: $dash }} / {{ $student->religion ?: $dash }}</td></tr>
                <tr><td class="text-muted">สถานศึกษาเดิม</td><td>{{ $student->previous_school ?: $dash }}{{ $student->previous_school_province ? ' จ.'.$student->previous_school_province : '' }}</td><td class="text-muted">ชื่อ-ชื่อสกุลบิดา</td><td>{{ $student->father_name ?: $dash }}</td></tr>
                <tr><td class="text-muted">ชั้นเรียนสุดท้าย</td><td>{{ $student->previous_level ?: $dash }}</td><td class="text-muted">ชื่อ-ชื่อสกุลมารดา</td><td>{{ $student->mother_name ?: $dash }}</td></tr>
            </table>
            <div class="border d-flex align-items-center justify-content-center text-muted flex-shrink-0" style="width:2.6cm;height:3.4cm;overflow:hidden">
                @if ($student->photoUrl())<img src="{{ $student->photoUrl() }}" alt="" style="width:100%;height:100%;object-fit:cover">@else รูปถ่าย @endif
            </div>
        </div>

        {{-- ผลการเรียนรายช่วง (มัธยม = รายภาค · ประถม = รายปี) --}}
        <div class="fw-semibold small mb-1">ผลการเรียนรายวิชา</div>
        @php($periods = $record->periods())
        <div class="row g-2 mb-2">
            @forelse ($periods as $p)
                <div class="col-6">
                    <table class="table table-bordered table-sm small mb-0">
                        <thead class="table-light"><tr><th colspan="2">{{ $p['label'] }}</th><th class="text-center" style="width:52px">{{ $byCredit ? 'นก.' : 'ชม.' }}</th><th class="text-center" style="width:46px">ผล</th></tr></thead>
                        <tbody>
                        @foreach ($p['rows'] as $r)
                            <tr>
                                <td style="width:70px">{{ $r['course']->subject->code }}</td>
                                <td>{{ $r['course']->subject->name }}</td>
                                <td class="text-center">{{ $r['course']->isActivity() ? ($r['hours'] ?: '') : ($byCredit ? $num($r['credit']) : ($r['hours'] ?: '')) }}</td>
                                <td class="text-center fw-semibold">{{ $r['grade'] ?? '' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @empty
                <div class="col-12 text-center text-muted small py-3">ยังไม่มีผลการเรียนในระดับนี้</div>
            @endforelse
        </div>

        {{-- สรุป --}}
        <div class="row g-2 small">
            <div class="col-6">
                <table class="table table-bordered table-sm mb-2">
                    <thead class="table-light"><tr><th>กลุ่มสาระการเรียนรู้</th><th class="text-center" style="width:70px">{{ $unit }}</th><th class="text-center" style="width:80px">ผลการเรียนเฉลี่ย</th></tr></thead>
                    @foreach ($record->byGroup() as $group => $g)
                        <tr><td>{{ $group }}</td><td class="text-center">{{ $g['amount'] ? $num($g['amount']) : '' }}</td><td class="text-center">{{ $g['gpa'] !== null ? number_format($g['gpa'], 2) : '' }}</td></tr>
                    @endforeach
                </table>
                <table class="table table-bordered table-sm mb-0">
                    <thead class="table-light"><tr><th>สรุป{{ $unit === 'ชม.' ? 'เวลาเรียน (ชม.)' : 'หน่วยกิต' }}</th><th class="text-center">ที่เรียน</th><th class="text-center">ที่ได้</th></tr></thead>
                    <tr><td>รายวิชาพื้นฐาน</td><td class="text-center">{{ $num($totals['basic']['taken']) }}</td><td class="text-center">{{ $num($totals['basic']['earned']) }}</td></tr>
                    <tr><td>รายวิชาเพิ่มเติม</td><td class="text-center">{{ $num($totals['extra']['taken']) }}</td><td class="text-center">{{ $num($totals['extra']['earned']) }}</td></tr>
                    <tr class="fw-semibold"><td>รวม</td><td class="text-center">{{ $num($totals['total']['taken']) }}</td><td class="text-center">{{ $num($totals['total']['earned']) }}</td></tr>
                    <tr class="fw-bold"><td colspan="2">ผลการเรียนเฉลี่ยตลอดหลักสูตร (GPAX)</td><td class="text-center fs-6">{{ $gpax !== null ? number_format($gpax, 2) : '-' }}</td></tr>
                </table>
            </div>
            <div class="col-6">
                <table class="table table-bordered table-sm mb-2">
                    <thead class="table-light"><tr><th>กิจกรรมพัฒนาผู้เรียน</th><th class="text-center" style="width:60px">ชม.</th><th class="text-center" style="width:60px">ผล</th></tr></thead>
                    @foreach ($record->activitySummary() as $a)
                        <tr><td>{{ $a['label'] }}</td><td class="text-center">{{ $a['hours'] ?: '' }}</td><td class="text-center">{{ $a['result'] ?? '' }}</td></tr>
                    @endforeach
                </table>
                <table class="table table-bordered table-sm mb-2">
                    <tr><td>ผลการประเมินการอ่าน คิดวิเคราะห์ และเขียน</td><td class="text-center fw-semibold" style="width:80px">{{ $Ev::label($evaluation?->rtw) }}</td></tr>
                    <tr><td>ผลการประเมินคุณลักษณะอันพึงประสงค์</td><td class="text-center fw-semibold">{{ $Ev::label($evaluation?->traitsSummary()) }}</td></tr>
                </table>
                <table class="table table-bordered table-sm mb-0">
                    <tr><td style="width:45%">ผลการตัดสิน</td><td>{{ $graduated && $record->eligible() ? $info['graduate'] : ($student->status === 'active' ? 'กำลังศึกษา' : (\App\Models\Student::STATUSES[$student->status] ?? $dash)) }}</td></tr>
                    <tr><td>วันอนุมัติการจบ</td><td>{{ $graduated && $student->left_on ? thai_date($student->left_on, true) : $dash }}</td></tr>
                    <tr><td>วันที่ออกจากสถานศึกษา / สาเหตุ</td><td>{{ ! $graduated && $student->left_on ? thai_date($student->left_on, true).($student->leave_reason ? ' / '.$student->leave_reason : '') : $dash }}</td></tr>
                </table>
            </div>
        </div>

        <div class="row g-3 mt-3">
            <x-sign class="col-6" role="นายทะเบียน" :name="school('registrar_name')" />
            <x-sign class="col-6" role="ผู้อำนวยการ{{ school('school_name') }}" :name="school('director_name')">
                <div>วันที่ {{ thai_date(today(), true) }}</div>
            </x-sign>
        </div>
        <div class="small text-muted mt-2 text-center">ฉบับตรวจสอบจากระบบ · ฉบับจริงพิมพ์ลงแบบพิมพ์ ปพ.1 ของกระทรวงศึกษาธิการ</div>
    </div>
</div>
@endsection
