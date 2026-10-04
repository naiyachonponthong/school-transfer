<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * เข้าสู่ระบบด้วย LINE (LINE Login v2.1) และเชื่อมบัญชี LINE จากหน้าข้อมูลส่วนตัว
 * ใช้ users.line_user_id ตัวเดียวกับการแจ้งเตือนทาง LINE OA — ช่อง LINE Login กับ Messaging API
 * ต้องอยู่ใต้ Provider เดียวกันใน LINE Developers รหัสผู้ใช้จึงจะตรงกัน
 */
class LineLoginController extends Controller
{
    public static function configured(): bool
    {
        return filled(Settings::get('line_login_channel_id')) && filled(Settings::get('line_login_channel_secret'));
    }

    public function redirect(Request $request)
    {
        abort_unless(self::configured(), 404);
        $state = Str::random(40);
        // ผู้ที่เข้าสู่ระบบอยู่แล้ว = ขอเชื่อมบัญชี LINE เข้ากับผู้ใช้นี้
        $request->session()->put('line_login', ['state' => $state, 'link' => $request->user()?->id]);

        return redirect()->away('https://access.line.me/oauth2/v2.1/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => Settings::get('line_login_channel_id'),
            'redirect_uri' => route('line.callback'),
            'state' => $state,
            'scope' => 'openid profile',
        ]));
    }

    public function callback(Request $request)
    {
        abort_unless(self::configured(), 404);
        $pending = $request->session()->pull('line_login');
        $linking = $pending['link'] ?? null;
        $fail = fn (string $message) => $linking && $request->user()
            ? redirect()->route('profile')->with('warning', $message)
            : redirect()->route('login')->withErrors(['username' => $message]);

        if (! $pending || ! hash_equals($pending['state'], (string) $request->query('state')) || ! $request->query('code')) {
            return $fail($request->query('error') ? 'ยกเลิกการเข้าสู่ระบบด้วย LINE' : 'การยืนยันตัวกับ LINE ไม่สมบูรณ์ กรุณาลองใหม่');
        }

        $lineId = $this->lineUserId((string) $request->query('code'));
        if (! $lineId) {
            return $fail('ยืนยันตัวกับ LINE ไม่สำเร็จ กรุณาลองใหม่');
        }

        if ($linking) {
            $user = $request->user();
            if (! $user || $user->id !== $linking) {
                return $fail('กรุณาเข้าสู่ระบบก่อนเชื่อม LINE');
            }
            if (User::where('line_user_id', $lineId)->where('id', '!=', $user->id)->exists()) {
                return $fail('บัญชี LINE นี้เชื่อมกับผู้ใช้อื่นอยู่แล้ว');
            }
            $user->forceFill(['line_user_id' => $lineId, 'line_linked_at' => now(), 'line_link_code' => null])->save();

            return redirect()->route('profile')->with('success', 'เชื่อม LINE แล้ว ครั้งต่อไปเข้าสู่ระบบด้วย LINE ได้เลย');
        }

        $user = User::where('line_user_id', $lineId)->where('is_active', true)->orderBy('id')->first();
        if (! $user) {
            return $fail('บัญชี LINE นี้ยังไม่ได้เชื่อมกับผู้ใช้ในระบบ เข้าสู่ระบบด้วยรหัสผ่านก่อน แล้วเชื่อม LINE ที่หน้าข้อมูลส่วนตัว');
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('home'));
    }

    /** แลก code เป็น ID token แล้วให้ LINE ตรวจ token นั้น คืนรหัสผู้ใช้ LINE (sub) */
    private function lineUserId(string $code): ?string
    {
        $clientId = (string) Settings::get('line_login_channel_id');
        try {
            $token = Http::asForm()->timeout(10)->post('https://api.line.me/oauth2/v2.1/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => route('line.callback'),
                'client_id' => $clientId,
                'client_secret' => Settings::get('line_login_channel_secret'),
            ]);
            if (! $token->successful() || ! $token->json('id_token')) {
                return null;
            }
            $verified = Http::asForm()->timeout(10)->post('https://api.line.me/oauth2/v2.1/verify', [
                'id_token' => $token->json('id_token'),
                'client_id' => $clientId,
            ]);

            return $verified->successful() && $verified->json('aud') === $clientId ? ($verified->json('sub') ?: null) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
