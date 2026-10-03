<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Line;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * ส่งข้อความ LINE ผ่านคิว ใช้กับการส่งจำนวนมาก (เช่น ออกใบแจ้งหนี้ทั้งชั้น) และการส่งซ้ำเมื่อ LINE ล่มชั่วคราว
 * ส่งซ้ำเฉพาะคนที่ยังไม่ได้รับ ไม่เกิน MAX_ATTEMPTS ครั้ง
 */
class SendLineMessage implements ShouldQueue
{
    use Queueable;

    public const MAX_ATTEMPTS = 3;

    /** @param  list<int>  $userIds */
    public function __construct(public array $userIds, public string $text, public int $attempt = 1) {}

    public function handle(): void
    {
        $failed = Line::send(User::whereIn('id', $this->userIds)->get(), $this->text);

        if ($failed->isNotEmpty() && $this->attempt < self::MAX_ATTEMPTS) {
            self::dispatch($failed->pluck('id')->all(), $this->text, $this->attempt + 1)->delay(now()->addMinutes(2 * $this->attempt));
        }
    }
}
