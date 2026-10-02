<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** บังคับเปลี่ยนรหัสผ่าน · ลืมรหัสผ่านทาง LINE · สำรอง/กู้คืนไฟล์ */
class PasswordAndBackupTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /** ไฟล์สำรองที่มีอยู่ก่อนเทสต์ (ไม่ลบทิ้ง) */
    private ?array $before = null;

    protected function tearDown(): void
    {
        foreach (File::glob(storage_path('app/backups/files-*.zip')) as $f) {
            if (! in_array($f, $this->before ?? [], true)) {
                File::delete($f);
            }
        }
        Storage::disk('public')->deleteDirectory('backup-test');
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    public function test_new_accounts_must_set_their_own_password(): void
    {
        $this->actingAs($this->admin())->post('/users', ['name' => 'ครูใหม่', 'username' => 'newteacher', 'role' => 'teacher', 'is_active' => 1, 'password' => 'temp1234'])->assertRedirect();
        $user = User::where('username', 'newteacher')->first();
        $this->assertTrue($user->must_change_password);

        $this->post('/logout');
        $this->post('/login', ['username' => 'newteacher', 'password' => 'temp1234'])->assertRedirect();
        $this->get('/')->assertRedirect(route('password.change'));
        $this->get('/students')->assertRedirect(route('password.change'));
        $this->getJson('/notifications')->assertStatus(423);
        $this->get('/password/change')->assertOk()->assertSee('ตั้งรหัสผ่านใหม่');

        // รหัสอ่อน / ซ้ำรหัสเดิม / ใช้ชื่อผู้ใช้ → ไม่รับ
        $this->post('/password/change', ['password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh'])->assertSessionHasErrors('password');
        $this->post('/password/change', ['password' => 'temp1234', 'password_confirmation' => 'temp1234'])->assertSessionHasErrors('password');
        $this->post('/password/change', ['password' => 'newteacher1', 'password_confirmation' => 'newteacher1'])->assertSessionHasErrors('password');

        $this->post('/password/change', ['password' => 'Strong2026', 'password_confirmation' => 'Strong2026'])->assertRedirect(route('home'));
        $this->assertFalse($user->fresh()->must_change_password);
        $this->get('/')->assertOk();
    }

    public function test_reset_and_auto_created_parent_accounts_are_flagged(): void
    {
        $teacher = User::where('username', 'teacher')->first();
        $this->actingAs($this->admin())->post("/users/{$teacher->id}/reset-password");
        $this->assertTrue($teacher->fresh()->must_change_password);

        // ผู้ดูแลแก้รหัสของตัวเอง → ไม่ต้องบังคับเปลี่ยน
        $admin = $this->admin();
        $this->actingAs($admin)->put("/users/{$admin->id}", ['name' => $admin->name, 'username' => $admin->username, 'role' => 'admin', 'is_active' => 1, 'password' => 'Admin2026x']);
        $this->assertFalse($admin->fresh()->must_change_password);

        $this->actingAs($admin)->post('/students', ['student_code' => 'S9001', 'first_name' => 'ใหม่', 'last_name' => 'ทดสอบ', 'status' => 'active',
            'classroom_id' => Classroom::first()->id, 'guardian_name' => 'ผู้ปกครองใหม่', 'guardian_phone' => '0891112233']);
        $parent = User::where('phone', '0891112233')->first();
        $this->assertTrue($parent->must_change_password);
        $this->post('/logout');
        $this->post('/login', ['username' => '0891112233', 'password' => '112233']);
        $this->get('/parent')->assertRedirect(route('password.change'));
    }

    public function test_seeded_and_existing_accounts_can_be_forced_by_command(): void
    {
        $this->assertFalse(User::where('role', 'parent')->first()->must_change_password);
        $this->artisan('users:require-password-change', ['--role' => ['parent']])->assertSuccessful();
        $this->assertSame(0, User::where('role', 'parent')->where('must_change_password', false)->count());
        $this->assertFalse($this->admin()->fresh()->must_change_password);
    }

    public function test_forgot_password_via_line_code(): void
    {
        $parent = User::where('username', '0812345678')->first();
        $noLine = User::where('role', 'parent')->whereKeyNot($parent->id)->first();
        $parent->forceFill(['line_user_id' => 'Uabc123'])->save();

        $this->get('/login')->assertSee(route('password.forgot'));
        // ไม่บอกว่าบัญชีมีอยู่หรือไม่: ข้อความเหมือนกันทั้งสองกรณี
        $this->post('/forgot-password', ['username' => $noLine->username])->assertRedirect()->assertSessionHas('success');
        $this->assertFalse(Cache::has('password-reset:'.$noLine->id));
        $this->post('/forgot-password', ['username' => 'nobody-here'])->assertSessionHas('success');
        $this->post('/forgot-password', ['username' => '0812345678'])->assertRedirect(route('password.reset', ['username' => '0812345678']));
        $this->assertTrue(Cache::has('password-reset:'.$parent->id));

        // ใช้รหัสที่รู้ค่าเพื่อทดสอบ (ของจริงสุ่มและส่งทาง LINE)
        Cache::put('password-reset:'.$parent->id, ['hash' => Hash::make('123456'), 'attempts' => 0], now()->addMinutes(10));
        $form = ['username' => '0812345678', 'password' => 'Family2026', 'password_confirmation' => 'Family2026'];
        $this->post('/reset-password', $form + ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/reset-password', $form + ['code' => '123456'])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('Family2026', $parent->fresh()->password));
        $this->assertFalse(Cache::has('password-reset:'.$parent->id));
        // ใช้ซ้ำไม่ได้
        $this->post('/reset-password', $form + ['code' => '123456'])->assertSessionHasErrors('code');
    }

    public function test_reset_code_is_locked_after_too_many_wrong_attempts(): void
    {
        $user = User::where('username', 'teacher')->first();
        Cache::put('password-reset:'.$user->id, ['hash' => Hash::make('654321'), 'attempts' => 0], now()->addMinutes(10));
        $form = ['username' => 'teacher', 'password' => 'Teach2026x', 'password_confirmation' => 'Teach2026x'];
        for ($i = 0; $i < 5; $i++) {
            $this->post('/reset-password', $form + ['code' => '111111'])->assertSessionHasErrors('code');
        }
        $this->post('/reset-password', $form + ['code' => '654321'])->assertSessionHasErrors('code');
        $this->assertTrue(Hash::check('teacher1234', $user->fresh()->password));
    }

    public function test_file_backup_and_restore(): void
    {
        $this->before = File::glob(storage_path('app/backups/files-*.zip'));
        Storage::disk('public')->put('backup-test/slip.txt', 'สลิปตัวอย่าง');

        $this->artisan('backup:files')->assertSuccessful();
        $zip = collect(File::glob(storage_path('app/backups/files-*.zip')))->diff($this->before)->first();
        $this->assertNotNull($zip);

        Storage::disk('public')->delete('backup-test/slip.txt');
        $this->artisan('backup:restore', ['file' => basename($zip), '--force' => true])->assertSuccessful();
        $this->assertSame('สลิปตัวอย่าง', Storage::disk('public')->get('backup-test/slip.txt'));
    }

    public function test_backup_page_lists_downloads_and_is_admin_only(): void
    {
        $this->before = File::glob(storage_path('app/backups/files-*.zip'));
        $this->actingAs($this->admin())->get('/backups')->assertOk()->assertSee('สำรองตอนนี้')->assertSee('backup:restore');
        $this->actingAs($this->admin())->post('/backups')->assertRedirect();
        $zip = collect(File::glob(storage_path('app/backups/files-*.zip')))->diff($this->before)->first();
        $this->assertNotNull($zip, 'สำรองตอนนี้ต้องได้ไฟล์ zip');

        $this->actingAs($this->admin())->get('/backups')->assertSee(basename($zip));
        $this->actingAs($this->admin())->get('/backups/'.basename($zip))->assertOk()->assertDownload(basename($zip));
        $this->actingAs($this->admin())->get('/backups/..env')->assertNotFound();
        $this->actingAs(User::where('username', 'teacher')->first())->get('/backups')->assertForbidden();
    }

    public function test_profile_password_change_uses_strong_rules(): void
    {
        $teacher = User::where('username', 'teacher')->first();
        $this->actingAs($teacher)->put('/profile', ['name' => $teacher->name, 'current_password' => 'teacher1234', 'password' => '123456', 'password_confirmation' => '123456'])
            ->assertSessionHasErrors('password');
        $this->actingAs($teacher)->put('/profile', ['name' => $teacher->name, 'current_password' => 'teacher1234', 'password' => 'Better2026', 'password_confirmation' => 'Better2026'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Better2026', $teacher->fresh()->password));
    }
}
