<?php

namespace Tests\Feature;

use App\Models\User;
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

    public function test_backup_command_declines_when_there_is_no_database_file(): void
    {
        // การทดสอบใช้ sqlite :memory: (phpunit.xml) ไม่มีไฟล์ให้สำรอง จึงต้องปฏิเสธอย่างปลอดภัย ไม่ error
        $this->artisan('backup:database')->assertFailed();
    }

    /** URL ของหน้าเว็บต้องไม่ขึ้นต้นด้วยชื่อโฟลเดอร์ใน public/ (เช่น /assets) ไม่งั้นเว็บเซิร์ฟเวอร์จะเสิร์ฟโฟลเดอร์แทนหน้าเว็บ */
    public function test_no_route_collides_with_a_public_directory(): void
    {
        $dirs = array_map('basename', glob(public_path('*'), GLOB_ONLYDIR));
        $clashes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->getActionName(), 'App\\')) // route storage/{path} ของ Laravel เองตั้งใจให้ตรงกับ public/storage
            ->map(fn ($r) => $r->uri())
            ->filter(fn ($uri) => in_array(strtok($uri, '/'), $dirs, true))
            ->values()->all();
        $this->assertSame([], $clashes, 'route ชนกับโฟลเดอร์ใน public/');
    }
}
