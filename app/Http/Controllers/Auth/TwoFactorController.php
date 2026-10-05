<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * ยืนยันตัวตน 2 ขั้นด้วยแอปสร้างรหัส (TOTP) สำหรับครูและบุคลากร
 *   - เปิด/ปิดและรหัสสำรองของบัญชีตัวเอง (หน้า /two-factor)
 *   - ด่านกรอกรหัสหลังรหัสผ่านถูกต้อง ก่อนเข้าสู่ระบบจริง (หน้า /two-factor/challenge)
 */
class TwoFactorController extends Controller
{
    private const SETUP_KEY = 'two_factor.setup_secret';

    private const LOGIN_KEY = 'two_factor.login';

    private const CHALLENGE_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    private const LOCK_SECONDS = 900;

    private const RECOVERY_CODES = 8;

    /* ---------------- ด่านกรอกรหัสตอนเข้าสู่ระบบ ---------------- */

    /** รหัสผ่าน (หรือ LINE) ถูกต้องแล้ว: พักไว้ในเซสชัน ยังไม่เข้าสู่ระบบจนกว่าจะกรอกรหัสจากแอป */
    public static function begin(Request $request, User $user, bool $remember): RedirectResponse
    {
        $request->session()->put(self::LOGIN_KEY, ['id' => $user->id, 'remember' => $remember, 'at' => now()->timestamp]);

        return redirect()->route('two-factor.challenge');
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(self::LOGIN_KEY);
        if (! $pending || $pending['at'] < now()->subMinutes(self::CHALLENGE_MINUTES)->timestamp) {
            $request->session()->forget(self::LOGIN_KEY);

            return null;
        }
        $user = User::where('is_active', true)->find($pending['id']);

        return $user?->hasTwoFactor() ? $user : null;
    }

    public function challenge(Request $request)
    {
        return $this->pendingUser($request) ? view('auth.two-factor-challenge') : redirect()->route('login');
    }

    public function verify(Request $request)
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['username' => 'หมดเวลายืนยันตัวตน กรุณาเข้าสู่ระบบใหม่']);
        }
        $data = $request->validate(['code' => ['required', 'string', 'max:20']], [], ['code' => 'รหัสยืนยัน']);

        // ล็อกตามบัญชี กันเดารหัส 6 หลักจากหลายเครื่อง
        $key = 'two-factor:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages(['code' => 'กรอกรหัสผิดหลายครั้ง กรุณาลองใหม่ในอีก '.max(1, (int) ceil(RateLimiter::availableIn($key) / 60)).' นาที']);
        }

        $code = strtoupper(preg_replace('/[\s-]+/', '', $data['code']));
        $recoveryLeft = null;
        if (preg_match('/^\d{'.Totp::DIGITS.'}$/', $code)) {
            $step = Totp::verify($user->two_factor_secret, $code, $user->two_factor_last_step);
            $ok = $step !== null;
            $ok && $user->forceFill(['two_factor_last_step' => $step]);
        } else {
            // รหัสสำรอง: ใช้ได้รหัสละครั้งเดียว
            $hashes = $user->two_factor_recovery_codes ?? [];
            $ok = in_array(self::hashRecoveryCode($code), $hashes, true);
            if ($ok) {
                $hashes = array_values(array_diff($hashes, [self::hashRecoveryCode($code)]));
                $user->forceFill(['two_factor_recovery_codes' => $hashes]);
                $recoveryLeft = count($hashes);
            }
        }
        if (! $ok) {
            RateLimiter::hit($key, self::LOCK_SECONDS);
            throw ValidationException::withMessages(['code' => 'รหัสไม่ถูกต้องหรือหมดอายุแล้ว ลองรหัสใหม่จากแอป']);
        }
        RateLimiter::clear($key);

        $pending = $request->session()->pull(self::LOGIN_KEY);
        Auth::login($user, (bool) ($pending['remember'] ?? false));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        if ($recoveryLeft !== null) {
            Audit::log('user.two_factor', $user, "เข้าสู่ระบบด้วยรหัสสำรองของ {$user->username} (เหลือ {$recoveryLeft} รหัส)");
        }

        return redirect()->intended(route('home'))
            ->with('warning', $recoveryLeft !== null ? "เข้าสู่ระบบด้วยรหัสสำรองแล้ว เหลือรหัสสำรองอีก {$recoveryLeft} รหัส ออกชุดใหม่ได้ที่หน้ายืนยันตัวตน 2 ขั้น" : null);
    }

    /* ---------------- ตั้งค่าของบัญชีตัวเอง ---------------- */

    public function show(Request $request)
    {
        $user = $request->user();
        if ($user->hasTwoFactor()) {
            return view('auth.two-factor', ['user' => $user, 'secret' => null, 'uri' => null,
                'recoveryLeft' => count($user->two_factor_recovery_codes ?? [])]);
        }
        // กุญแจที่ยังไม่ยืนยันอยู่ในเซสชันเท่านั้น รีเฟรชหน้าแล้ว QR จึงเป็นอันเดิม
        $secret = $request->session()->get(self::SETUP_KEY) ?? Totp::generateSecret();
        $request->session()->put(self::SETUP_KEY, $secret);

        return view('auth.two-factor', ['user' => $user, 'secret' => $secret, 'recoveryLeft' => 0,
            'uri' => Totp::uri($secret, $user->username, Settings::get('school_short') ?: Settings::get('school_name'))]);
    }

    public function enable(Request $request)
    {
        $user = $request->user();
        if ($user->hasTwoFactor()) {
            return redirect()->route('two-factor.setup');
        }
        $data = $request->validate(['code' => ['required', 'string', 'max:10']], [], ['code' => 'รหัสจากแอป']);
        $secret = $request->session()->get(self::SETUP_KEY);
        $step = $secret ? Totp::verify($secret, $data['code']) : null;
        if ($step === null) {
            throw ValidationException::withMessages(['code' => 'รหัสไม่ถูกต้อง ตรวจว่าสแกน QR อันล่าสุด และเวลาของมือถือตั้งเป็นอัตโนมัติ']);
        }

        $codes = self::newRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $secret, 'two_factor_confirmed_at' => now(), 'two_factor_last_step' => $step,
            'two_factor_recovery_codes' => array_map([self::class, 'hashRecoveryCode'], $codes),
        ])->save();
        $request->session()->forget(self::SETUP_KEY);
        Audit::log('user.two_factor', $user, "เปิดการยืนยันตัวตน 2 ขั้นของ {$user->username} ({$user->name})");

        return redirect()->route('two-factor.setup')->with('success', 'เปิดการยืนยันตัวตน 2 ขั้นแล้ว')->with('recovery_codes', $codes);
    }

    /** ออกรหัสสำรองชุดใหม่ (ชุดเดิมใช้ไม่ได้ทันที) */
    public function recoveryCodes(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasTwoFactor(), 404);
        $this->confirmPassword($request);

        $codes = self::newRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => array_map([self::class, 'hashRecoveryCode'], $codes)])->save();
        Audit::log('user.two_factor', $user, "ออกรหัสสำรองชุดใหม่ของ {$user->username}");

        return redirect()->route('two-factor.setup')->with('success', 'ออกรหัสสำรองชุดใหม่แล้ว')->with('recovery_codes', $codes);
    }

    public function disable(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasTwoFactor(), 404);
        $this->confirmPassword($request);

        self::clear($user);
        Audit::log('user.two_factor', $user, "ปิดการยืนยันตัวตน 2 ขั้นของ {$user->username} ({$user->name})");

        return redirect()->route('two-factor.setup')->with('success', 'ปิดการยืนยันตัวตน 2 ขั้นแล้ว');
    }

    private function confirmPassword(Request $request): void
    {
        $request->validate(['current_password' => ['required', 'current_password']],
            ['current_password.current_password' => 'รหัสผ่านไม่ถูกต้อง'], ['current_password' => 'รหัสผ่าน']);
    }

    /** ล้างการยืนยันตัวตน 2 ขั้นของบัญชี (เจ้าตัวปิดเอง ผู้ดูแลล้างให้เมื่อมือถือหาย หรือคำสั่ง users:disable-2fa) */
    public static function clear(User $user): void
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();
    }

    /** @return list<string> รหัสสำรองรูปแบบ XXXXX-XXXXX (ไม่มีตัวอักษรที่อ่านสับสน) */
    private static function newRecoveryCodes(): array
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $code = '';
            for ($j = 0; $j < 10; $j++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $codes[] = substr($code, 0, 5).'-'.substr($code, 5);
        }

        return $codes;
    }

    /** เก็บเฉพาะค่าแฮช รหัสจริงแสดงครั้งเดียวตอนออก */
    private static function hashRecoveryCode(string $code): string
    {
        return hash('sha256', strtoupper(preg_replace('/[\s-]+/', '', $code)));
    }
}
