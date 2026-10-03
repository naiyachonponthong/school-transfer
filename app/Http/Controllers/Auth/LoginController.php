<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const LOCK_SECONDS = 900;

    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // เข้าได้ทั้งชื่อผู้ใช้ อีเมล หรือเบอร์โทร จะได้ไม่ต้องจำหลายอย่าง
        $login = trim($data['username']);
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : (preg_match('/^0\d{8,9}$/', $login) ? 'phone' : 'username');

        // ล็อกตามชื่อผู้ใช้ (ไม่ใช่แค่ IP) กันเดารหัสผ่านของบัญชีเดียวจากหลายเครื่อง
        $key = 'login:'.Str::lower($login);
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages(['username' => 'เข้าระบบผิดหลายครั้ง กรุณาลองใหม่ในอีก '.max(1, (int) ceil(RateLimiter::availableIn($key) / 60)).' นาที']);
        }

        if (! Auth::attempt([$field => $login, 'password' => $data['password'], 'is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($key, self::LOCK_SECONDS);
            throw ValidationException::withMessages(['username' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง']);
        }
        RateLimiter::clear($key);

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
