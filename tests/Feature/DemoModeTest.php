<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use App\Support\Demo;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** โหมดทดลองใช้: เข้าระบบตามบทบาทโดยไม่ใช้รหัสผ่าน ข้อจำกัดระหว่างทดลอง และการคืนข้อมูลเป็นต้นแบบ */
class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function tearDown(): void
    {
        File::deleteDirectory(Demo::dir());
        parent::tearDown();
    }

    private function enable(): array
    {
        $users = [
            'exec' => User::where('role', 'teacher')->where('username', '!=', 'teacher')->first(),
            'teacher' => User::where('username', 'teacher')->first(),
            'parent' => User::where('phone', '0812345678')->first(),
            'student' => User::where('username', '69001')->first(),
        ];
        Settings::set(['demo_mode' => '1'] + collect($users)->mapWithKeys(fn ($u, $r) => ['demo_user_'.$r => (string) $u->id])->all());

        return $users;
    }

    public function test_demo_buttons_only_work_when_enabled_and_never_for_admins(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('ทดลองใช้งาน');
        $this->post(route('demo.login', 'teacher'))->assertNotFound();

        $users = $this->enable();
        $this->get(route('login'))->assertOk()->assertSee('ทดลองใช้งาน')->assertSee('ผู้บริหาร');
        $this->post(route('demo.login', 'nobody'))->assertNotFound();
        $this->post(route('demo.login', 'teacher'))->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($users['teacher']);
        $this->get(route('home'))->assertOk()->assertSee('โหมดทดลองใช้');

        // ผู้ดูแลระบบตั้งบัญชีทดลองเป็นผู้ดูแลระบบไม่ได้
        $admin = User::where('username', 'admin')->first();
        $this->post(route('logout'));
        $this->actingAs($admin)->post(route('demo.settings'), ['demo_mode' => 1, 'demo_user_exec' => $admin->id])->assertSessionHasErrors('demo_user_exec');
        $this->post(route('demo.settings'), ['demo_mode' => 1, 'demo_user_teacher' => $users['teacher']->id])->assertSessionHasNoErrors();
        $this->assertSame('', Settings::get('demo_user_parent'));
        $this->get(route('settings'))->assertOk()->assertSee('โหมดทดลองใช้');
    }

    public function test_demo_sessions_cannot_change_accounts_settings_or_delete(): void
    {
        $users = $this->enable();
        $old = $users['teacher']->password;

        $this->post(route('demo.login', 'teacher'));
        $this->put(route('profile.update'), ['name' => 'แฮก', 'current_password' => 'teacher1234', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertRedirect()->assertSessionHas('warning');
        $this->assertSame($old, $users['teacher']->fresh()->password);
        $this->assertNotSame('แฮก', $users['teacher']->fresh()->name);
        $this->get(route('settings'))->assertRedirect(route('home'));
        $this->get(route('backups.index'))->assertRedirect(route('home'));
        $this->postJson(route('push.subscribe'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/x'])->assertForbidden();

        $student = Student::active()->first();
        $this->delete("/students/{$student->id}")->assertRedirect();
        $this->assertNotNull(Student::find($student->id));

        // ครูทดลองยังลองใช้งานปกติได้ (เปิดหน้าเช็คชื่อ)
        $this->get(route('attendance.index'))->assertOk();

        // ผู้บริหารทดลอง: ดูได้อย่างเดียว
        $this->post(route('logout'));
        $this->post(route('demo.login', 'exec'));
        $this->get(route('home'))->assertOk()->assertSee('ดูได้อย่างเดียว');
        $this->post(route('announcements.store'), ['title' => 'x', 'body' => 'y', 'audience' => 'staff'])->assertRedirect()->assertSessionHas('warning');
        $this->assertDatabaseMissing('announcements', ['title' => 'x']);

        // เข้าระบบด้วยรหัสผ่านตามปกติ ไม่ถูกจำกัด
        $this->post(route('logout'));
        $this->post(route('login'), ['username' => 'teacher', 'password' => 'teacher1234']);
        $this->put(route('profile.update'), ['name' => 'นายสมชาย ชื่อใหม่'])->assertSessionHasNoErrors();
        $this->assertSame('นายสมชาย ชื่อใหม่', $users['teacher']->fresh()->name);
    }

    public function test_snapshot_and_reset_restore_all_data(): void
    {
        $this->enable();
        $admin = User::where('username', 'admin')->first();
        $student = Student::active()->first();
        $name = $student->first_name;
        $count = Student::count();

        $this->actingAs($admin)->post(route('demo.reset'))->assertSessionHas('warning'); // ยังไม่มีต้นแบบ
        $this->post(route('demo.snapshot'))->assertSessionHas('success');
        $this->assertNotNull(Demo::snapshotAt());

        // ผู้ทดลองแก้ข้อมูลไปมา
        $student->update(['first_name' => 'ถูกแก้']);
        Student::create(['student_code' => 'X999', 'prefix' => 'เด็กชาย', 'first_name' => 'เพิ่มใหม่', 'last_name' => 'ทดลอง', 'gender' => 'M', 'classroom_id' => $student->classroom_id, 'number' => 99, 'status' => 'active']);
        Settings::set(['school_name' => 'ชื่อถูกเปลี่ยน']);

        $this->artisan('demo:reset')->assertSuccessful();
        $this->assertSame($name, $student->fresh()->first_name);
        $this->assertSame($count, Student::count());
        $this->assertNotSame('ชื่อถูกเปลี่ยน', Settings::get('school_name'));
        $this->assertSame('1', Settings::get('demo_mode'));

        // รันตามเวลา: ทำเฉพาะเมื่อเปิดคืนค่าอัตโนมัติ
        $student->update(['first_name' => 'ถูกแก้อีก']);
        $this->artisan('demo:reset --scheduled')->assertSuccessful();
        $this->assertSame('ถูกแก้อีก', $student->fresh()->first_name);
    }
}
