<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * ไม่ใช้ RefreshDatabase ที่นี่โดยตั้งใจ: ทุกเทสต์ในสวีทใช้ฐานข้อมูล sqlite ":memory:"
 * ตัวเดียวร่วมกันตลอดทั้งโปรเซส (พฤติกรรมมาตรฐานของ Laravel) `school:install` รัน
 * migrate:fresh เอง ซึ่งบน SQLite จะ VACUUM หลังล้างตาราง — ทำไม่ได้ทั้งในทรานแซกชันของ
 * RefreshDatabase และจะไปรบกวนสคีมาที่คลาสทดสอบอื่นใช้ร่วมกันด้วย จึงแยกไปใช้ไฟล์ sqlite
 * ชั่วคราวของตัวเองต่างหาก ปลอดภัยจากทั้งสองปัญหา
 */
class SchoolInstallCommandTest extends TestCase
{
    private string $dbFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dbFile = tempnam(sys_get_temp_dir(), 'school-install-test-').'.sqlite';
        touch($this->dbFile);
        config(['database.connections.sqlite.database' => $this->dbFile]);
        DB::purge('sqlite');
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        @unlink($this->dbFile);
        parent::tearDown();
    }

    public function test_install_command_creates_clean_admin_with_no_demo_data(): void
    {
        $this->artisan('school:install', [
            '--force' => true,
            '--name' => 'โรงเรียนทดสอบ',
            '--admin-name' => 'ผู้ดูแลระบบ',
            '--admin-user' => 'testadmin',
            '--admin-password' => 'StrongPass123',
        ])->assertSuccessful();

        $this->assertSame(1, User::count());
        $admin = User::first();
        $this->assertSame('testadmin', $admin->username);
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('StrongPass123', $admin->password));
        $this->assertSame('โรงเรียนทดสอบ', Settings::get('school_name'));

        // ไม่มีข้อมูลตัวอย่างติดมาด้วย
        $this->assertSame(0, Student::count());

        $this->post('/login', ['username' => 'testadmin', 'password' => 'StrongPass123'])->assertRedirect('/');
        $this->assertAuthenticated();
    }
}
