<?php

namespace App\Http\Controllers;

use App\Models\MessageLog;
use App\Models\User;
use App\Services\Line;
use Illuminate\Http\Request;

class LineController extends Controller
{
    /**
     * Webhook จาก LINE Official Account
     * ผู้ใช้เชื่อมบัญชีโดยพิมพ์รหัส 6 หลักจากหน้า "บัญชีของฉัน" ในแชทของ OA
     */
    public function webhook(Request $request)
    {
        $body = $request->getContent();
        if (! Line::verifySignature($body, $request->header('X-Line-Signature'))) {
            return response('invalid signature', 403);
        }

        foreach ((array) ($request->json('events') ?? []) as $event) {
            $lineId = $event['source']['userId'] ?? null;
            $reply = $event['replyToken'] ?? null;
            if (! $lineId) {
                continue;
            }

            if (($event['type'] ?? '') === 'unfollow') {
                User::where('line_user_id', $lineId)->update(['line_user_id' => null, 'line_linked_at' => null]);

                continue;
            }
            if (($event['type'] ?? '') === 'follow') {
                $reply && Line::reply($reply, 'สวัสดีครับ 🙏 พิมพ์รหัส 6 หลักจากเมนู "บัญชีของฉัน" ในระบบโรงเรียน เพื่อรับแจ้งเตือนการมาเรียน ใบลา และข่าวสารของบุตรหลานทาง LINE');

                continue;
            }
            if (($event['type'] ?? '') === 'message' && ($event['message']['type'] ?? '') === 'text') {
                $code = preg_replace('/\D/', '', (string) $event['message']['text']);
                $user = strlen($code) === 6 ? User::where('line_link_code', $code)->first() : null;
                if ($user) {
                    // บัญชี LINE เดียวผูกได้บัญชีระบบเดียว
                    User::where('line_user_id', $lineId)->where('id', '!=', $user->id)->update(['line_user_id' => null, 'line_linked_at' => null]);
                    $user->forceFill(['line_user_id' => $lineId, 'line_link_code' => null, 'line_linked_at' => now()])->save();
                    $reply && Line::reply($reply, "เชื่อมบัญชีสำเร็จ ✅\nคุณ{$user->name} จะได้รับแจ้งเตือนจากโรงเรียนทาง LINE นี้");
                } elseif ($reply) {
                    Line::reply($reply, 'ไม่พบรหัสนี้ กรุณาตรวจสอบรหัส 6 หลักในหน้า "บัญชีของฉัน" อีกครั้ง');
                }
            }
        }

        return response('ok');
    }

    public function createCode(Request $request)
    {
        do {
            $code = (string) random_int(100000, 999999);
        } while (User::where('line_link_code', $code)->exists());
        $request->user()->forceFill(['line_link_code' => $code])->save();

        return back()->with('success', 'สร้างรหัสเชื่อม LINE แล้ว');
    }

    public function unlink(Request $request)
    {
        $request->user()->forceFill(['line_user_id' => null, 'line_linked_at' => null, 'line_link_code' => null])->save();

        return back()->with('success', 'ยกเลิกการเชื่อม LINE แล้ว');
    }

    /** ผู้ดูแล: ประวัติการส่ง + ทดสอบส่ง */
    public function logs()
    {
        return view('settings.messages', [
            'logs' => MessageLog::with('user')->latest('id')->paginate(50),
            'linked' => User::whereNotNull('line_user_id')->count(),
            'parents' => User::where('role', 'parent')->count(),
            'configured' => Line::configured(),
        ]);
    }

    public function test(Request $request)
    {
        $user = $request->user();
        if (! $user->hasLine()) {
            return back()->withErrors(['line' => 'บัญชีของคุณยังไม่ได้เชื่อม LINE (ไปที่ "บัญชีของฉัน")']);
        }
        Line::send([$user], '🔔 ทดสอบการแจ้งเตือนจากระบบโรงเรียน');
        $last = MessageLog::where('user_id', $user->id)->latest('id')->first();

        return back()->with($last?->status === 'sent' ? 'success' : 'warning', 'ผลการส่ง: '.($last?->status ?? '-').($last?->error ? ' — '.$last->error : ''));
    }
}
