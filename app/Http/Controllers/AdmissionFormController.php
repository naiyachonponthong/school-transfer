<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Support\AdmissionForm;
use App\Support\Settings;
use Illuminate\Http\Request;

/** ตั้งค่าฟอร์มรับสมัคร: ช่องเสริม แสดง/บังคับ/ซ่อน · คำถามของโรงเรียน · ช่วงวันที่เปิดรับ · จำนวนรับต่อชั้น */
class AdmissionFormController extends Controller
{
    public function edit()
    {
        $config = AdmissionForm::config();

        return view('admissions.form-builder', [
            'config' => $config,
            'levels' => AdmissionForm::levels(),
            'open' => (bool) Settings::get('admission_open'),
            'isOpen' => AdmissionForm::isOpen($config),
            // คำถามที่มีคนตอบแล้ว — ลบได้ แต่ใบสมัครเก่ายังเก็บคำตอบไว้ (snapshot)
            'answered' => Admission::whereNotNull('answers')->pluck('answers')->flatten(1)->pluck('id')->countBy()->all(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'admission_open' => ['nullable', 'boolean'],
            'admission_levels' => ['required', 'string', 'max:200'],
            'config' => ['required', 'json', 'max:200000'],
        ], ['admission_levels.required' => 'ระบุชั้นที่เปิดรับสมัครอย่างน้อย 1 ชั้น']);
        $config = json_decode($data['config'], true);
        abort_unless(is_array($config), 422);
        $levels = implode(',', array_values(array_unique(array_filter(array_map('trim', explode(',', $data['admission_levels']))))));

        if (! empty($config['open_from']) && ! empty($config['open_until']) && $config['open_from'] > $config['open_until']) {
            return back()->withErrors(['config' => 'วันปิดรับสมัครต้องไม่ก่อนวันเปิด'])->withInput();
        }
        Settings::set(['admission_open' => $request->boolean('admission_open') ? '1' : '0', 'admission_levels' => $levels]);
        $saved = AdmissionForm::save($config);

        return redirect()->route('admissions.form')->with('success', 'บันทึกฟอร์มรับสมัครแล้ว · คำถามของโรงเรียน '.count($saved['questions']).' ข้อ');
    }
}
