<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Admission;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExecutiveAndPrivacyTest extends TestCase
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

    /* ---------------- แดชบอร์ดผู้บริหาร ---------------- */

    public function test_executive_dashboard_shows_all_sections_and_is_restricted(): void
    {
        $this->actingAs($this->admin())->get(route('executive.index'))->assertOk()
            ->assertSee('อัตรามาเรียนรายสัปดาห์')->assertSee('แยกระดับชั้น')->assertSee('การกระจายผลการเรียนภาคนี้')
            ->assertSee('ค้างชำระทั้งหมด')->assertSee('บุคลากรวันนี้')->assertSee('นักเรียนที่มีสัญญาณเตือน');

        $t = $this->teacher();
        $this->actingAs($t)->get(route('executive.index'))->assertForbidden();
        $t->roles()->sync(Role::where('key', 'executive')->pluck('id'));
        $this->actingAs($t->fresh())->get(route('executive.index'))->assertOk();
        $this->assertContains('executive.view', Role::where('key', 'executive')->first()->permissions);
    }

    public function test_executive_dashboard_renders_with_no_data(): void
    {
        DB::table('attendances')->delete();
        DB::table('scores')->delete();
        DB::table('payments')->delete();
        DB::table('invoice_items')->delete();
        DB::table('payment_slips')->delete();
        DB::table('invoices')->delete();

        $this->actingAs($this->admin())->get(route('executive.index'))->assertOk()->assertSee('ยังไม่มีผลการเรียน');
    }

    /* ---------------- PDPA ---------------- */

    public function test_privacy_notice_must_be_acknowledged_once_per_version(): void
    {
        $parent = User::where('phone', '0812345678')->first();

        // ยังไม่ตั้งประกาศ = ไม่บังคับ
        $this->actingAs($parent)->get(route('parent.home'))->assertOk();

        $base = ['school_name' => 'x', 'late_time' => '08:00', 'staff_late_time' => '08:00', 'periods_per_day' => 7, 'theme_color' => Settings::get('theme_color')];
        $this->actingAs($this->admin())->post(route('settings.update'), $base + ['privacy_notice' => 'โรงเรียนเก็บข้อมูลเพื่อการจัดการศึกษา'])->assertSessionHasNoErrors();
        $this->flushSession();

        $this->actingAs($parent)->get(route('parent.home'))->assertRedirect(route('privacy.show'));
        $this->get(route('privacy.show'))->assertOk()->assertSee('โรงเรียนเก็บข้อมูลเพื่อการจัดการศึกษา');
        $this->post(route('privacy.accept'), [])->assertSessionHasErrors('agree');
        $this->post(route('privacy.accept'), ['agree' => 1])->assertRedirect();
        $this->assertDatabaseHas('privacy_consents', ['user_id' => $parent->id, 'version' => 1]);
        $this->get(route('parent.home'))->assertOk();

        // ขึ้นฉบับใหม่ ทุกคนต้องรับทราบอีกครั้ง
        $this->actingAs($this->admin())->post(route('privacy.accept'), ['agree' => 1]);
        $this->post(route('settings.update'), $base + ['privacy_notice' => 'ฉบับแก้ไข', 'privacy_bump' => 1])->assertSessionHasNoErrors();
        $this->assertSame('2', (string) Settings::get('privacy_version'));
        $this->flushSession();
        $this->actingAs($parent)->get(route('parent.home'))->assertRedirect(route('privacy.show'));
    }

    public function test_student_data_export_contains_personal_data_and_is_logged(): void
    {
        $student = Student::whereHas('invoices')->whereHas('scores')->first();

        $res = $this->actingAs($this->admin())->get(route('students.data-export', $student))->assertOk();
        $this->assertStringContainsString('attachment', $res->headers->get('content-disposition'));
        $json = $res->json();
        $this->assertSame($student->student_code, $json['student']['student_code']);
        $this->assertNotEmpty($json['scores']);
        $this->assertNotEmpty($json['invoices']);
        $this->assertNotEmpty($json['enrollments']);
        $this->assertTrue(AuditLog::where('action', 'student.data_export')->where('subject_id', $student->id)->exists());

        $this->actingAs($this->teacher())->get(route('students.data-export', $student))->assertForbidden();
    }

    public function test_purge_removes_only_old_unenrolled_applications_and_old_logs(): void
    {
        $make = fn (array $attrs) => tap(new Admission, fn ($a) => $a->forceFill(['app_no' => 'T'.uniqid(), 'year' => 2567, 'level' => 'ม.1', 'first_name' => 'ก', 'last_name' => 'ข',
            'parent_name' => 'ค', 'parent_phone' => '0800000000', 'status' => 'rejected'] + $attrs)->save());
        $old = $make(['created_at' => now()->subYears(3)]);
        $recent = $make(['created_at' => now()->subMonths(6)]);
        $enrolled = $make(['created_at' => now()->subYears(3), 'status' => 'enrolled', 'student_id' => Student::value('id')]);
        AuditLog::insert([
            ['action' => 'user.update', 'description' => 'old', 'created_at' => now()->subYears(4)],
            ['action' => 'user.update', 'description' => 'new', 'created_at' => now()->subDay()],
        ]);

        $this->artisan('privacy:purge --dry-run')->assertSuccessful();
        $this->assertDatabaseHas('applications', ['id' => $old->id]);

        $this->artisan('privacy:purge')->assertSuccessful();
        $this->assertDatabaseMissing('applications', ['id' => $old->id]);
        $this->assertDatabaseHas('applications', ['id' => $recent->id]);
        $this->assertDatabaseHas('applications', ['id' => $enrolled->id]);
        $this->assertDatabaseMissing('audit_logs', ['description' => 'old']);
        $this->assertDatabaseHas('audit_logs', ['description' => 'new']);
    }
}
