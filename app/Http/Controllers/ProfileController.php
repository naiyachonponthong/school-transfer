<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\PasswordController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    /** บันทึกลายเซ็นที่เซ็นบนหน้าจอ (PNG) ใช้ลงนามเมื่อพิจารณาคำขอใช้งบ */
    public function signature(Request $request)
    {
        $data = $request->validate(['signature' => ['required', 'string', 'max:400000']], [], ['signature' => 'ลายเซ็น']);
        $user = $request->user();
        $disk = Storage::disk('local');
        if ($data['signature'] === 'clear') {
            // สำเนาในเอกสารที่เซ็นไปแล้วเป็นไฟล์แยก จึงไม่หายตาม
            $user->signature && $disk->delete($user->signature);
            $user->forceFill(['signature' => null])->save();

            return back()->with('success', 'ลบลายเซ็นแล้ว');
        }
        $png = str_starts_with($data['signature'], 'data:image/png;base64,') ? base64_decode(substr($data['signature'], 22), true) : false;
        $size = $png ? @getimagesizefromstring($png) : false;
        if (! $size || $size[2] !== IMAGETYPE_PNG || $size[0] > 1600 || $size[1] > 800) {
            return back()->withErrors(['signature' => 'ลายเซ็นไม่ถูกต้อง กรุณาเซ็นใหม่']);
        }
        $user->signature && $disk->delete($user->signature);
        $path = 'signatures/'.\Illuminate\Support\Str::random(40).'.png';
        $disk->put($path, $png);
        $user->forceFill(['signature' => $path])->save();

        return back()->with('success', 'บันทึกลายเซ็นแล้ว');
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => array_merge(['nullable'], array_slice(PasswordController::rules($user), 1)),
            'avatar' => ['nullable', 'image', 'max:4096'],
        ], [
            'current_password.current_password' => 'รหัสผ่านเดิมไม่ถูกต้อง',
            ...PasswordController::messages(),
        ]);

        // ชื่อนักเรียนมาจากทะเบียนนักเรียน แก้เองไม่ได้
        $user->fill(collect($data)->only($user->isStudent() ? ['email', 'phone'] : ['name', 'email', 'phone'])->all());
        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
            $user->must_change_password = false;
        }
        $user->save();

        return back()->with('success', 'บันทึกข้อมูลส่วนตัวแล้ว');
    }
}
