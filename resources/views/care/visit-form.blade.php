@extends('layouts.app')
@section('title', 'เยี่ยมบ้าน '.$student->fullName())

@push('head')
<style>
    /* แบบบันทึกการเยี่ยมบ้าน: ใช้ความกว้างเต็มพื้นที่ทำงาน หัวข้อเรียงตามแบบ 4 หน้า */
    .hv-opts { display: flex; flex-wrap: wrap; gap: .35rem 1.25rem; }
    .hv-opts label { display: inline-flex; align-items: flex-start; gap: .4rem; font-size: .9rem; }
    .hv-opts.cols label { flex: 1 1 260px; }
    .hv-q { font-weight: 600; font-size: .9rem; margin-bottom: .35rem; }
    .hv-members { table-layout: fixed; min-width: 900px; }
    .table.hv-table thead th { font-size: .78rem; font-weight: 600; text-align: center; vertical-align: middle; background: var(--sb-primary-50); white-space: normal; }
    .hv-table td { padding: .2rem; }
    .hv-table input { min-width: 0; }
    .hv-savebar { position: sticky; bottom: 0; z-index: 5; background: var(--sb-card); border-top: 1px solid var(--sb-border); }
    @media (max-width: 991.98px) { .hv-savebar { position: static; } } /* จอเล็กมีแถบเมนูล่างอยู่แล้ว */
</style>
@endpush

@section('content')
@php
    $F = \App\Support\HomeVisitForm::class;
    $form = old('form', $visit->form ?? []);
    $guardian = $student->guardians->first();
    // ครั้งแรกเติมข้อมูลผู้ปกครองจากทะเบียนให้ก่อน
    if (empty($visit->form) && ! old('form') && $guardian) {
        [$gFirst, $gLast] = array_pad(explode(' ', preg_replace('/^(นางสาว|นาย|นาง)\s*/u', '', $guardian->name), 2), 2, '');
        $form += ['guardian_first' => $gFirst, 'guardian_last' => $gLast, 'guardian_phone' => $guardian->phone, 'guardian_relation' => $guardian->pivot->relation];
    }
    $val = fn (string $key, $default = '') => $form[$key] ?? $default;
    $members = array_pad($form['members'] ?? [], $F::MEMBER_ROWS, []);
@endphp

<div class="page-head">
    <div><h1>บันทึกการเยี่ยมบ้าน</h1><div class="sub">{{ $student->fullName() }} · {{ $student->classroom?->name() }} · {{ $term->label() }} · แบบบันทึกนี้รวมการคัดกรองนักเรียนยากจน</div></div>
    <div class="actions">
        <a href="{{ route('care.visits.print', $student) }}" target="_blank" class="btn btn-light border" title="พิมพ์ตามแบบ 4 หน้า จากข้อมูลที่บันทึกแล้ว"><i class="bi bi-printer"></i> พิมพ์แบบ</a>
        <a href="{{ route('care.visits', ['classroom' => $student->classroom_id]) }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
    </div>
</div>

@if ($errors->any())<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>@endif

{{-- ตัวเลือกข้อเดียว (○) / หลายข้อ (☐) --}}
@php
    $single = function (string $key, string $class = '') use ($F, $form) {
        $html = '<div class="hv-opts '.$class.'">';
        foreach ($F::SINGLE[$key] as $v => $label) {
            $html .= '<label><input type="radio" class="form-check-input" name="form['.$key.']" value="'.$v.'" '.(($form[$key] ?? null) === $v ? 'checked' : '').'> '.e($label).'</label>';
        }
        return new \Illuminate\Support\HtmlString($html.'</div>');
    };
    $multi = function (string $key, string $class = 'cols') use ($F, $form) {
        $html = '<div class="hv-opts '.$class.'">';
        foreach ($F::MULTI[$key] as $v => $label) {
            $html .= '<label><input type="checkbox" class="form-check-input" name="form['.$key.'][]" value="'.$v.'" '.(in_array($v, $form[$key] ?? [], true) ? 'checked' : '').'> '.e($label).'</label>';
        }
        return new \Illuminate\Support\HtmlString($html.'</div>');
    };
@endphp

<form method="POST" action="{{ route('care.visits.save', $student) }}" enctype="multipart/form-data" id="hvForm">
    @csrf
    <div class="row g-3">
        {{-- ข้อ 1–2 --}}
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-person-vcard"></i> 1–2. นักเรียนและผู้ปกครอง</div>
                <div class="card-body row g-3">
                    <div class="col-md-4"><label class="form-label">ชื่อ-นามสกุลนักเรียน</label><input class="form-control" value="{{ $student->fullName() }}" disabled></div>
                    <div class="col-md-2"><label class="form-label">ชั้น</label><input class="form-control" value="{{ $student->classroom?->name() }}" disabled></div>
                    <div class="col-md-3"><label class="form-label">เลขที่บัตรประชาชน</label><input class="form-control" value="{{ $student->citizen_id ?: '-' }}" disabled></div>
                    <div class="col-md-3"><label class="form-label">วันที่เยี่ยม</label><input type="date" name="visited_on" value="{{ old('visited_on', $visit->visited_on?->toDateString()) }}" max="{{ today()->toDateString() }}" class="form-control @error('visited_on') is-invalid @enderror" required></div>

                    <div class="col-md-3"><label class="form-label">ชื่อผู้ปกครอง</label><input name="form[guardian_first]" value="{{ $val('guardian_first') }}" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label">นามสกุล</label><input name="form[guardian_last]" value="{{ $val('guardian_last') }}" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label">เบอร์โทรศัพท์</label><input name="form[guardian_phone]" value="{{ $val('guardian_phone') }}" class="form-control" inputmode="tel"></div>
                    <div class="col-md-3 d-flex align-items-end"><label class="small"><input type="checkbox" class="form-check-input" name="form[no_guardian]" value="1" @checked($val('no_guardian', false))> ไม่มีผู้ปกครอง</label></div>

                    <div class="col-md-4"><label class="form-label">ความสัมพันธ์กับนักเรียน</label><input name="form[guardian_relation]" value="{{ $val('guardian_relation') }}" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">อาชีพ</label><input name="form[guardian_occupation]" value="{{ $val('guardian_occupation') }}" class="form-control"></div>
                    <div class="col-md-4"><label class="form-label">การศึกษาสูงสุด</label><input name="form[guardian_education]" value="{{ $val('guardian_education') }}" class="form-control"></div>

                    <div class="col-md-4"><label class="form-label">เลขที่บัตรประชาชนผู้ปกครอง</label><input name="form[guardian_citizen_id]" value="{{ $val('guardian_citizen_id') }}" class="form-control" inputmode="numeric" maxlength="13"></div>
                    <div class="col-md-8 d-flex align-items-end">
                        <div class="hv-opts">
                            <label><input type="checkbox" class="form-check-input" name="form[no_id_card]" value="1" @checked($val('no_id_card', false))> ไม่มีบัตรประจำตัวประชาชน</label>
                            <label><input type="checkbox" class="form-check-input" name="form[welfare_registered]" value="1" @checked($val('welfare_registered', false))> เคยลงทะเบียนเพื่อสวัสดิการแห่งรัฐ (ลงทะเบียนคนจน)</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ข้อ 4 --}}
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-house"></i> 4. สถานะของครัวเรือน <span class="ms-2 small text-muted fw-normal">เฉพาะบุคคลที่อาศัยในบ้านปัจจุบัน</span></div>
                <div class="card-body">
                    <div class="hv-q">4.1 ครัวเรือนมีภาระพึ่งพิง</div>{{ $multi('dependents') }}
                    <div class="hv-q mt-3">4.2 ประเภทที่อยู่อาศัย</div>{{ $single('housing_type') }}
                    <div class="hv-q mt-3">4.3 สภาพที่อยู่อาศัย</div>{{ $multi('house_condition') }}
                    <div class="hv-q mt-3">4.4 ยานพาหนะของครอบครัว</div>
                    @foreach (['vehicle_car' => 'รถยนต์ส่วนบุคคล', 'vehicle_pickup' => 'รถปิกอัพ/รถบรรทุกเล็ก/รถตู้', 'vehicle_tractor' => 'รถไถ/เกี่ยวข้าว/รถอีแต๋น/รถอื่น ๆ ประเภทเดียวกัน'] as $k => $label)
                        <div class="d-flex flex-wrap align-items-center gap-3 small mb-1"><span style="min-width:230px">- {{ $label }}</span>{{ $single($k) }}</div>
                    @endforeach
                    <div class="hv-q mt-3">4.5 เป็นเกษตรกร มีที่ดินทำกิน (รวมเช่า)</div>{{ $multi('farmland', '') }}
                </div>
            </div>
        </div>

        {{-- ข้อ 3 --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header"><i class="bi bi-people"></i> 3. จำนวนสมาชิกในครัวเรือน (รวมตัวนักเรียน)
                    <input type="number" min="0" max="50" name="form[member_count]" id="memberCount" value="{{ $val('member_count') }}" class="form-control form-control-sm ms-2" style="width:80px" aria-label="จำนวนสมาชิกในครัวเรือน"> <span class="ms-1">คน</span>
                    <span class="ms-3 small text-muted fw-normal">รายละเอียดกรอกเฉพาะนักเรียนยากจน · รายได้เฉลี่ยต่อเดือน (บาท)</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered hv-table hv-members mb-0">
                        <thead>
                            <tr>
                                <th style="width:44px">คนที่</th><th>ความสัมพันธ์กับนักเรียน</th><th style="width:70px">อายุ</th><th style="width:80px">พิการทางร่างกาย/สติปัญญา</th>
                                @foreach ($F::INCOME as $label)<th>{{ $label }}</th>@endforeach
                                <th style="width:120px">รายได้รวมเฉลี่ยต่อเดือน</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($members as $i => $m)
                            <tr data-member>
                                <td class="text-center align-middle small">{{ $i + 1 }}</td>
                                <td><input name="form[members][{{ $i }}][relation]" value="{{ $m['relation'] ?? '' }}" class="form-control form-control-sm" aria-label="ความสัมพันธ์ คนที่ {{ $i + 1 }}"></td>
                                <td><input type="number" min="0" max="130" name="form[members][{{ $i }}][age]" value="{{ $m['age'] ?? '' }}" class="form-control form-control-sm" aria-label="อายุ คนที่ {{ $i + 1 }}"></td>
                                <td class="text-center align-middle"><input type="checkbox" class="form-check-input" name="form[members][{{ $i }}][disabled]" value="1" @checked($m['disabled'] ?? false) aria-label="พิการ คนที่ {{ $i + 1 }}"></td>
                                @foreach (array_keys($F::INCOME) as $type)
                                    <td><input type="number" min="0" step="1" name="form[members][{{ $i }}][{{ $type }}]" value="{{ $m[$type] ?? '' }}" class="form-control form-control-sm text-end" data-income aria-label="{{ $F::INCOME[$type] }} คนที่ {{ $i + 1 }}"></td>
                                @endforeach
                                <td class="text-end align-middle small fw-semibold" data-row-total>-</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold"><td colspan="{{ 4 + count($F::INCOME) }}" class="small p-2">รวมรายได้ครัวเรือน (รายการที่ 1–10)</td><td class="text-end p-2" id="householdTotal">-</td></tr>
                            <tr class="fw-semibold"><td colspan="{{ 4 + count($F::INCOME) }}" class="small p-2">รายได้ครัวเรือนเฉลี่ยต่อคน (รวมรายได้ครัวเรือน หารด้วยจำนวนสมาชิกทั้งหมด)</td><td class="text-end p-2" id="perHead">-</td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- ข้อ 5 --}}
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-heart"></i> 5. ความสัมพันธ์ในครอบครัว</div>
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3"><span class="hv-q mb-0">5.1 สมาชิกในครอบครัวมีเวลาอยู่ร่วมกัน</span><input type="number" min="0" max="24" step="0.5" name="form[hours_together]" value="{{ $val('hours_together') }}" class="form-control form-control-sm" style="width:90px" aria-label="ชั่วโมงต่อวัน"> ชั่วโมง/วัน</div>

                    <div class="hv-q">5.2 ความสัมพันธ์ระหว่างนักเรียนกับสมาชิกในครอบครัว</div>
                    <div class="table-responsive mb-3">
                        <table class="table table-bordered table-sm hv-table mb-0">
                            <thead><tr><th class="text-start">สมาชิก</th>@foreach ($F::CLOSENESS as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                            <tbody>
                            @foreach ($F::RELATIVES as $rel => $label)
                                <tr>
                                    <td class="small align-middle">
                                        @if ($rel === 'other')<input name="form[relation_other_name]" value="{{ $val('relation_other_name') }}" class="form-control form-control-sm" placeholder="อื่น ๆ ระบุ" aria-label="สมาชิกอื่น ๆ">@else{{ $label }}@endif
                                    </td>
                                    @foreach ($F::CLOSENESS as $c => $cLabel)
                                        <td class="text-center align-middle"><input type="radio" class="form-check-input" name="form[closeness][{{ $rel }}]" value="{{ $c }}" @checked(($form['closeness'][$rel] ?? null) === $c) aria-label="{{ $label }}: {{ $cLabel }}"></td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="hv-q">5.3 กรณีที่ผู้ปกครองไม่อยู่บ้าน ฝากเด็กนักเรียนอยู่บ้านกับใคร</div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">{{ $single('left_with') }}<input name="form[left_with_other]" value="{{ $val('left_with_other') }}" class="form-control form-control-sm" style="max-width:200px" placeholder="ระบุ" aria-label="ฝากไว้กับ อื่น ๆ"></div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6"><label class="form-label small">5.4 รายได้ครัวเรือนเฉลี่ยต่อคน (กรณีไม่ยากจน) บาท</label><input type="number" min="0" name="form[income_per_head]" value="{{ $val('income_per_head') }}" class="form-control form-control-sm"></div>
                        <div class="col-md-6"><label class="form-label small">5.5 นักเรียนได้รับค่าใช้จ่ายจาก</label><input name="form[expense_from]" value="{{ $val('expense_from') }}" class="form-control form-control-sm"></div>
                        <div class="col-md-4 d-flex align-items-end"><label class="small"><input type="checkbox" class="form-check-input" name="form[student_works]" value="1" @checked($val('student_works', false))> นักเรียนทำงานหารายได้</label></div>
                        <div class="col-md-4"><label class="form-label small">อาชีพ</label><input name="form[student_job]" value="{{ $val('student_job') }}" class="form-control form-control-sm"></div>
                        <div class="col-md-4"><label class="form-label small">รายได้วันละ (บาท)</label><input type="number" min="0" name="form[student_income]" value="{{ $val('student_income') }}" class="form-control form-control-sm"></div>
                        <div class="col-md-6"><label class="form-label small">นักเรียนได้เงินมาโรงเรียนวันละ (บาท)</label><input type="number" min="0" name="form[allowance]" value="{{ $val('allowance') }}" class="form-control form-control-sm"></div>
                    </div>

                    <div class="hv-q">5.6 สิ่งที่ผู้ปกครองต้องการให้โรงเรียนช่วยเหลือนักเรียน</div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">{{ $multi('help_wanted', '') }}<input name="form[help_wanted_other]" value="{{ $val('help_wanted_other') }}" class="form-control form-control-sm" style="max-width:200px" placeholder="ระบุ" aria-label="ความช่วยเหลือ อื่น ๆ"></div>

                    <div class="hv-q">5.7 ความช่วยเหลือที่ครอบครัวเคยได้รับจากหน่วยงานหรือต้องการได้รับ</div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">{{ $multi('assistance', '') }}<input name="form[assistance_other]" value="{{ $val('assistance_other') }}" class="form-control form-control-sm" style="max-width:200px" placeholder="ระบุ" aria-label="ความช่วยเหลือที่เคยได้รับ อื่น ๆ"></div>

                    <label class="hv-q" for="concerns">5.8 ข้อห่วงใยของผู้ปกครองที่มีต่อนักเรียน</label>
                    <textarea id="concerns" name="form[concerns]" rows="3" class="form-control">{{ $val('concerns') }}</textarea>
                </div>
            </div>
        </div>

        {{-- ข้อ 6.1–6.5 --}}
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-shield-exclamation"></i> 6. พฤติกรรมและความเสี่ยง</div>
                <div class="card-body">
                    <div class="hv-q">6.1 สุขภาพ</div>{{ $multi('health') }}
                    <div class="hv-q mt-3">6.2 สวัสดิการหรือความปลอดภัย</div>{{ $multi('safety') }}

                    <div class="hv-q mt-3">6.3 ระยะทางระหว่างบ้านไปโรงเรียน (ไป/กลับ)</div>
                    <div class="d-flex flex-wrap align-items-center gap-2 small mb-2">
                        <input type="number" min="0" step="0.1" name="form[distance_km]" value="{{ $val('distance_km') }}" class="form-control form-control-sm" style="width:90px" aria-label="ระยะทาง กิโลเมตร"> กิโลเมตร · ใช้เวลาเดินทาง
                        <input type="number" min="0" max="24" name="form[travel_hours]" value="{{ $val('travel_hours') }}" class="form-control form-control-sm" style="width:70px" aria-label="ชั่วโมง"> ชม.
                        <input type="number" min="0" max="59" name="form[travel_minutes]" value="{{ $val('travel_minutes') }}" class="form-control form-control-sm" style="width:70px" aria-label="นาที"> นาที
                    </div>
                    <div class="small text-muted mb-1">การเดินทางของนักเรียนไปโรงเรียน</div>
                    <div class="d-flex flex-wrap align-items-center gap-2">{{ $single('transport') }}<input name="form[transport_other]" value="{{ $val('transport_other') }}" class="form-control form-control-sm" style="max-width:180px" placeholder="ระบุ" aria-label="การเดินทาง อื่น ๆ"></div>

                    <div class="hv-q mt-3">6.4 ภาระงานความรับผิดชอบของนักเรียนที่มีต่อครอบครัว</div>
                    <div class="d-flex flex-wrap align-items-center gap-2">{{ $multi('duties', '') }}<input name="form[duties_other]" value="{{ $val('duties_other') }}" class="form-control form-control-sm" style="max-width:180px" placeholder="ระบุ" aria-label="ภาระงาน อื่น ๆ"></div>

                    <div class="hv-q mt-3">6.5 กิจกรรมยามว่างหรืองานอดิเรก</div>
                    <div class="d-flex flex-wrap align-items-center gap-2">{{ $multi('hobbies', '') }}<input name="form[hobbies_other]" value="{{ $val('hobbies_other') }}" class="form-control form-control-sm" style="max-width:180px" placeholder="ระบุ" aria-label="งานอดิเรก อื่น ๆ"></div>
                </div>
            </div>
        </div>

        {{-- ข้อ 6.6–6.11 --}}
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-exclamation-triangle"></i> 6.6–6.8 พฤติกรรมเสี่ยง</div>
                <div class="card-body">
                    <div class="hv-q">6.6 พฤติกรรมการใช้สารเสพติด</div>{{ $multi('substance') }}
                    <div class="hv-q mt-3">6.7 พฤติกรรมการใช้ความรุนแรง</div>{{ $multi('violence') }}
                    <div class="hv-q mt-3">6.8 พฤติกรรมทางเพศ</div>{{ $multi('sexual') }}
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-controller"></i> 6.9–6.11 เกมและสื่ออิเล็กทรอนิกส์</div>
                <div class="card-body">
                    <div class="hv-q">6.9 การติดเกม</div>
                    <div class="d-flex flex-wrap align-items-end gap-2">{{ $multi('game') }}<input name="form[game_other]" value="{{ $val('game_other') }}" class="form-control form-control-sm" style="max-width:180px" placeholder="อื่น ๆ ระบุ" aria-label="การติดเกม อื่น ๆ"></div>
                    <div class="hv-q mt-3">6.10 การเข้าถึงสื่อคอมพิวเตอร์และอินเทอร์เน็ตที่บ้าน</div>{{ $single('internet', 'cols') }}
                    <div class="hv-q mt-3">6.11 การใช้เครื่องมือสื่อสารอิเล็กทรอนิกส์</div>{{ $multi('devices') }}
                </div>
            </div>
        </div>

        {{-- ผู้ให้ข้อมูล + ภาพถ่าย (หน้า 4) --}}
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-person-check"></i> ผู้ให้ข้อมูลนักเรียน</div>
                <div class="card-body">
                    {{ $single('informant') }}
                    <div class="small text-muted mt-3">ขอรับรองว่าข้อมูลดังกล่าวเป็นจริง — ลงชื่อผู้ปกครอง/ผู้แทนในแบบที่พิมพ์ออกมา</div>
                    <hr>
                    <label class="form-label" for="note">บันทึกเพิ่มเติมของครูผู้เยี่ยมบ้าน</label>
                    <textarea id="note" name="note" rows="3" class="form-control">{{ old('note', $visit->note) }}</textarea>
                    <div class="row g-2 mt-1">
                        <div class="col-md-6"><label class="form-label small">ตำแหน่งผู้เยี่ยม</label><input name="form[visitor_position]" value="{{ $val('visitor_position', auth()->user()->position) }}" class="form-control form-control-sm" placeholder="ครูหรือผู้อำนวยการโรงเรียน"></div>
                        <div class="col-md-6">
                            <label class="form-label small">พิกัดบ้าน</label>
                            <div class="input-group input-group-sm">
                                <input name="lat" id="lat" value="{{ old('lat', $visit->lat) }}" class="form-control" placeholder="ละติจูด" inputmode="decimal" aria-label="ละติจูด">
                                <input name="lng" id="lng" value="{{ old('lng', $visit->lng) }}" class="form-control" placeholder="ลองจิจูด" inputmode="decimal" aria-label="ลองจิจูด">
                                <button type="button" class="btn btn-light border" id="locate" title="ใช้ตำแหน่งปัจจุบัน"><i class="bi bi-crosshair"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-camera"></i> ภาพถ่ายบ้านนักเรียนที่ได้รับการเยี่ยมบ้าน</div>
                <div class="card-body">
                    <div class="hv-q">ภาพถ่ายที่แนบมาคือ</div>{{ $single('photo_kind', 'cols') }}
                    <div class="row g-3 mt-1">
                        @foreach (['photo' => ['รูปที่ 1 ภาพถ่ายสภาพบ้านนักเรียน', 'home-visit'], 'photo_inside' => ['รูปที่ 2 ภาพถ่ายภายในบ้านนักเรียน', 'home-visit-inside']] as $field => [$label, $type])
                            <div class="col-md-6">
                                <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                                <input type="file" id="{{ $field }}" name="{{ $field }}" accept="image/*" class="form-control @error($field) is-invalid @enderror">
                                @if ($visit->{$field})
                                    <a href="{{ route('files.show', [$type, $visit->id]) }}" target="_blank" class="d-block mt-2"><img src="{{ route('files.show', [$type, $visit->id]) }}" alt="{{ $label }}" class="rounded-3 border" style="max-height:180px;max-width:100%;object-fit:cover"></a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="small text-muted mt-2">รูปที่ 1 ควรเห็นหลังคาและฝาบ้านด้วย</div>
                </div>
            </div>
        </div>
    </div>

    <div class="hv-savebar d-flex align-items-center gap-3 px-3 py-2 mt-3 rounded-3">
        <span class="small text-muted flex-grow-1">○ ตอบได้ข้อเดียว · ☐ ตอบได้มากกว่า 1 ข้อ · บันทึกแล้วกลับมาแก้ไขได้ตลอดภาคเรียน · ปุ่ม "พิมพ์แบบ" พิมพ์จากข้อมูลที่บันทึกแล้ว</span>
        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> บันทึก</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    // รวมรายได้ต่อแถว รวมครัวเรือน และเฉลี่ยต่อคน (ระบบคำนวณซ้ำอีกครั้งตอนบันทึก)
    const fmt = (n) => n > 0 ? n.toLocaleString('th-TH', { maximumFractionDigits: 2 }) : '-';
    const recalc = () => {
        let household = 0, filled = 0;
        document.querySelectorAll('[data-member]').forEach((row) => {
            let total = 0;
            row.querySelectorAll('[data-income]').forEach((i) => { total += parseFloat(i.value) || 0; });
            row.querySelector('[data-row-total]').textContent = fmt(total);
            household += total;
            if (total > 0 || row.querySelector('input[name$="[relation]"]').value.trim() !== '') filled++;
        });
        const count = parseInt(document.getElementById('memberCount').value, 10) || filled;
        document.getElementById('householdTotal').textContent = fmt(household);
        document.getElementById('perHead').textContent = count > 0 ? fmt(household / count) : '-';
    };
    document.getElementById('hvForm').addEventListener('input', recalc);
    recalc();

    document.getElementById('locate').addEventListener('click', function () {
        if (!navigator.geolocation) return alert('อุปกรณ์นี้ไม่รองรับการระบุตำแหน่ง');
        navigator.geolocation.getCurrentPosition(function (p) {
            document.getElementById('lat').value = p.coords.latitude.toFixed(7);
            document.getElementById('lng').value = p.coords.longitude.toFixed(7);
        }, function () { alert('ไม่ได้รับอนุญาตให้ใช้ตำแหน่ง'); });
    });
})();
</script>
@endpush
