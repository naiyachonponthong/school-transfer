<?php

namespace Tests;

use App\Models\Term;
use App\Services\Notifier;
use App\Support\Settings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // ฐานข้อมูลถูกย้อนกลับทุกเทสต์ แต่ค่าตั้งค่าที่จำไว้ใน static ไม่ย้อนตาม
        Settings::flush();
        Term::flushCurrent();
        Notifier::resetInlineCount();
    }
}
