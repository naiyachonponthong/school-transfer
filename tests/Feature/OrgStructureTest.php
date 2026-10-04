<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\GateDevice;
use App\Models\StaffAttendance;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** โครงสร้างองค์กร ทะเบียนติดต่อ และรหัสบุคลากร */
class OrgStructureTest extends TestCase
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

    public function test_preset_builds_the_standard_structure_only_once(): void
    {
        Department::query()->delete();
        $this->actingAs($this->admin())->get('/org')->assertOk()->assertSee('สร้างโครงสร้างมาตรฐาน');

        $this->actingAs($this->admin())->post('/org-preset')->assertRedirect()->assertSessionHas('success');
        $root = Department::whereNull('parent_id')->sole();
        $this->assertSame('ผู้อำนวยการโรงเรียน', $root->name);
        $this->assertSame(4, $root->children()->count());
        $this->assertSame(9, Department::where('kind', 'group')->count());

        $count = Department::count();
        $this->actingAs($this->admin())->post('/org-preset')->assertSessionHas('warning');
        $this->assertSame($count, Department::count());
    }

    public function test_hr_manages_departments_heads_and_members(): void
    {
        $division = Department::where('name', 'ฝ่ายบริหารทั่วไป')->first();
        $t = $this->teacher();
        $other = User::where('username', 't3')->first();

        $this->actingAs($this->admin())->post('/org', ['name' => 'งานอาคารสถานที่', 'code' => 'BLD', 'kind' => 'unit', 'parent_id' => $division->id, 'head_id' => $t->id])->assertRedirect();
        $unit = Department::where('name', 'งานอาคารสถานที่')->first();
        $this->assertSame($division->id, $unit->parent_id);

        $this->actingAs($this->admin())->put("/org/{$unit->id}", ['name' => 'งานอาคารสถานที่', 'kind' => 'unit', 'parent_id' => $division->id, 'head_id' => $t->id,
            'members_sent' => 1, 'members' => [$other->id]])->assertRedirect();
        $this->assertTrue($unit->members()->where('users.id', $other->id)->exists());
        // คนที่มีหน่วยหลักอยู่แล้ว หน่วยหลักไม่เปลี่ยน
        $this->assertSame(1, $other->departments()->wherePivot('is_primary', true)->count());
        $this->assertNotSame($unit->id, $other->departments()->wherePivot('is_primary', true)->first()->id);

        $this->actingAs($this->admin())->get('/org')->assertOk()->assertSee('งานอาคารสถานที่')->assertSee($t->name);
        $this->actingAs($this->admin())->get('/org?view=table')->assertOk()->assertSee('งานอาคารสถานที่')->assertSee($other->name);

        // ย้ายไปอยู่ใต้หน่วยย่อยของตัวเองไม่ได้
        $this->actingAs($this->admin())->put("/org/{$division->id}", ['name' => $division->name, 'kind' => 'division', 'parent_id' => $unit->id])->assertSessionHasErrors('parent_id');

        // ลบหน่วยกลาง: หน่วยย่อยเลื่อนขึ้น สมาชิกหลุด
        $child = Department::create(['name' => 'งานย่อย', 'kind' => 'unit', 'parent_id' => $unit->id]);
        $this->actingAs($this->admin())->delete("/org/{$unit->id}")->assertRedirect();
        $this->assertSame($division->id, $child->fresh()->parent_id);
        $this->assertFalse($other->departments()->where('departments.id', $unit->id)->exists());
        $this->assertSame(1, $other->departments()->wherePivot('is_primary', true)->count());
    }

    public function test_teachers_can_view_but_not_change_the_structure(): void
    {
        $t = $this->teacher();
        $d = Department::first();
        $this->actingAs($t)->get('/org')->assertOk()->assertDontSee('เพิ่มหน่วยงาน');
        $this->actingAs($t)->post('/org', ['name' => 'x', 'kind' => 'unit'])->assertForbidden();
        $this->actingAs($t)->put("/org/{$d->id}", ['name' => 'x', 'kind' => 'unit'])->assertForbidden();
        $this->actingAs($t)->delete("/org/{$d->id}")->assertForbidden();
        $this->actingAs($t)->post('/org-codes')->assertForbidden();

        $parent = User::where('role', 'parent')->first();
        $this->actingAs($parent)->get('/org')->assertForbidden();
        $this->actingAs($parent)->get('/directory')->assertForbidden();
    }

    public function test_directory_shows_contacts_without_private_details(): void
    {
        $t = $this->teacher();
        $other = User::where('username', 't2')->first();
        StaffProfile::updateOrCreate(['user_id' => $other->id], ['citizen_id' => '1103700000017', 'address' => 'บ้านเลขที่ลับ 99']);

        $page = $this->actingAs($t)->get('/directory')->assertOk();
        $page->assertSee($other->name)->assertSee($other->phone)->assertSee('กลุ่มสาระภาษาไทย');
        $page->assertDontSee('1103700000017')->assertDontSee('บ้านเลขที่ลับ 99');

        // เจ้าตัวซ่อนเบอร์โทรได้ และค้นด้วยเบอร์ที่ซ่อนไม่เจอ
        $this->actingAs($other)->put("/staff/{$other->id}/org", ['hide_phone' => 1])->assertRedirect();
        $this->actingAs($t)->get('/directory')->assertOk()->assertSee($other->name)->assertDontSee($other->phone);
        $this->actingAs($t)->get('/directory?q='.$other->phone)->assertOk()->assertDontSee($other->name);

        // ค้นชื่อและกรองตามหน่วยงาน (รวมหน่วยย่อย)
        $this->actingAs($t)->get('/directory?q='.urlencode('กนกพร'))->assertSee($other->name)->assertDontSee('นายวีระ');
        $academic = Department::where('name', 'ฝ่ายบริหารวิชาการ')->first();
        $general = Department::where('name', 'ฝ่ายบริหารงบประมาณ')->first();
        $this->actingAs($t)->get('/directory?department='.$academic->id)->assertSee($other->name);
        $this->actingAs($t)->get('/directory?department='.$general->id)->assertDontSee($other->name)->assertSee('ไม่พบบุคลากร');
    }

    public function test_staff_code_and_departments_are_set_by_hr_only(): void
    {
        $t = $this->teacher();
        $budget = Department::where('name', 'ฝ่ายบริหารงบประมาณ')->first();
        $math = $t->departments()->first();

        // เจ้าตัวแก้รหัสหรือสังกัดเองไม่ได้ (ซ่อนเบอร์ได้อย่างเดียว)
        $this->actingAs($t)->put("/staff/{$t->id}/org", ['staff_code' => 'HACK', 'departments' => [$budget->id]])->assertRedirect();
        $this->assertSame('90001', $t->fresh()->staff_code);
        $this->assertFalse($t->departments()->where('departments.id', $budget->id)->exists());

        $this->actingAs($this->admin())->put("/staff/{$t->id}/org", ['staff_code' => 'T-001', 'departments' => [$math->id, $budget->id], 'primary_department' => $budget->id])->assertRedirect();
        $this->assertSame('T-001', $t->fresh()->staff_code);
        $this->assertSame($budget->id, $t->departments()->wherePivot('is_primary', true)->sole()->id);
        $this->actingAs($this->admin())->get('/staff')->assertOk()->assertSee('T-001')->assertSee('ฝ่ายบริหารงบประมาณ');

        // รหัสซ้ำกับคนอื่นหรือซ้ำกับรหัสนักเรียนไม่ได้
        $this->actingAs($this->admin())->put("/staff/{$t->id}/org", ['staff_code' => '90002'])->assertSessionHasErrors('staff_code');
        $this->actingAs($this->admin())->put("/staff/{$t->id}/org", ['staff_code' => Student::first()->student_code])->assertSessionHasErrors('staff_code');
    }

    public function test_codes_are_generated_for_staff_without_one_and_work_at_the_gate(): void
    {
        $t = $this->teacher();
        $t->forceFill(['staff_code' => null])->save();
        $this->admin()->forceFill(['staff_code' => null])->save();
        $this->actingAs($this->admin())->post('/org-codes')->assertRedirect()->assertSessionHas('success');
        $code = $t->fresh()->staff_code;
        $this->assertMatchesRegularExpression('/^9\d{4}$/', $code);
        $this->assertSame(User::whereIn('role', ['admin', 'teacher'])->count(), User::whereNotNull('staff_code')->distinct()->count('staff_code'));
        $this->assertFalse(Student::where('student_code', $code)->exists());
        auth()->logout();

        // เครื่องสแกนที่ประตูรับรหัสบุคลากร
        $device = GateDevice::create(['name' => 'ประตู', 'mode' => 'auto', 'token' => Str::random(48), 'is_active' => true]);
        StaffAttendance::where('user_id', $t->id)->delete();
        $this->travelTo(today()->setTime(7, 30));
        $this->postJson("/gate/hook/{$device->token}", ['code' => $code])->assertOk()->assertJsonPath('result', 'present');
        $this->assertSame('07:30:00', StaffAttendance::where('user_id', $t->id)->where('date', today()->toDateString())->value('check_in'));
    }
}
