<?php

namespace App\Http\Controllers;

use App\Models\BehaviorRule;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        return view('settings.edit', [
            'settings' => Settings::all(),
            'rules' => BehaviorRule::orderByDesc('points')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'school_name' => ['required', 'string', 'max:255'],
            'school_short' => ['nullable', 'string', 'max:100'],
            'school_address' => ['nullable', 'string', 'max:500'],
            'school_phone' => ['nullable', 'string', 'max:50'],
            'school_affiliation' => ['nullable', 'string', 'max:255'],
            'school_province' => ['nullable', 'string', 'max:100'],
            'director_name' => ['nullable', 'string', 'max:255'],
            'academic_deputy_name' => ['nullable', 'string', 'max:255'],
            'measurement_head_name' => ['nullable', 'string', 'max:255'],
            'registrar_name' => ['nullable', 'string', 'max:255'],
            'late_time' => ['required', 'date_format:H:i'],
            'staff_late_time' => ['required', 'date_format:H:i'],
            'periods_per_day' => ['required', 'integer', 'min:1', 'max:12'],
            'period_times' => ['nullable', 'string', 'max:1000'],
            'promptpay_id' => ['nullable', 'string', 'max:20'],
            'bank_info' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'theme_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'line_channel_token' => ['nullable', 'string', 'max:500'],
            'line_channel_secret' => ['nullable', 'string', 'max:100'],
            'line_oa_id' => ['nullable', 'string', 'max:40'],
            'gate_checkout_after' => ['nullable', 'date_format:H:i'],
            'school_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'school_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'gps_radius' => ['nullable', 'integer', 'min:30', 'max:5000'],
            'library_loan_days' => ['nullable', 'integer', 'min:1', 'max:60'],
        ], ['theme_color.regex' => 'รหัสสีต้องเป็นรูปแบบ #RRGGBB']);
        $data['theme_color'] = strtoupper($data['theme_color']);
        foreach (['line_notify_gate', 'line_notify_absent', 'gps_required'] as $flag) {
            $data[$flag] = $request->boolean($flag) ? '1' : '0';
        }
        // ช่อง token/secret เว้นว่าง = ใช้ค่าเดิม (ไม่แสดงค่าจริงบนหน้าเว็บ)
        foreach (['line_channel_token', 'line_channel_secret'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }
        $data = array_map(fn ($v) => $v ?? '', $data);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('school', 'public');
        } else {
            unset($data['logo']);
        }

        $old = Settings::all();
        $changed = array_keys(array_filter($data, fn ($v, $k) => (string) ($old[$k] ?? '') !== (string) $v, ARRAY_FILTER_USE_BOTH));
        if ($changed) {
            // ไม่เก็บค่าของ token/secret ลงประวัติ เก็บแค่ว่ามีการเปลี่ยน
            $secret = ['line_channel_token', 'line_channel_secret'];
            Audit::log('setting.update', null, 'แก้ตั้งค่าโรงเรียน: '.implode(', ', $changed), collect($changed)
                ->mapWithKeys(fn ($k) => [$k => in_array($k, $secret, true) ? ['***', '***'] : [$old[$k] ?? null, $data[$k]]])->all());
        }
        Settings::set($data);

        return back()->with('success', 'บันทึกการตั้งค่าแล้ว');
    }

    public function storeRule(Request $request)
    {
        BehaviorRule::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'points' => ['required', 'integer', 'between:-100,100', 'not_in:0'],
        ]));

        return back()->with('success', 'เพิ่มหัวข้อพฤติกรรมแล้ว');
    }

    public function updateRule(Request $request, BehaviorRule $rule)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'points' => ['required', 'integer', 'between:-100,100', 'not_in:0'],
        ]);
        $rule->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'บันทึกแล้ว');
    }

    public function destroyRule(BehaviorRule $rule)
    {
        $rule->delete();

        return back()->with('success', 'ลบแล้ว');
    }
}
