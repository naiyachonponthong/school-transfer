<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use App\Support\Demo;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** โหมดทดลองใช้: เข้าระบบตามบทบาทโดยไม่ใช้รหัสผ่าน + หน้าตั้งค่าของผู้ดูแลระบบ */
class DemoController extends Controller
{
    /** ปุ่ม "ทดลองใช้" ในหน้าเข้าสู่ระบบ */
    public function login(Request $request, string $role)
    {
        abort_unless(Demo::enabled() && isset(Demo::ROLES[$role]), 404);
        $user = Demo::userFor($role);
        abort_unless($user, 404);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('demo_role', $role);

        return redirect()->route('home')->with('success', 'เข้าสู่โหมดทดลองใช้ในบทบาท'.Demo::ROLES[$role][0].' · ข้อมูลทั้งหมดเป็นข้อมูลตัวอย่าง');
    }

    public function settings(Request $request)
    {
        $rules = ['demo_mode' => ['nullable', 'boolean'], 'demo_reset' => ['nullable', 'boolean']];
        foreach (array_keys(Demo::ROLES) as $role) {
            // บัญชีทดลองต้องไม่ใช่ผู้ดูแลระบบ: คนภายนอกกดเข้าได้โดยไม่มีรหัสผ่าน
            $rules['demo_user_'.$role] = ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNot('role', 'admin')];
        }
        $data = $request->validate($rules, ['*.exists' => 'เลือกผู้ใช้ที่เปิดใช้งานอยู่และไม่ใช่ผู้ดูแลระบบ']);

        $values = ['demo_mode' => $request->boolean('demo_mode') ? '1' : '0', 'demo_reset' => $request->boolean('demo_reset') ? '1' : '0'];
        foreach (array_keys(Demo::ROLES) as $role) {
            $values['demo_user_'.$role] = (string) ($data['demo_user_'.$role] ?? '');
        }
        Settings::set($values);
        Audit::log('setting.update', null, 'แก้ตั้งค่าโหมดทดลองใช้: '.($values['demo_mode'] === '1' ? 'เปิด' : 'ปิด'));

        return back()->with('success', 'บันทึกโหมดทดลองใช้แล้ว');
    }

    /** บันทึกข้อมูลปัจจุบันเป็นต้นแบบ (ใช้คืนค่าทุกคืน) */
    public function snapshot()
    {
        $rows = Demo::snapshot();
        Audit::log('setting.update', null, "บันทึกต้นแบบข้อมูลของโหมดทดลองใช้ ({$rows} แถว)");

        return back()->with('success', 'บันทึกต้นแบบข้อมูลแล้ว '.number_format($rows).' แถว');
    }

    /** คืนข้อมูลกลับเป็นต้นแบบทันที */
    public function reset(Request $request)
    {
        $admin = $request->user()->id;
        try {
            $rows = Demo::reset();
        } catch (\RuntimeException $e) {
            return back()->with('warning', $e->getMessage());
        }
        // ผู้ดูแลที่สั่งอาจถูกแก้ไข/ลบไปในข้อมูลต้นแบบ: ถ้ายังอยู่ให้คงการเข้าสู่ระบบไว้
        if (! User::find($admin)) {
            Auth::logout();

            return redirect()->route('login');
        }

        return back()->with('success', 'คืนข้อมูลเป็นต้นแบบแล้ว '.number_format($rows).' แถว');
    }
}
