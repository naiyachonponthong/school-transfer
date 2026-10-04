<?php

namespace Tests\Feature;

use App\Models\PushMessage;
use App\Models\PushSubscription;
use App\Models\Student;
use App\Models\User;
use App\Services\Notifier;
use App\Services\WebPush;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** แจ้งเตือนบนอุปกรณ์ (Web Push) และเข้าสู่ระบบด้วย LINE */
class WebPushAndLineLoginTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/abc123';

    private function parentUser(): User
    {
        return User::where('phone', '0812345678')->first();
    }

    public function test_only_known_push_services_can_be_subscribed(): void
    {
        $this->assertTrue(WebPush::allowedEndpoint(self::ENDPOINT));
        $this->assertTrue(WebPush::allowedEndpoint('https://updates.push.services.mozilla.com/wpush/v2/x'));
        $this->assertTrue(WebPush::allowedEndpoint('https://db5p.notify.windows.com/w/?token=x'));
        $this->assertFalse(WebPush::allowedEndpoint('http://fcm.googleapis.com/x'));
        $this->assertFalse(WebPush::allowedEndpoint('https://localhost/admin'));
        $this->assertFalse(WebPush::allowedEndpoint('https://evil.example/fcm.googleapis.com'));

        $parent = $this->parentUser();
        $this->actingAs($parent)->postJson(route('push.subscribe'), ['endpoint' => 'https://internal.example/hook'])->assertStatus(422);
        $this->postJson(route('push.subscribe'), ['endpoint' => self::ENDPOINT])->assertOk();
        $this->postJson(route('push.subscribe'), ['endpoint' => self::ENDPOINT])->assertOk();
        $this->assertSame(1, PushSubscription::count());
        $this->get(route('profile'))->assertOk()->assertSee('แจ้งเตือนบนอุปกรณ์นี้')->assertSee('อุปกรณ์ที่เปิดรับอยู่ 1 เครื่อง');

        $this->deleteJson(route('push.unsubscribe'), ['endpoint' => self::ENDPOINT])->assertOk();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_notifications_reach_subscribed_devices_without_line(): void
    {
        $this->withoutDefer(); // งานที่เลื่อนไว้หลังตอบหน้าเว็บ ให้ทำทันที
        Http::fake(['fcm.googleapis.com/*' => Http::response('', 201)]);
        $parent = $this->parentUser();
        $parent->forceFill(['line_user_id' => null])->save();
        $child = $parent->children()->first();
        $this->actingAs($parent)->postJson(route('push.subscribe'), ['endpoint' => self::ENDPOINT])->assertOk();

        Notifier::parents($child, 'น้องมาถึงโรงเรียนแล้ว', route('parent.home'));

        // ส่งสัญญาณเปล่าพร้อมโทเคน VAPID ที่ตรวจลายเซ็นได้ด้วยกุญแจสาธารณะของระบบ
        Http::assertSent(function (HttpRequest $r) {
            if ($r->url() !== self::ENDPOINT || $r->body() !== '' || ! preg_match('/^vapid t=([\w-]+)\.([\w-]+)\.([\w-]+), k=([\w-]+)$/', $r->header('Authorization')[0], $m)) {
                return false;
            }
            $claims = json_decode(base64_decode(strtr($m[2], '-_', '+/')), true);
            $raw = base64_decode(strtr($m[3], '-_', '+/'));
            $int = fn (string $b) => "\x02".chr(strlen($b = (ord(($b = ltrim($b, "\0") ?: "\0")[0]) & 0x80 ? "\0" : '').$b)).$b;
            $body = $int(substr($raw, 0, 32)).$int(substr($raw, 32));
            $pem = openssl_pkey_get_details(openssl_pkey_get_private(Settings::get('webpush_private_key')))['key'];

            return $m[4] === Settings::get('webpush_public_key') && $claims['aud'] === 'https://fcm.googleapis.com' && $claims['exp'] > time()
                && strlen($raw) === 64 && openssl_verify($m[1].'.'.$m[2], "\x30".chr(strlen($body)).$body, $pem, OPENSSL_ALGO_SHA256) === 1;
        });

        // service worker มาดึงข้อความ: ได้ครั้งเดียว และเห็นเฉพาะของตัวเอง
        $this->assertSame(1, PushMessage::where('user_id', $parent->id)->count());
        $this->getJson(route('push.pending'))->assertOk()->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.url', route('parent.home'));
        $this->getJson(route('push.pending'))->assertOk()->assertJsonCount(0, 'messages');
        $this->actingAs(User::where('username', 'admin')->first())->getJson(route('push.pending'))->assertJsonCount(0, 'messages');
    }

    public function test_expired_subscriptions_are_removed(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response('', 410)]);
        $parent = $this->parentUser();
        $this->actingAs($parent)->postJson(route('push.subscribe'), ['endpoint' => self::ENDPOINT])->assertOk();

        WebPush::send(WebPush::notify(collect([$parent]), 'ทดสอบ'));
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_line_login_signs_in_linked_users_and_links_from_profile(): void
    {
        $this->get(route('line.login'))->assertNotFound();
        $this->get(route('login'))->assertOk()->assertDontSee('เข้าสู่ระบบด้วย LINE');

        Settings::set(['line_login_channel_id' => '1650000001', 'line_login_channel_secret' => 'secret']);
        Http::fake([
            'api.line.me/oauth2/v2.1/token' => Http::response(['id_token' => 'tok']),
            'api.line.me/oauth2/v2.1/verify' => Http::response(['sub' => 'Uabc', 'aud' => '1650000001']),
        ]);
        $this->get(route('login'))->assertOk()->assertSee('เข้าสู่ระบบด้วย LINE');

        // ยังไม่เคยเชื่อม: กลับไปหน้าเข้าสู่ระบบพร้อมคำแนะนำ
        $state = fn () => session('line_login.state');
        $this->get(route('line.login'))->assertRedirectContains('https://access.line.me/oauth2/v2.1/authorize');
        $this->get(route('line.callback', ['code' => 'c', 'state' => $state()]))->assertRedirect(route('login'))->assertSessionHasErrors('username');
        $this->assertGuest();

        // state ไม่ตรง = ปฏิเสธ
        $this->get(route('line.login'));
        $this->get(route('line.callback', ['code' => 'c', 'state' => 'wrong']))->assertRedirect(route('login'));
        $this->assertGuest();

        // เชื่อมจากหน้าข้อมูลส่วนตัว
        $parent = $this->parentUser();
        $parent->forceFill(['line_user_id' => null])->save();
        $this->actingAs($parent)->get(route('profile'))->assertOk()->assertSee('เชื่อมด้วยบัญชี LINE');
        $this->get(route('line.login'));
        $this->get(route('line.callback', ['code' => 'c', 'state' => $state()]))->assertRedirect(route('profile'));
        $this->assertSame('Uabc', $parent->fresh()->line_user_id);

        // บัญชี LINE เดียวกันเชื่อมกับผู้ใช้อื่นซ้ำไม่ได้
        $admin = User::where('username', 'admin')->first();
        $admin->forceFill(['line_user_id' => null])->save();
        $this->actingAs($admin)->get(route('line.login'));
        $this->get(route('line.callback', ['code' => 'c', 'state' => $state()]))->assertRedirect(route('profile'))->assertSessionHas('warning');
        $this->assertNull($admin->fresh()->line_user_id);

        // ครั้งต่อไปเข้าสู่ระบบด้วย LINE ได้ · บัญชีที่ถูกปิดเข้าไม่ได้
        $this->post(route('logout'));
        $this->get(route('line.login'));
        $this->get(route('line.callback', ['code' => 'c', 'state' => $state()]))->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($parent);

        $this->post(route('logout'));
        $parent->update(['is_active' => false]);
        $this->get(route('line.login'));
        $this->get(route('line.callback', ['code' => 'c', 'state' => $state()]))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_line_login_secret_is_stored_encrypted_and_kept_when_left_blank(): void
    {
        $admin = User::where('username', 'admin')->first();
        $base = ['school_name' => 'x', 'late_time' => '08:00', 'staff_late_time' => '08:00', 'periods_per_day' => 7, 'theme_color' => Settings::get('theme_color')];
        $this->actingAs($admin)->post(route('settings.update'), $base + ['line_login_channel_id' => '1650000001', 'line_login_channel_secret' => 'topsecret'])->assertSessionHasNoErrors();
        $this->assertNotSame('topsecret', \DB::table('settings')->where('key', 'line_login_channel_secret')->value('value'));
        $this->assertSame('topsecret', Settings::get('line_login_channel_secret'));

        $this->post(route('settings.update'), $base + ['line_login_channel_id' => '1650000001', 'line_login_channel_secret' => ''])->assertSessionHasNoErrors();
        $this->assertSame('topsecret', Settings::get('line_login_channel_secret'));
        $this->get(route('settings'))->assertOk()->assertSee('LINE Login')->assertDontSee('topsecret');
    }
}
