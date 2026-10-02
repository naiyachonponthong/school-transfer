@extends('layouts.app')
@section('title', 'ปพ.3 '.$level.' ปีการศึกษา '.$year)

@push('head')
<style media="print">@page { size: A4 landscape; margin: 10mm; }</style>
@endpush

@section('content')
@php
    $byCredit = $info['credits'];
    $eligible = $rows->filter(fn ($r) => $r['record']->eligible());
    $graduated = $rows->filter(fn ($r) => $r['student']->status === 'graduated');
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.');
@endphp
<div class="page-head no-print">
    <div><h1>รายงานผู้สำเร็จการศึกษา (ปพ.3)</h1><div class="sub">ตรวจเกณฑ์การจบ · อนุมัติการจบ · พิมพ์รายงาน · ส่งออกไฟล์สำหรับกรอก ปพ.3 ออนไลน์</div></div>
    <div class="actions">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-light border"><i class="bi bi-filetype-csv"></i> ส่งออก CSV</a>
        <button onclick="print()" class="btn btn-primary"><i class="bi bi-printer"></i> พิมพ์</button>
    </div>
</div>

<form method="GET" class="card mb-3 no-print">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <div><label class="form-label">ชั้น</label><select name="level" class="form-select" data-autosubmit>@foreach ($finals as $f)<option @selected($f === $level)>{{ $f }}</option>@endforeach</select></div>
        <div><label class="form-label">ปีการศึกษา</label><select name="year" class="form-select" data-autosubmit>@foreach ($years->push($year)->unique()->sortDesc() as $y)<option @selected((int) $y === $year)>{{ $y }}</option>@endforeach</select></div>
        <div><label class="form-label">วันอนุมัติการจบ</label><input type="date" name="approved_on" value="{{ $approvedOn->toDateString() }}" class="form-control" data-autosubmit></div>
        <div class="ms-auto small text-muted text-end">
            นักเรียน {{ $rows->count() }} คน · ผ่านเกณฑ์ {{ $eligible->count() }} · อนุมัติจบแล้ว {{ $graduated->count() }}<br>
            @if ($info['min'])เกณฑ์: พื้นฐาน {{ $info['min']['basic'] }} + เพิ่มเติม ≥ {{ $info['min']['extra'] }} รวม ≥ {{ $info['min']['total'] }} หน่วยกิต · @endif ไม่มีผลค้าง · อ่านคิดเขียนและคุณลักษณะ "ผ่าน" ขึ้นไป
        </div>
    </div>
</form>

<form method="POST" action="{{ route('graduates.approve') }}" id="approveForm" data-confirm="อนุมัติการจบให้นักเรียนที่เลือก ลงวันที่ {{ thai_date($approvedOn, true) }}?">
    @csrf
    <input type="hidden" name="level" value="{{ $level }}"><input type="hidden" name="year" value="{{ $year }}"><input type="hidden" name="approved_on" value="{{ $approvedOn->toDateString() }}">

    <div class="card doc-page">
        <div class="card-body p-3">
            <div class="text-center mb-2">
                <h2 class="h6 fw-bold mb-0">แบบรายงานผู้สำเร็จการศึกษา (ปพ.3)</h2>
                <div class="small">{{ \App\Support\Curriculum::NAME }} · ระดับ{{ $info['name'] }} ({{ $info['graduate'] }}) · ปีการศึกษา {{ $year }}</div>
                <div class="small">{{ school('school_name') }} · สังกัด {{ school('school_affiliation') ?: '..........' }} · จังหวัด {{ school('school_province') ?: '..........' }} · วันอนุมัติการจบ {{ thai_date($approvedOn, true) }}</div>
            </div>
            <div class="table-responsive">
                <table class="table table-cards table-bordered table-sm small align-middle mb-2">
                    <thead class="table-light text-center">
                        <tr>
                            <th class="no-print" style="width:30px"><input type="checkbox" class="form-check-input" data-check-all=".pick-grad" aria-label="เลือกทุกคนที่ผ่านเกณฑ์"></th>
                            <th style="width:36px">ที่</th><th>เลขประจำตัว</th><th>เลขประจำตัวประชาชน</th><th>ชื่อ-ชื่อสกุล</th><th>เพศ</th><th>วันเกิด</th>
                            <th>{{ $byCredit ? 'หน่วยกิต' : 'เวลาเรียน (ชม.)' }}<div class="fw-normal">ที่เรียน/ที่ได้</div></th>
                            <th>ผลการเรียนเฉลี่ย</th><th style="min-width:300px">ผลการตัดสิน</th><th>ปพ.1 ชุด/เลขที่</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($rows->values() as $i => $r)
                        @php($s = $r['student'])
                        @php($rec = $r['record'])
                        @php($t = $rec->totals()['total'])
                        @php($ok = $rec->eligible())
                        <tr>
                            <td class="no-print text-center">@if ($ok)<input type="checkbox" name="student_ids[]" value="{{ $s->id }}" class="form-check-input pick-grad" @checked($s->status !== 'graduated') aria-label="เลือก {{ $s->fullName() }}">@endif</td>
                            <td class="text-center">{{ $i + 1 }}</td>
                            <td class="text-center">{{ $s->student_code }}</td>
                            <td class="text-center">{{ $s->citizen_id ?: '-' }}</td>
                            <td class="text-nowrap"><a href="{{ route('transcript', ['student' => $s, 'stage' => $stage]) }}" class="text-reset">{{ $s->fullName() }}</a></td>
                            <td class="text-center">{{ ['M' => 'ช', 'F' => 'ญ'][$s->gender] ?? '' }}</td>
                            <td class="text-center text-nowrap">{{ $s->birthdate ? thai_date($s->birthdate) : '-' }}</td>
                            <td class="text-center">{{ $num($t['taken']) }}/{{ $num($t['earned']) }}</td>
                            <td class="text-center fw-semibold">{{ $rec->gpax() !== null ? number_format($rec->gpax(), 2) : '-' }}</td>
                            <td>
                                @if ($ok)
                                    <span class="fw-semibold text-success">จบ</span>@if ($s->status === 'graduated') <span class="small text-muted">(อนุมัติ{{ $s->left_on ? ' '.thai_date($s->left_on) : '' }})</span>@endif
                                @else
                                    <span class="fw-semibold text-danger">ไม่จบ</span>
                                    <div class="small text-muted no-print">{{ collect($rec->graduationChecks())->reject(fn ($c) => $c['ok'])->pluck('label')->implode(' · ') }}</div>
                                @endif
                            </td>
                            <td class="text-center text-nowrap">{{ $r['issue'] ? $r['issue']->form_series.'/'.$r['issue']->form_number : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center text-muted py-4">ไม่มีนักเรียนชั้น {{ $level }} ปีการศึกษา {{ $year }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="small mb-3">
                รวม {{ $rows->count() }} คน (ชาย {{ $rows->where('student.gender', 'M')->count() }} หญิง {{ $rows->where('student.gender', 'F')->count() }})
                · จบ {{ $eligible->count() }} คน · ไม่จบ {{ $rows->count() - $eligible->count() }} คน
            </div>
            <div class="row g-3">
                <x-sign class="col-6" role="นายทะเบียน" :name="school('registrar_name')" />
                <x-sign class="col-6" role="ผู้อำนวยการ{{ school('school_name') }} (ผู้อนุมัติ)" :name="school('director_name')" />
            </div>
        </div>
    </div>

    @if ($eligible->isNotEmpty())
        <div class="d-flex gap-2 align-items-center mt-3 no-print">
            <button class="btn btn-success"><i class="bi bi-mortarboard"></i> อนุมัติการจบคนที่เลือก</button>
            <span class="small text-muted">เปลี่ยนสถานะเป็น "จบการศึกษา" และบันทึกวันอนุมัติการจบ (ใช้ใน ปพ.1 และ ปพ.7) · เลือกได้เฉพาะคนที่ผ่านเกณฑ์</span>
        </div>
    @endif
</form>
@endsection
