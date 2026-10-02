<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Notifier;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * - บังคับตั้งรหัสผ่านใหม่ (บัญชีที่สร้าง/รีเซ็ตโดยคนอื่น)
 * - ลืมรหัสผ่าน: ส่งรหัสยืนยัน 6 หลักทาง LINE ที่เชื่อมไว้ (ใช้ได้ 10 นาที ลองผิดได้ 5 ครั้ง)
 */
class PasswordController extends Controller
{
    private const CODE_TTL_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    /** กฎรหัสผ่านใหม่: อย่างน้อย 8 ตัว มีตัวอักษรและตัวเลข และต้องไม่ใช่ชื่อผู้ใช้/เบอร์โทร */
    public static function rules(User $user): array
    {
        return ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers(), function ($attr, $value, $fail) use ($user) {
            $weak = array_filter([$user->username, $user->phone, $user->phone ? substr($user->phone, -6) : null]);
            if (in_array($value, $weak, true) || str_contains((string) $value, (string) $user->username)) {
                $fail('รหัสผ่านต้องไม่ใช่ชื่อผู้ใช้หรือเบอร์โทร');
            }
        }];
    }

    /** ข้อความภาษาไทยของกฎรหัสผ่าน */
    public static function messages(string $field = 'password'): array
    {
        return [
            "{$field}.required" => 'กรุณากรอกรหัสผ่านใหม่',
            "{$field}.min" => 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัว',
            "{$field}.letters" => 'รหัสผ่านต้องมีตัวอักษรอย่างน้อย 1 ตัว',
            "{$field}.numbers" => 'รหัสผ่านต้องมีตัวเลขอย่างน้อย 1 ตัว',
            "{$field}.confirmed" => 'พิมพ์รหัสผ่านทั้งสองช่องให้ตรงกัน',
        ];
    }

    public function showChange(Request $request)
    {
        return view('auth.change-password', ['user' => $request->user()]);
    }

    public function change(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(['password' => self::rules($user)], self::messages());
        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสเดิม']);
        }
        $user->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false])->save();
        Audit::log('user.password_set', $user, "{$user->username} ตั้งรหัสผ่านใหม่เอง");

        return redirect()->route('home')->with('success', 'ตั้งรหัสผ่านใหม่เรียบร้อย');
    }

    public function showForgot()
    {
        return view('auth.forgot-password');
    }

    /** ขอรหัสยืนยัน — ตอบข้อความเดียวกันเสมอ ไม่บอกว่ามีบัญชีนี้หรือไม่ */
    public function sendCode(Request $request)
    {
        $data = $request->validate(['username' => ['required', 'string', 'max:100']], [], ['username' => 'ชื่อผู้ใช้หรือเบอร์โทร']);
        $user = self::findUser($data['username']);

        if ($user && $user->line_user_id) {
            $code = (string) random_int(100000, 999999);
            Cache::put(self::key($user), ['hash' => Hash::make($code), 'attempts' => 0], now()->addMinutes(self::CODE_TTL_MINUTES));
            Notifier::users([$user], "🔑 รหัสยืนยันสำหรับตั้งรหัสผ่านใหม่: {$code}\nใช้ได้ ".self::CODE_TTL_MINUTES.' นาที · ถ้าไม่ได้ขอ ไม่ต้องทำอะไร และอย่าบอกรหัสนี้กับใคร');
            Audit::log('user.password_code', $user, "ขอรหัสรีเซ็ตรหัสผ่านทาง LINE ({$user->username})");
        }

        return redirect()->route('password.reset', ['username' => $data['username']])
            ->with('success', 'ถ้าบัญชีนี้เชื่อม LINE ไว้ ระบบส่งรหัสยืนยัน 6 หลักไปทาง LINE แล้ว (ใช้ได้ '.self::CODE_TTL_MINUTES.' นาที)');
    }

    public function showReset(Request $request)
    {
        return view('auth.reset-password', ['username' => (string) $request->query('username')]);
    }

    public function reset(Request $request)
    {
        $request->validate(['username' => ['required', 'string'], 'code' => ['required', 'digits:6']], [], ['code' => 'รหัสยืนยัน']);
        $user = self::findUser($request->input('username'));
        $entry = $user ? Cache::get(self::key($user)) : null;
        $invalid = ValidationException::withMessages(['code' => 'รหัสยืนยันไม่ถูกต้องหรือหมดอายุ กรุณาขอรหัสใหม่']);
        if (! $entry) {
            throw $invalid;
        }
        if (! Hash::check($request->input('code'), $entry['hash'])) {
            $entry['attempts']++;
            $entry['attempts'] >= self::MAX_ATTEMPTS ? Cache::forget(self::key($user)) : Cache::put(self::key($user), $entry, now()->addMinutes(self::CODE_TTL_MINUTES));
            throw $invalid;
        }
        $data = $request->validate(['password' => self::rules($user)], self::messages());

        Cache::forget(self::key($user));
        $user->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false])->save();
        Audit::log('user.password_reset_line', $user, "ตั้งรหัสผ่านใหม่ด้วยรหัสยืนยันทาง LINE ({$user->username})");

        return redirect()->route('login')->with('success', 'ตั้งรหัสผ่านใหม่แล้ว เข้าสู่ระบบด้วยรหัสใหม่ได้เลย');
    }

    /** ค้นแบบเดียวกับหน้าเข้าสู่ระบบ: ชื่อผู้ใช้ อีเมล หรือเบอร์โทร */
    private static function findUser(string $login): ?User
    {
        $login = trim($login);
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : (preg_match('/^0\d{8,9}$/', $login) ? 'phone' : 'username');

        return User::where($field, $login)->where('is_active', true)->first();
    }

    private static function key(User $user): string
    {
        return 'password-reset:'.$user->id;
    }
}
