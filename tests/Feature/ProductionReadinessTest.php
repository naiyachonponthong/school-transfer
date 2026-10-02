<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_command_refuses_without_force_when_users_exist(): void
    {
        $countBefore = User::count();
        User::factory()->create(['username' => 'existing-guard-'.uniqid()]);
        $this->artisan('school:install', ['--name' => 'x'])
            ->expectsOutputToContain('มีผู้ใช้งานอยู่แล้ว')
            ->assertFailed();
        $this->assertSame($countBefore + 1, User::count());
    }

    public function test_install_command_rejects_weak_password(): void
    {
        $this->artisan('school:install', [
            '--force' => true, '--name' => 'x', '--admin-name' => 'y', '--admin-user' => 'z', '--admin-password' => '123',
        ])->assertFailed();
        $this->assertFalse(User::where('username', 'z')->exists());
    }

    public function test_backup_command_declines_on_non_mysql_connection(): void
    {
        // การทดสอบใช้ sqlite เสมอ (phpunit.xml) จึงต้องปฏิเสธอย่างปลอดภัย ไม่ error
        $this->artisan('backup:database')->assertFailed();
    }
}
