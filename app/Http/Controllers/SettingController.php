<?php

namespace App\Http\Controllers;

use App\Models\BehaviorRule;
use App\Models\User;
use App\Support\Artwork;
use App\Support\Audit;
use App\Support\Settings;
use App\Support\SettingsMenu;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /** ช่องของแต่ละหัวข้อ: ฟอร์มของหัวข้อส่ง section มาด้วย จะตรวจและบันทึกเฉพาะช่องของหัวข้อนั้น */
    private const FIELDS = [
        'school' => ['school_name', 'school_short', 'school_address', 'school_affiliation', 'school_province', 'school_phone',
            'director_name', 'academic_deputy_name', 'measurement_head_name', 'registrar_name', 'logo'],
        'schedule' => ['late_time', 'staff_late_time', 'periods_per_day', 'grade_scale', 'school_days', 'period_times'],
        'gate' => ['gate_checkout_after', 'gps_required', 'school_lat', 'school_lng', 'gps_radius'],
        'library' => ['library_loan_days'],
        'facility' => ['facility_manager_ids'],
        'payment' => ['promptpay_id', 'bank_info'],
        'line' => ['line_channel_token', 'line_channel_secret', 'line_oa_id', 'line_notify_gate', 'line_notify_absent'],
        'line-login' => ['line_login_channel_id', 'line_login_channel_secret'],
        'privacy' => ['privacy_notice'],
        'appearance' => ['theme_color', 'brand_credit'],
        'website' => ['website_enabled', 'website_tagline', 'website_greeting', 'website_director_photo', 'website_intro', 'website_video', 'website_email', 'website_hours', 'website_facebook', 'website_youtube', 'website_line', 'website_map_url'],
    ];

    private const FLAGS = ['line_notify_gate', 'line_notify_absent', 'gps_required', 'website_enabled'];

    private const SECRETS = ['line_channel_token', 'line_channel_secret', 'line_login_channel_secret'];

    public function edit(Request $request)
    {
        $sections = SettingsMenu::sections($request->user());
        $section = (string) $request->query('s', 'school');
        if (! isset($sections[$section])) {
            $section = array_key_first($sections);
        }

        return view('settings.edit', [
            'settings' => Settings::all(),
            'sections' => $sections,
            'groups' => SettingsMenu::groups($request->user()),
            'section' => $section,
            'staff' => User::whereIn('role', ['teacher'])->where('is_active', true)->orderBy('name')->get(),
            'rules' => BehaviorRule::orderByDesc('points')->get(),
        ]);
    }

    /** /settings/license: หน้าตั้งค่าหัวข้อ License (เปิดได้แม้ระบบถูกล็อก) */
    public function license(Request $request)
    {
        $request->query->set('s', 'license');

        return $this->edit($request);
    }

    public function update(Request $request)
    {
        // ฟอร์มส่งเป็นรายการ (multi-select) · รองรับค่าเดิมที่เก็บเป็นข้อความคั่นด้วย , ด้วย
        if (is_string($request->input('facility_manager_ids'))) {
            $request->merge(['facility_manager_ids' => array_filter(explode(',', $request->input('facility_manager_ids')))]);
        }
        $rules = [
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
            'school_days' => ['nullable', 'in:5,6'],
            'privacy_notice' => ['nullable', 'string', 'max:20000'],
            'grade_scale' => ['nullable', 'string', 'max:60', fn ($attr, $value, $fail) => \App\Support\Grade::parseScale((string) $value) === null ? $fail('เกณฑ์ตัดเกรดต้องเป็นจำนวนเต็ม 7 ค่า เรียงจากมากไปน้อย คั่นด้วยจุลภาค เช่น 80,75,70,65,60,55,50') : null],
            'promptpay_id' => ['nullable', 'string', 'max:20'],
            'bank_info' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'theme_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'brand_credit' => ['nullable', 'string', 'max:150'],
            'website_tagline' => ['nullable', 'string', 'max:200'],
            'website_greeting' => ['nullable', 'string', 'max:5000'],
            'website_director_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'website_hours' => ['nullable', 'string', 'max:150'],
            'website_intro' => ['nullable', 'string', 'max:3000'],
            'website_video' => ['nullable', 'url', 'max:300', fn ($a, $v, $fail) => $v && ! \App\Support\Website::youtubeId($v) ? $fail('ใส่ลิงก์วิดีโอจาก YouTube เช่น https://youtu.be/xxxx') : null],
            'website_email' => ['nullable', 'email', 'max:150'],
            'website_facebook' => ['nullable', 'url', 'max:300'],
            'website_youtube' => ['nullable', 'url', 'max:300'],
            'website_line' => ['nullable', 'url', 'max:300'],
            'website_map_url' => ['nullable', 'url', 'max:1000'],
            'line_channel_token' => ['nullable', 'string', 'max:500'],
            'line_channel_secret' => ['nullable', 'string', 'max:100'],
            'line_oa_id' => ['nullable', 'string', 'max:40'],
            'line_login_channel_id' => ['nullable', 'string', 'max:40'],
            'line_login_channel_secret' => ['nullable', 'string', 'max:100'],
            'gate_checkout_after' => ['nullable', 'date_format:H:i'],
            'school_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'school_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'gps_radius' => ['nullable', 'integer', 'min:30', 'max:5000'],
            'library_loan_days' => ['nullable', 'integer', 'min:1', 'max:60'],
            'facility_manager_ids' => ['nullable', 'array'],
            'facility_manager_ids.*' => ['integer', 'exists:users,id'],
        ];
        // ฟอร์มของแต่ละหัวข้อ: ตรวจเฉพาะช่องของหัวข้อนั้น (ไม่ส่ง section = ฟอร์มรวมแบบเดิม ตรวจทุกช่อง)
        $fields = self::FIELDS[$request->input('section')] ?? null;
        if ($fields !== null) {
            $rules = array_filter($rules, fn ($k) => in_array(strtok($k, '.'), $fields, true), ARRAY_FILTER_USE_KEY);
        }
        $data = $request->validate($rules, ['theme_color.regex' => 'รหัสสีต้องเป็นรูปแบบ #RRGGBB']);

        if (isset($data['theme_color'])) {
            $data['theme_color'] = strtoupper($data['theme_color']);
        }
        // แก้ประกาศความเป็นส่วนตัวแล้วต้องการให้ทุกคนรับทราบใหม่ = ขึ้นฉบับใหม่
        if ($request->boolean('privacy_bump')) {
            $data['privacy_version'] = (string) ((int) Settings::get('privacy_version', 1) + 1);
        }
        if ($fields === null || in_array('facility_manager_ids', $fields, true)) {
            $data['facility_manager_ids'] = implode(',', $data['facility_manager_ids'] ?? []);
        }
        foreach (self::FLAGS as $flag) {
            if ($fields === null || in_array($flag, $fields, true)) {
                $data[$flag] = $request->boolean($flag) ? '1' : '0';
            }
        }
        // ช่อง token/secret เว้นว่าง = ใช้ค่าเดิม (ไม่แสดงค่าจริงบนหน้าเว็บ)
        foreach (self::SECRETS as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }
        $data = array_map(fn ($v) => $v ?? '', $data);

        if ($request->hasFile('website_director_photo')) {
            \App\Support\Website::deleteFile((string) Settings::get('website_director_photo'));
            $data['website_director_photo'] = \App\Support\Website::storeImage($request->file('website_director_photo'), 'director');
        } else {
            unset($data['website_director_photo']);
        }
        if ($request->boolean('website_enabled') && Settings::get('website_enabled') !== '1') {
            \App\Support\SchoolSetup::website(); // เปิดครั้งแรก: สร้างหน้าเนื้อหาตั้งต้น
        }
        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('school', 'public');
        } else {
            unset($data['logo']);
        }

        $old = Settings::all();
        $changed = array_keys(array_filter($data, fn ($v, $k) => (string) ($old[$k] ?? '') !== (string) $v, ARRAY_FILTER_USE_BOTH));
        if ($changed) {
            // ไม่เก็บค่าของ token/secret ลงประวัติ เก็บแค่ว่ามีการเปลี่ยน
            Audit::log('setting.update', null, 'แก้ตั้งค่าโรงเรียน: '.implode(', ', $changed), collect($changed)
                ->mapWithKeys(fn ($k) => [$k => in_array($k, self::SECRETS, true) ? ['***', '***'] : [$old[$k] ?? null, $data[$k]]])->all());
        }
        Settings::set($data);

        return $this->toWorkspace()->with('success', 'บันทึกการตั้งค่าแล้ว');
    }

    /** ตั้งค่า > รูปภาพระบบ */
    public function saveImages(Request $request)
    {
        $rules = [];
        foreach (array_keys(Artwork::SLOTS) as $slot) {
            $rules[$slot] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:max_width=6000,max_height=6000'];
            $rules['reset_'.$slot] = ['nullable', 'boolean'];
        }
        $request->validate($rules, ['*.max' => 'รูปภาพต้องไม่เกิน 8 MB', '*.mimes' => 'รองรับเฉพาะ JPG PNG และ WebP']);

        $changed = [];
        foreach (Artwork::SLOTS as $slot => [$label]) {
            $key = 'artwork_'.$slot;
            $old = (string) Settings::get($key, '');
            if ($request->hasFile($slot)) {
                Settings::set([$key => Artwork::store($request->file($slot))]);
                Artwork::delete($old);
                $changed[] = $label;
            } elseif ($request->boolean('reset_'.$slot) && $old !== '') {
                Settings::set([$key => '']);
                Artwork::delete($old);
                $changed[] = $label.' (กลับไปใช้รูปเริ่มต้น)';
            }
        }
        if ($changed) {
            Audit::log('setting.images', null, 'เปลี่ยนรูปภาพระบบ: '.implode(', ', $changed));
        }

        return $this->toWorkspace()->with('success', $changed ? 'บันทึกรูปภาพแล้ว' : 'ไม่มีรูปภาพที่เปลี่ยน');
    }

    public function storeRule(Request $request)
    {
        BehaviorRule::create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'points' => ['required', 'integer', 'between:-100,100', 'not_in:0'],
        ]));

        return $this->toWorkspace()->with('success', 'เพิ่มหัวข้อพฤติกรรมแล้ว');
    }

    public function updateRule(Request $request, BehaviorRule $rule)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'points' => ['required', 'integer', 'between:-100,100', 'not_in:0'],
        ]);
        $rule->update($data + ['is_active' => $request->boolean('is_active')]);

        return $this->toWorkspace()->with('success', 'บันทึกแล้ว');
    }

    public function destroyRule(BehaviorRule $rule)
    {
        $rule->delete();

        return $this->toWorkspace()->with('success', 'ลบแล้ว');
    }
}
