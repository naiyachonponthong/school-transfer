<?php

namespace App\Http\Controllers;

use App\Models\PushMessage;
use App\Models\PushSubscription;
use App\Services\WebPush;
use App\Support\Settings;
use Illuminate\Http\Request;

/** แจ้งเตือนบนอุปกรณ์ (Web Push): สมัคร/ยกเลิก และให้ service worker มาดึงข้อความ */
class PushController extends Controller
{
    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:2000', fn ($attr, $value, $fail) => WebPush::allowedEndpoint((string) $value) ? null : $fail('ไม่รองรับบริการแจ้งเตือนของเบราว์เซอร์นี้')],
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            ['user_id' => $request->user()->id, 'endpoint' => $data['endpoint'], 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255)],
        );

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request)
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);
        PushSubscription::where('user_id', $request->user()->id)->where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();

        return response()->json(['ok' => true]);
    }

    /** service worker เรียกเมื่อได้รับสัญญาณ: คืนข้อความที่ยังไม่ได้แสดง (ไม่เกิน 5 รายการล่าสุดใน 24 ชม.) */
    public function pending(Request $request)
    {
        $messages = PushMessage::where('user_id', $request->user()->id)->whereNull('delivered_at')
            ->where('created_at', '>=', now()->subDay())->latest('id')->limit(5)->get();
        PushMessage::where('user_id', $request->user()->id)->whereNull('delivered_at')->update(['delivered_at' => now()]);

        return response()->json([
            'title' => Settings::get('school_short') ?: Settings::get('school_name'),
            'messages' => $messages->reverse()->values()->map(fn ($m) => ['id' => $m->id, 'text' => $m->text, 'url' => $m->url ?: route('notifications')]),
        ]);
    }

    /** ส่งแจ้งเตือนทดสอบถึงอุปกรณ์ของตัวเอง */
    public function test(Request $request)
    {
        $ids = WebPush::notify(collect([$request->user()]), '🔔 ทดสอบการแจ้งเตือนบนอุปกรณ์ ถ้าเห็นข้อความนี้แสดงว่าใช้งานได้', route('notifications'));
        if (! $ids) {
            return back()->with('warning', 'ยังไม่มีอุปกรณ์ที่เปิดรับแจ้งเตือน');
        }
        defer(fn () => WebPush::send($ids));

        return back()->with('success', 'ส่งแจ้งเตือนทดสอบแล้ว');
    }
}
