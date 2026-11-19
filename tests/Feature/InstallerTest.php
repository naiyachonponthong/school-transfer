<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DbBackup;
use App\Support\Installer;
use App\Support\SchoolSetup;
use App\Support\Settings;
use App\Support\WebCron;
use Dotenv\Dotenv;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    public function test_env_values_round_trip_through_dotenv(): void
    {
        $values = [
            'A' => 'simple', 'B' => 'p@ss w0rd', 'C' => 'has#hash', 'D' => 'dollar$HOME', 'E' => "single'quote\"double",
            'F' => 'back\\slash$x', 'G' => '', 'H' => 'https://school.example.com/sub', 'I' => true,
        ];
        $env = '';
        foreach ($values as $k => $v) {
            $env .= $k.'='.Installer::envValue($v)."\n";
        }
        $parsed = Dotenv::parse($env);

        foreach ($values as $k => $v) {
            $this->assertSame(is_bool($v) ? ($v ? 'true' : 'false') : $v, $parsed[$k], "key $k");
        }
    }

    public function test_ini_sizes_are_parsed(): void
    {
        $this->assertSame(8 * 1048576, Installer::bytes('8M'));
        $this->assertSame(2 * 1073741824, Installer::bytes('2G'));
        $this->assertSame(512 * 1024, Installer::bytes('512K'));
        $this->assertSame(-1, Installer::bytes('-1'));
    }

    public function test_requirements_report_php_and_extensions(): void
    {
        $groups = Installer::requirements();

        $this->assertArrayHasKey('PHP', $groups);
        $this->assertTrue($groups['PHP'][0]['ok']);
        $this->assertNotEmpty($groups['Extension']);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', Installer::phpNeed());
    }

    public function test_mysql_connection_errors_are_explained_in_thai(): void
    {
        $r = Installer::testDatabase(['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'x', 'password' => 'x']);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('เชื่อมต่อ', $r['message']);
    }

    public function test_backup_writes_restorable_sql(): void
    {
        $user = User::factory()->create(['name' => "บรรทัด 1\nบรรทัด 2 'quote' ; end;"]);
        $path = storage_path('app/backups/test-'.uniqid().'.sql.gz');

        try {
            DbBackup::create($path);
            $sql = gzdecode((string) file_get_contents($path));
            $this->assertStringContainsString('CREATE TABLE "users"', $sql);
            $this->assertStringContainsString('INSERT INTO "migrations"', $sql);

            // นำเข้าคืนแล้วข้อมูลต้องตรง (ข้อความหลายบรรทัดและมี ; ต้องไม่ทำให้แยกคำสั่งผิด)
            $before = DB::table('users')->count();
            $user->forceFill(['name' => 'changed'])->save();
            User::factory()->create();
            DbBackup::restore($path);
            $this->assertSame($before, DB::table('users')->count());
            $this->assertSame("บรรทัด 1\nบรรทัด 2 'quote' ; end;", DB::table('users')->where('id', $user->id)->value('name'));
        } finally {
            @unlink($path);
        }
    }

    public function test_school_setup_creates_or_takes_over_first_admin(): void
    {
        User::factory()->create(['username' => 'boss', 'role' => 'teacher']);
        // บางชุดเทสต์ seed ฐานข้อมูลไว้แล้ว (มีผู้ดูแลอยู่) ไม่มีค่อยสร้าง
        $seeded = User::where('role', 'admin')->orderBy('id')->first()
            ?? User::factory()->create(['username' => 'seed-admin', 'role' => 'admin', 'must_change_password' => true]);
        $seeded->forceFill(['must_change_password' => true])->save();

        SchoolSetup::school('โรงเรียนทดสอบ');
        $admin = SchoolSetup::admin('ผู้ดูแลใหม่', 'boss', Hash::make('secret123'));

        $this->assertSame($seeded->id, $admin->id);
        $this->assertSame('boss', $admin->fresh()->username);
        $this->assertFalse($admin->fresh()->must_change_password);
        $this->assertTrue(Hash::check('secret123', $admin->fresh()->password));
        $this->assertSame(1, User::where('username', 'boss')->count()); // บัญชีเดิมที่ชื่อซ้ำถูกย้ายชื่อออก
        $this->assertSame('โรงเรียนทดสอบ', Settings::get('school_name'));
        $this->assertSame('โรงเรียนทดสอบ', Settings::get('school_short'));
    }

    public function test_web_cron_runs_due_tasks_once_without_backfilling_on_first_sight(): void
    {
        $count = 0;
        app(Schedule::class)->call(function () use (&$count) {
            $count++;
        })->everyMinute()->name('test-counter');

        WebCron::runDueEvents(); // เห็นครั้งแรก: จำรอบไว้ ยังไม่รัน
        $this->assertSame(0, $count);

        $this->travel(1)->minutes();
        WebCron::runDueEvents();
        WebCron::runDueEvents(); // นาทีเดียวกันห้ามรันซ้ำ
        $this->assertSame(1, $count);
    }

    public function test_web_cron_stops_when_real_cron_heartbeat_is_fresh(): void
    {
        $this->assertFalse(WebCron::realCronAlive());
        Cache::forever('school.scheduler_last', now()->toDateTimeString());
        $this->assertTrue(WebCron::realCronAlive());
    }

    public function test_storage_fallback_serves_public_files_only(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('photos/a.png', UploadedFile::fake()->image('a.png')->getContent());

        $this->get('/storage/photos/a.png')->assertOk();
        $this->get('/storage/../../.env')->assertNotFound();
        $this->get('/storage/photos/missing.png')->assertNotFound();
    }
}
