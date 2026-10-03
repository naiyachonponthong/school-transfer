<?php

namespace App\Services;

use App\Models\MessageLog;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * LINE Messaging API (LINE Official Account)
 * หมายเหตุ: LINE Notify ปิดบริการแล้วตั้งแต่ 31 มี.ค. 2568 จึงใช้ Messaging API แทน
 * ตั้งค่า Channel access token / Channel secret ที่หน้า "ตั้งค่าโรงเรียน"
 */
class Line
{
    public static function configured(): bool
    {
        return filled(Settings::get('line_channel_token'));
    }

    public static function verifySignature(string $body, ?string $signature): bool
    {
        $secret = (string) Settings::get('line_channel_secret');
        if ($secret === '' || ! $signature) {
            return false;
        }

        return hash_equals(base64_encode(hash_hmac('sha256', $body, $secret, true)), $signature);
    }

    /** ส่งข้อความถึงผู้ใช้หลายคน (เฉพาะคนที่เชื่อม LINE แล้ว) */
    public static function send(iterable $users, string $text): Collection
    {
        // ค่าที่คืน = คนที่ส่งไม่ถึงเพราะปัญหาชั่วคราว (เครือข่าย/LINE ล่ม/ถูกจำกัดความถี่) ควรส่งซ้ำภายหลัง
        $retry = collect();
        $users = collect($users)->filter(fn (User $u) => $u->line_user_id)->unique('id');
        if ($users->isEmpty()) {
            return $retry;
        }
        $text = mb_substr($text, 0, 4900);

        if (! self::configured()) {
            self::log($users, $text, 'skipped', 'ยังไม่ได้ตั้งค่า LINE Channel access token');

            return $retry;
        }

        // multicast ได้ครั้งละ 500 คน
        foreach ($users->chunk(500) as $chunk) {
            try {
                $res = Http::withToken((string) Settings::get('line_channel_token'))->timeout(10)
                    ->post('https://api.line.me/v2/bot/message/multicast', [
                        'to' => $chunk->pluck('line_user_id')->values()->all(),
                        'messages' => [['type' => 'text', 'text' => $text]],
                    ]);
                if ($res->successful()) {
                    self::log($chunk, $text, 'sent');

                    continue;
                }
                self::log($chunk, $text, 'failed', 'HTTP '.$res->status().' '.mb_substr($res->body(), 0, 180));
                // 4xx อื่น ๆ (token ผิด/โควตาหมด/ข้อความผิดรูปแบบ) ส่งซ้ำก็ไม่ผ่าน
                if ($res->serverError() || $res->status() === 429) {
                    $retry = $retry->concat($chunk);
                }
            } catch (\Throwable $e) {
                self::log($chunk, $text, 'failed', mb_substr($e->getMessage(), 0, 190));
                $retry = $retry->concat($chunk);
            }
        }

        return $retry;
    }

    /** จำนวนข้อความที่ส่งสำเร็จในเดือนนี้ (LINE คิดโควตาตามจำนวนผู้รับ) */
    public static function sentThisMonth(): int
    {
        return MessageLog::where('channel', 'line')->where('status', 'sent')->where('created_at', '>=', now()->startOfMonth())->count();
    }

    /** ตอบกลับข้อความใน webhook */
    public static function reply(string $replyToken, string $text): void
    {
        if (! self::configured()) {
            return;
        }
        try {
            Http::withToken((string) Settings::get('line_channel_token'))->timeout(10)
                ->post('https://api.line.me/v2/bot/message/reply', [
                    'replyToken' => $replyToken,
                    'messages' => [['type' => 'text', 'text' => $text]],
                ]);
        } catch (\Throwable) {
            // webhook ต้องตอบ 200 เสมอ ไม่ให้ LINE ส่งซ้ำ
        }
    }

    private static function log(Collection $users, string $text, string $status, ?string $error = null): void
    {
        $now = now();
        MessageLog::insert($users->map(fn ($u) => [
            'channel' => 'line', 'user_id' => $u->id, 'text' => $text, 'status' => $status, 'error' => $error,
            'created_at' => $now, 'updated_at' => $now,
        ])->values()->all());
    }
}
