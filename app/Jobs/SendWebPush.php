<?php

namespace App\Jobs;

use App\Services\WebPush;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** ส่งสัญญาณแจ้งเตือนบนอุปกรณ์ผ่านคิว (ใช้กับการส่งจำนวนมากและคำสั่งตามเวลา) */
class SendWebPush implements ShouldQueue
{
    use Queueable;

    /** @param  list<int>  $userIds */
    public function __construct(public array $userIds) {}

    public function handle(): void
    {
        WebPush::send($this->userIds);
    }
}
