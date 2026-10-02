<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * ติดตั้งระบบสำหรับใช้งานจริง: ล้างฐานข้อมูล ไม่ใส่ข้อมูลตัวอย่าง สร้างเฉพาะบัญชีผู้ดูแลคนแรกและชื่อโรงเรียน
 * ใช้แทน `migrate:fresh --seed` ตอนขึ้นระบบจริง (seed มีไว้สำหรับสาธิต/ทดลองใช้เท่านั้น)
 *
 *   php artisan school:install
 *   php artisan school:install --name="โรงเรียนตัวอย่าง" --admin-user=admin --admin-name="ผู้ดูแลระบบ" --admin-password=xxxxxxxx --force
 */
class InstallSchool extends Command
{
    protected $signature = 'school:install
        {--name= : ชื่อโรงเรียน}
        {--admin-name= : ชื่อผู้ดูแลระบบ}
        {--admin-user= : ชื่อผู้ใช้สำหรับเข้าระบบ}
        {--admin-password= : รหัสผ่าน (เว้นว่าง = สุ่มให้)}
        {--force : รันโดยไม่ถามยืนยัน (ใช้ตอน deploy อัตโนมัติ)}';

    protected $description = 'ติดตั้งระบบสำหรับใช้งานจริง (ล้างข้อมูลตัวอย่าง สร้างบัญชีผู้ดูแลคนแรก)';

    public function handle(): int
    {
        $alreadyInstalled = Schema::hasTable('users') && User::query()->exists();
        if ($alreadyInstalled && ! $this->option('force')) {
            $this->error('ระบบมีผู้ใช้งานอยู่แล้ว ถ้าต้องการล้างข้อมูลทั้งหมดและเริ่มใหม่ ให้รัน `php artisan school:install --force` (ข้อมูลเดิมจะหายทั้งหมด กู้คืนไม่ได้)');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->warn('คำสั่งนี้จะล้างฐานข้อมูลทั้งหมด (migrate:fresh) แล้วเริ่มต้นใหม่ ไม่มีข้อมูลตัวอย่าง');
            if (! $this->confirm('ยืนยันดำเนินการต่อ?')) {
                return self::SUCCESS;
            }
        }

        $schoolName = $this->option('name') ?: $this->ask('ชื่อโรงเรียน', 'โรงเรียนของฉัน');
        $adminName = $this->option('admin-name') ?: $this->ask('ชื่อผู้ดูแลระบบ', 'ผู้ดูแลระบบ');
        $username = $this->option('admin-user') ?: $this->ask('ชื่อผู้ใช้สำหรับเข้าระบบ (ภาษาอังกฤษ/ตัวเลข)', 'admin');

        $usernameError = Validator::make(['u' => $username], ['u' => ['required', 'alpha_dash', 'max:50']])->errors()->first('u');
        if ($usernameError) {
            $this->error("ชื่อผู้ใช้ไม่ถูกต้อง: {$usernameError}");

            return self::FAILURE;
        }

        $password = $this->option('admin-password');
        if ($password) {
            $err = Validator::make(['p' => $password], ['p' => ['string', Password::min(8)]])->errors()->first('p');
            if ($err) {
                $this->error("รหัสผ่านไม่ปลอดภัยพอ: {$err}");

                return self::FAILURE;
            }
        } else {
            $password = str()->password(12, symbols: false);
        }

        $this->call('migrate:fresh', ['--force' => true]);

        DB::transaction(function () use ($schoolName, $adminName, $username, $password) {
            Settings::set([
                'school_name' => $schoolName,
                'school_short' => $schoolName,
            ]);

            User::create([
                'name' => $adminName,
                'username' => $username,
                'role' => 'admin',
                'position' => 'ผู้ดูแลระบบ',
                'is_active' => true,
                'password' => Hash::make($password),
            ]);
        });

        if (! file_exists(public_path('storage'))) {
            $this->call('storage:link');
        }

        $this->newLine();
        $this->info('ติดตั้งเสร็จแล้ว เข้าสู่ระบบด้วยบัญชีนี้แล้วเปลี่ยนรหัสผ่านทันที:');
        $this->table(['ชื่อผู้ใช้', 'รหัสผ่าน'], [[$username, $password]]);
        $this->comment('ขั้นตอนถัดไป: เข้าสู่ระบบ → ตั้งค่าโรงเรียน (โลโก้ ที่อยู่ สีธีม) → ปีการศึกษา → ห้องเรียน → นำเข้านักเรียน');

        return self::SUCCESS;
    }
}
