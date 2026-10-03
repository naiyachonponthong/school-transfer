<?php

namespace Tests\Feature;

use App\Models\MessageLog;
use App\Models\OfficeDocument;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\StaffTraining;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffProfileAndOfficeTest extends TestCase
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

    private function otherTeacher(): User
    {
        return User::where('role', 'teacher')->where('username', '!=', 'teacher')->first();
    }

    private function clerk(): User
    {
        $u = $this->otherTeacher();
        $u->roles()->sync(Role::where('key', 'clerk')->pluck('id'));
        $u->flushPermissions();

        return $u->fresh();
    }

    /* ---------------- ทะเบียนบุคลากร ---------------- */

    public function test_staff_edit_own_profile_and_trainings_but_not_others(): void
    {
        Storage::fake('local');
        $t = $this->teacher();
        $other = $this->otherTeacher();

        $this->actingAs($t)->get(route('staff.show', $t))->assertOk()->assertSee('ประวัติการอบรม');
        $this->put(route('staff.update', $t), ['citizen_id' => '123', 'rank' => 'ชำนาญการ'])->assertSessionHasErrors('citizen_id');
        $this->put(route('staff.update', $t), ['citizen_id' => '1234567890123', 'rank' => 'ชำนาญการ', 'hired_on' => today()->subYears(5)->toDateString(),
            'license_no' => 'L-001', 'license_expires_on' => today()->addDays(40)->toDateString()])->assertSessionHasNoErrors();
        $profile = StaffProfile::where('user_id', $t->id)->first();
        $this->assertSame(5, $profile->yearsOfService());
        $this->assertTrue($profile->licenseExpiring());

        $this->post(route('staff.trainings.store', $t), ['title' => 'อบรม Active Learning', 'organizer' => 'สพป.', 'date' => today()->toDateString(), 'hours' => 12,
            'file' => UploadedFile::fake()->create('cert.pdf', 50, 'application/pdf')])->assertSessionHasNoErrors();
        $training = StaffTraining::first();
        Storage::disk('local')->assertExists($training->file);
        $this->get(route('files.show', ['training', $training->id]))->assertOk();

        // คนอื่นเปิด/แก้ไม่ได้ · ฝ่ายบุคคล (admin) เห็นทะเบียนรวมและแก้ได้ · เลขบัตรไม่ลงประวัติการใช้งาน
        $this->actingAs($other)->get(route('staff.show', $t))->assertForbidden();
        $this->put(route('staff.update', $t), ['rank' => 'x'])->assertForbidden();
        $this->get(route('files.show', ['training', $training->id]))->assertForbidden();
        $this->get(route('staff.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('staff.index'))->assertOk()->assertSee('L-001')->assertSee('12 ชม.');
        $this->get(route('staff.show', $t))->assertOk();
        $this->assertStringNotContainsString('1234567890123', \App\Models\AuditLog::all()->toJson());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.profile']);
        $this->get(route('staff.show', User::where('role', 'parent')->first()))->assertNotFound();
    }

    public function test_license_reminder_fires_once_per_stage(): void
    {
        Settings::set(['line_channel_token' => 'tok']);
        Http::fake(['api.line.me/*' => Http::response('{}', 200)]);
        $t = $this->teacher();
        $t->forceFill(['line_user_id' => 'Uteacher'])->save();
        StaffProfile::create(['user_id' => $t->id, 'license_expires_on' => today()->addDays(60)]);
        StaffProfile::create(['user_id' => $this->otherTeacher()->id, 'license_expires_on' => today()->addDays(200)]);
        $count = fn () => MessageLog::where('user_id', $t->id)->where('text', 'like', '%ใบอนุญาต%')->count();

        $this->artisan('staff:license-remind')->assertSuccessful();
        $this->assertSame(1, $count());
        $this->artisan('staff:license-remind');
        $this->assertSame(1, $count()); // ช่วง 90 วันเตือนไปแล้ว

        $this->travel(35)->days(); // เหลือ 25 วัน เข้าช่วง 30 วัน
        $this->artisan('staff:license-remind');
        $this->assertSame(2, $count());
    }

    /* ---------------- สารบรรณ ---------------- */

    public function test_office_documents_get_running_numbers_and_recipients_acknowledge(): void
    {
        Storage::fake('local');
        $clerk = $this->clerk();
        $reader = $this->teacher();
        $year = now()->year + 543;

        $this->actingAs($reader)->post(route('office.store'), ['type' => 'in', 'doc_date' => today()->toDateString(), 'subject' => 'x', 'urgency' => 'normal'])->assertForbidden();

        $payload = ['type' => 'in', 'doc_date' => today()->toDateString(), 'subject' => 'ขอเชิญประชุม', 'ref_no' => 'ศธ 04123/ว99', 'party' => 'สพป.', 'urgency' => 'urgent'];
        $this->actingAs($clerk)->post(route('office.store'), $payload + ['recipient_ids' => [$reader->id], 'file' => UploadedFile::fake()->create('doc.pdf', 80, 'application/pdf')])->assertRedirect();
        $this->post(route('office.store'), ['subject' => 'ฉบับที่สอง'] + $payload);
        $this->post(route('office.store'), ['type' => 'order', 'subject' => 'แต่งตั้งคณะกรรมการ'] + $payload);

        $first = OfficeDocument::where('subject', 'ขอเชิญประชุม')->first();
        $this->assertSame("1/{$year}", $first->number());
        $this->assertSame("2/{$year}", OfficeDocument::where('subject', 'ฉบับที่สอง')->first()->number());
        $this->assertSame("1/{$year}", OfficeDocument::where('type', 'order')->first()->number()); // เลขแยกตามประเภท

        // ผู้รับเห็นเฉพาะฉบับที่เวียนถึงตัวเอง เปิดไฟล์ได้ แล้วกดรับทราบ
        $this->actingAs($reader)->get(route('office.index'))->assertOk()->assertSee('ขอเชิญประชุม')->assertDontSee('ฉบับที่สอง');
        $this->get(route('office.show', $first))->assertOk()->assertSee('กรุณากดรับทราบ');
        $this->get(route('files.show', ['office-doc', $first->id]))->assertOk();
        $this->get(route('office.show', OfficeDocument::where('subject', 'ฉบับที่สอง')->first()))->assertForbidden();
        $this->post(route('office.acknowledge', $first))->assertRedirect();
        $this->assertNotNull($first->recipients()->first()->pivot->acknowledged_at);

        // ธุรการเห็นทุกฉบับ ดูสถานะรับทราบ และเวียนเพิ่มได้
        $this->actingAs($clerk)->get(route('office.index'))->assertOk()->assertSee('ฉบับที่สอง');
        $this->get(route('office.show', $first))->assertOk()->assertSee('การรับทราบ 1/1');
        $this->post(route('office.recipients', $first), ['recipient_ids' => [$this->admin()->id, $reader->id]])->assertRedirect();
        $this->assertSame(2, $first->recipients()->count());
    }
}
