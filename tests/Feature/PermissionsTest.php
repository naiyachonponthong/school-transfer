<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Support\Menu;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    private function teacher(): User
    {
        return User::where('username', 'teacher')->first();
    }

    private function give(User $user, string ...$roleKeys): User
    {
        $user->roles()->sync(Role::whereIn('key', $roleKeys)->pluck('id'));

        return $user->fresh();
    }

    private function menuKeys(User $user): array
    {
        return collect(Menu::groups($user))->flatten(1)->pluck('key')->all();
    }

    public function test_default_roles_exist_and_admin_has_every_permission(): void
    {
        $this->assertSame(count(Permissions::DEFAULT_ROLES), Role::where('is_system', true)->count());
        $this->assertSame(Permissions::keys(), $this->admin()->permissions());
        $this->assertSame([], User::where('role', 'parent')->first()->permissions());
    }

    public function test_teacher_without_position_keeps_daily_work_but_not_finance_or_admin_pages(): void
    {
        $t = $this->teacher();
        $student = Student::active()->first();

        foreach (['/attendance', '/students', '/students/'.$student->id, '/students/create', '/health', '/library-loans', '/gate', '/reports', '/courses', '/repairs', '/bookings'] as $url) {
            $this->assertSame(200, $this->actingAs($t)->get($url)->status(), $url);
        }
        foreach (['/invoices', '/invoices/create', '/slips', '/users', '/roles', '/settings', '/classrooms', '/admissions', '/audit', '/staff-attendance', '/backups', '/certificates'] as $url) {
            $this->assertSame(403, $this->actingAs($t)->get($url)->status(), $url);
        }
        $this->get('/invoices/'.Invoice::first()->id)->assertForbidden();

        $keys = $this->menuKeys($t);
        $this->assertContains('attendance', $keys);
        $this->assertNotContains('invoices', $keys);
        $this->assertNotContains('settings', $keys);
        $this->get('/')->assertOk();
        $this->get('/menu')->assertOk();
    }

    public function test_finance_position_can_do_finance_work_without_being_admin(): void
    {
        $t = $this->give($this->teacher(), 'finance');
        $inv = Invoice::where('status', 'unpaid')->first();

        $this->actingAs($t)->get('/invoices')->assertOk();
        $this->get('/invoices/create')->assertOk();
        $this->get('/slips')->assertOk();
        $this->get('/invoices/'.$inv->id)->assertOk()->assertSee('รับชำระเงิน');
        $this->post(route('invoices.pay', $inv), ['amount' => 100, 'method' => 'cash'])->assertRedirect();
        $this->assertEquals(100, $inv->fresh()->paid);

        // ได้เฉพาะงานการเงิน ไม่ได้งานอื่นของผู้ดูแล และไม่ได้สิทธิ์ครูที่ไม่ได้ติ๊กให้
        $this->get('/users')->assertForbidden();
        $this->get('/settings')->assertForbidden();
        $this->get('/health')->assertForbidden();
        $this->assertContains('slips', $this->menuKeys($t));
    }

    public function test_positions_combine_and_matrix_changes_apply_immediately(): void
    {
        $t = $this->give($this->teacher(), 'teacher', 'academic');
        $this->actingAs($t)->get('/classrooms')->assertOk();
        $this->get('/health')->assertOk();
        $this->get('/invoices')->assertForbidden();

        $matrix = Role::all()->mapWithKeys(fn ($r) => [$r->id => $r->permissions])->all();
        $teacherRole = Role::where('key', 'teacher')->first();
        $matrix[$teacherRole->id][] = 'finance.view';
        $this->actingAs($this->admin())->get('/roles')->assertOk()->assertSee('ตำแหน่งงานและสิทธิ์');
        $this->put(route('roles.update'), ['permissions' => $matrix])->assertRedirect();
        $this->put(route('roles.update'), ['permissions' => [$teacherRole->id => ['not.a.permission']]])->assertSessionHasErrors();
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.role']);

        $this->actingAs($t->fresh())->get('/invoices')->assertOk();
    }

    public function test_facilities_permission_and_legacy_setting_both_grant_facility_work(): void
    {
        $t = $this->teacher();
        $this->assertFalse($t->canManageFacilities());
        $this->assertTrue($this->give($t, 'clerk')->canManageFacilities());

        // วิธีเดิม (ตั้งชื่อครูในหน้าตั้งค่า) ยังใช้ได้
        $other = User::where('role', 'teacher')->where('id', '!=', $t->id)->first();
        \App\Support\Settings::set(['facility_manager_ids' => (string) $other->id]);
        $this->assertTrue($other->fresh()->canManageFacilities());
    }

    public function test_admin_assigns_positions_on_the_user_form_and_custom_roles_can_be_managed(): void
    {
        $t = $this->teacher();
        $finance = Role::where('key', 'finance')->first();

        $this->actingAs($this->admin())->get(route('users.edit', $t))->assertOk()->assertSee('ตำแหน่งงาน');
        $this->put(route('users.update', $t), ['name' => $t->name, 'username' => $t->username, 'role' => 'teacher', 'is_active' => 1, 'password' => '', 'role_ids' => [$finance->id]])->assertRedirect();
        $this->assertSame(['finance'], $t->roles()->pluck('key')->all());

        $this->post(route('roles.store'), ['name' => 'หัวหน้ากลุ่มสาระ'])->assertRedirect();
        $custom = Role::where('name', 'หัวหน้ากลุ่มสาระ')->first();
        $this->assertFalse($custom->is_system);
        $this->delete(route('roles.destroy', $finance))->assertStatus(422);
        $this->delete(route('roles.destroy', $custom))->assertRedirect();
        $this->assertDatabaseMissing('roles', ['id' => $custom->id]);
    }
}
