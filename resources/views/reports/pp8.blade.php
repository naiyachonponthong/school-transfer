@extends('layouts.app')
@section('title', isset($classroom) ? 'ปพ.8 ห้อง '.$classroom->name() : 'ปพ.8 '.($records[0]['student']->fullName() ?? ''))

@section('content')
@php
    $dash = '-';
    $d = fn ($v) => $v ? thai_date($v, true) : $dash;
    $gpa = fn ($v) => $v === null ? $dash : number_format((float) $v, 2);
@endphp
<div class="d-flex gap-2 mb-3 no-print flex-wrap align-items-center">
    <a href="{{ $back }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
    <div class="text-muted small">ระเบียนสะสม (ปพ.8) {{ isset($classroom) ? 'ห้อง '.$classroom->name().' '.count($records).' คน' : '' }} · รวบรวมจากข้อมูลในระบบ ไม่ต้องกรอกเพิ่ม</div>
    <button onclick="print()" class="btn btn-primary ms-auto"><i class="bi bi-printer"></i> พิมพ์ / บันทึก PDF</button>
</div>

@forelse ($records as $r)
    @php($s = $r['student'])
    <div class="card doc-page mb-4" style="max-width:900px;margin:auto">
        <div class="card-body p-4">
            <div class="text-center mb-3">
                @if (school('logo'))<img src="{{ asset('storage/'.school('logo')) }}" alt="" style="height:60px" class="mb-1">@endif
                <h2 class="h5 fw-bold mb-0">ระเบียนสะสม (ปพ.8)</h2>
                <div>{{ school('school_name') }}@if (school('school_affiliation')) · {{ school('school_affiliation') }}@endif</div>
            </div>

            <div class="d-flex gap-3 align-items-start">
                <table class="table table-sm table-bordered small mb-2 flex-grow-1">
                    <tr><th class="text-nowrap" style="width:22%">ชื่อ-นามสกุล</th><td>{{ $s->fullName() }}{{ $s->nickname ? ' ('.$s->nickname.')' : '' }}</td><th class="text-nowrap" style="width:18%">เลขประจำตัว</th><td>{{ $s->student_code }}</td></tr>
                    <tr><th>เลขประจำตัวประชาชน</th><td>{{ $s->citizen_id ?: $dash }}</td><th>ชั้น / เลขที่</th><td>{{ $s->classroom?->name() ?? $dash }} / {{ $s->number ?: $dash }}</td></tr>
                    <tr><th>วันเกิด</th><td>{{ $d($s->birthdate) }}</td><th>หมู่เลือด</th><td>{{ $s->blood_type ?: $dash }}</td></tr>
                    <tr><th>สัญชาติ / เชื้อชาติ / ศาสนา</th><td colspan="3">{{ $s->nationality ?: $dash }} / {{ $s->ethnicity ?: $dash }} / {{ $s->religion ?: $dash }}</td></tr>
                    <tr><th>บิดา</th><td>{{ $s->father_name ?: $dash }}</td><th>มารดา</th><td>{{ $s->mother_name ?: $dash }}</td></tr>
                    <tr><th>ผู้ปกครอง</th><td colspan="3">{{ $s->guardians->map(fn ($g) => $g->name.($g->pivot->relation ? ' ('.$g->pivot->relation.')' : '').($g->phone ? ' โทร '.$g->phone : ''))->implode(', ') ?: $dash }}</td></tr>
                    <tr><th>ที่อยู่</th><td colspan="3">{{ $s->address ?: $dash }}</td></tr>
                    <tr><th>เข้าเรียนเมื่อ</th><td>{{ $d($s->admitted_on) }}</td><th>โรงเรียนเดิม</th><td>{{ $s->previous_school ?: $dash }}</td></tr>
                    <tr><th>โรคประจำตัว / ข้อควรระวัง</th><td colspan="3">{{ $s->medical_note ?: $dash }}</td></tr>
                </table>
                @if ($s->photo)<img src="{{ asset('storage/'.$s->photo) }}" alt="" style="width:100px;height:125px;object-fit:cover;border:1px solid #ddd">@endif
            </div>

            <h3 class="h6 fw-bold mt-3 mb-2">ผลการเรียน การมาเรียน ความประพฤติ และพัฒนาการรายภาคเรียน</h3>
            @if ($r['rows']->isEmpty())
                <p class="text-muted small">ยังไม่มีข้อมูลรายภาคเรียน</p>
            @else
                <table class="table table-sm table-bordered small text-center align-middle mb-2">
                    <thead class="table-light">
                        <tr>
                            <th rowspan="2" class="text-start">ภาคเรียน</th>
                            <th rowspan="2">GPA</th>
                            <th colspan="4">การมาเรียน (วัน)</th>
                            <th colspan="2">ความประพฤติ</th>
                            <th colspan="2">สุขภาพ</th>
                            <th rowspan="2">คุณลักษณะ<br>อันพึงประสงค์</th>
                            <th rowspan="2">อ่าน คิดวิเคราะห์<br>และเขียน</th>
                        </tr>
                        <tr><th>มา</th><th>สาย</th><th>ขาด</th><th>ลา</th><th>+/-</th><th>คงเหลือ</th><th>น้ำหนัก</th><th>ส่วนสูง</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($r['rows'] as $t)
                            <tr>
                                <td class="text-start text-nowrap">{{ $t['term']->label() }}</td>
                                <td>{{ $gpa($t['gpa']) }}</td>
                                <td>{{ $t['attendance']['present'] }}</td><td>{{ $t['attendance']['late'] }}</td><td>{{ $t['attendance']['absent'] }}</td><td>{{ $t['attendance']['leave'] }}</td>
                                <td>{{ $t['behavior_change'] > 0 ? '+' : '' }}{{ $t['behavior_change'] }}</td><td>{{ $t['behavior_total'] }}</td>
                                <td>{{ $t['weight'] ? rtrim(rtrim(number_format((float) $t['weight'], 1), '0'), '.') : $dash }}</td>
                                <td>{{ $t['height'] ? rtrim(rtrim(number_format((float) $t['height'], 1), '0'), '.') : $dash }}</td>
                                <td>{{ $t['traits'] ?? $dash }}</td><td>{{ $t['rtw'] ?? $dash }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold"><td class="text-start">สรุป</td><td>{{ $gpa($r['gpax']) }}</td><td colspan="4" class="text-start">หน่วยกิตที่ได้ {{ rtrim(rtrim(number_format((float) $r['credits'], 1), '0'), '.') }}</td><td colspan="2">{{ $r['behavior_now'] }}</td><td colspan="4" class="text-start text-muted fw-normal">คะแนนความประพฤติเริ่มต้น {{ \App\Models\Student::BASE_BEHAVIOR }} คะแนน</td></tr>
                    </tfoot>
                </table>
            @endif

            <div class="row g-3 mt-1">
                <div class="col-6">
                    <h3 class="h6 fw-bold mb-1">กิจกรรม ชุมนุม</h3>
                    @php($clubRows = $r['rows']->filter(fn ($t) => $t['clubs']))
                    @if ($clubRows->isEmpty())<p class="small text-muted mb-0">{{ $dash }}</p>@else
                        <ul class="small ps-3 mb-0">@foreach ($clubRows as $t)<li>{{ $t['term']->label() }}: {{ implode(', ', $t['clubs']) }}</li>@endforeach</ul>
                    @endif
                </div>
                <div class="col-6">
                    <h3 class="h6 fw-bold mb-1">ผลงาน รางวัล ความภาคภูมิใจ</h3>
                    @if ($r['awards']->isEmpty())<p class="small text-muted mb-0">{{ $dash }}</p>@else
                        <ul class="small ps-3 mb-0">@foreach ($r['awards'] as $a)<li>{{ trim(($a->headline ? $a->headline.' ' : '').$a->title) }} ({{ thai_date($a->created_at) }})</li>@endforeach</ul>
                    @endif
                </div>
                <div class="col-12">
                    <h3 class="h6 fw-bold mb-1">การเยี่ยมบ้าน</h3>
                    @if ($r['visits']->isEmpty())<p class="small text-muted mb-0">{{ $dash }}</p>@else
                        <ul class="small ps-3 mb-0">
                            @foreach ($r['visits'] as $v)<li>{{ thai_date($v->visited_on, true) }} โดย {{ $v->visitor?->name ?? $dash }}{{ $v->family_status ? ' · ครอบครัว: '.(\App\Models\HomeVisit::FAMILY[$v->family_status] ?? $v->family_status) : '' }}{{ $v->housing ? ' · ที่อยู่อาศัย: '.(\App\Models\HomeVisit::HOUSING[$v->housing] ?? $v->housing) : '' }}</li>@endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="row mt-4 small text-center">
                <div class="col-6">ลงชื่อ ......................................................<br>( {{ $s->classroom?->homeroomTeacher?->name ?? '......................................................' }} )<br>ครูประจำชั้น</div>
                <div class="col-6">ลงชื่อ ......................................................<br>( {{ school('registrar_name') ?: '......................................................' }} )<br>นายทะเบียน</div>
            </div>
            <div class="small text-muted text-end mt-3">พิมพ์จากระบบเมื่อ {{ thai_datetime(now()) }}</div>
        </div>
    </div>
@empty
    <div class="text-center text-muted py-5">ไม่มีนักเรียนในห้องนี้</div>
@endforelse
@endsection
