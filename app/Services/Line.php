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
    public static function send(iterable $users, string $text): void
    {
        $users = collect($users)->filter(fn (User $u) => $u->line_user_id)->unique('id');
        if ($users->isEmpty()) {
            return;
        }
        $text = mb_substr($text, 0, 4900);

        if (! self::configured()) {
            self::log($users, $text, 'skipped', 'ยังไม่ได้ตั้งค่า LINE Channel access token');

            return;
        }

        // multicast ได้ครั้งละ 500 คน
        foreach ($users->chunk(500) as $chunk) {
            try {
                $res = Http::withToken((string) Settings::get('line_channel_token'))->timeout(10)
                    ->post('https://api.line.me/v2/bot/message/multicast', [
                        'to' => $chunk->pluck('line_user_id')->values()->all(),
                        'messages' => [['type' => 'text', 'text' => $text]],
                    ]);
                $res->successful()
                    ? self::log($chunk, $text, 'sent')
                    : self::log($chunk, $text, 'failed', 'HTTP '.$res->status().' '.mb_substr($res->body(), 0, 180));
            } catch (\Throwable $e) {
                self::log($chunk, $text, 'failed', mb_substr($e->getMessage(), 0, 190));
            }
        }
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
