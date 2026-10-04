<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\ConsentForm;
use App\Models\ConsentResponse;
use App\Models\GateDevice;
use App\Models\GateEvent;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** เครื่องสแกนใบหน้า/บัตรที่ประตู: หลายเครื่อง ทิศทางของแต่ละเครื่อง และการรับเหตุการณ์จากเครื่อง */
class GateDeviceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    private function device(string $name, string $mode = 'auto'): GateDevice
    {
        $this->actingAs($this->admin())->post('/gate/devices', ['name' => $name, 'mode' => $mode])->assertRedirect();
        auth()->logout();

        return GateDevice::where('name', $name)->first();
    }

    private function student(int $skip = 0): Student
    {
        $s = Student::active()->skip($skip)->first();
        Attendance::where('student_id', $s->id)->where('date', today()->toDateString())->delete();

        return $s;
    }

    public function test_admin_manages_several_devices_each_with_its_own_address(): void
    {
        $a = $this->device('ประตูหน้า ช่อง 1', 'in');
        $b = $this->device('ประตูหน้า ช่อง 2', 'out');
        $this->assertNotSame($a->token, $b->token);

        $this->actingAs($this->admin())->get('/gate/devices')->assertOk()
            ->assertSee('ประตูหน้า ช่อง 1')->assertSee('ประตูหน้า ช่อง 2')->assertSee($a->hookUrl());

        $this->actingAs($this->admin())->put("/gate/devices/{$a->id}", ['name' => 'ประตูหลัง', 'mode' => 'auto', 'is_active' => '0'])->assertRedirect();
        $this->assertFalse($a->fresh()->is_active);
        $this->assertSame('ประตูหลัง', $a->fresh()->name);

        $old = $b->token;
        $this->actingAs($this->admin())->post("/gate/devices/{$b->id}/rotate")->assertRedirect();
        $this->assertNotSame($old, $b->fresh()->token);

        $this->actingAs($this->admin())->delete("/gate/devices/{$b->id}")->assertRedirect();
        $this->assertNull(GateDevice::find($b->id));
    }

    public function test_only_administrators_reach_the_device_page(): void
    {
        $this->get('/gate/devices')->assertRedirect();
        $this->actingAs(User::where('role', 'teacher')->first())->get('/gate/devices')->assertForbidden();
    }

    public function test_two_devices_record_arrival_and_departure_without_login(): void
    {
        $this->travelTo(today()->setTime(7, 40));
        $in = $this->device('ขาเข้า', 'in');
        $out = $this->device('ขาออก', 'out');
        $s = $this->student();

        $this->postJson("/gate/hook/{$in->token}", ['code' => $s->student_code])->assertOk()->assertJsonPath('result', 'present');
        $this->postJson("/gate/hook/{$in->token}", ['code' => $s->student_code])->assertJsonPath('result', 'repeat');
        // เครื่องขาออกบันทึกออกแม้ยังไม่ถึงเวลาออกตามปกติ
        $this->travelTo(today()->setTime(11, 5));
        $this->postJson("/gate/hook/{$out->token}", ['code' => $s->student_code])->assertJsonPath('result', 'out');

        $att = Attendance::where('student_id', $s->id)->where('date', today()->toDateString())->first();
        $this->assertSame('present', $att->status);
        $this->assertSame('07:40:00', $att->checked_at);
        $this->assertSame('11:05:00', $att->checkout_at);
        $this->assertSame('gate', $att->source);
        $this->assertSame(2, $in->events()->count());
        $this->assertSame(1, $out->events()->count());
        $this->assertTrue($out->fresh()->isOnline());
    }

    public function test_auto_device_follows_the_clock_and_marks_late(): void
    {
        $d = $this->device('ประตูเดียว');
        $s = $this->student();

        $this->travelTo(today()->setTime(8, 25));
        $this->postJson("/gate/hook/{$d->token}", ['code' => $s->student_code])->assertJsonPath('result', 'late');
        $this->travelTo(today()->setTime(15, 30));
        $this->postJson("/gate/hook/{$d->token}", ['code' => $s->student_code])->assertJsonPath('result', 'out');
    }

    public function test_hikvision_style_events_are_understood(): void
    {
        $this->travelTo(today()->setTime(7, 30));
        $d = $this->device('Hikvision');
        $s = $this->student();
        $event = ['ipAddress' => '192.168.1.64', 'dateTime' => now()->subMinutes(2)->toIso8601String(), 'eventType' => 'AccessControllerEvent',
            'AccessControllerEvent' => ['majorEventType' => 5, 'subEventType' => 75, 'employeeNoString' => $s->student_code, 'name' => 'x']];

        // เครื่องส่งเป็น multipart ที่มีช่องข้อความเป็น JSON
        $this->post("/gate/hook/{$d->token}", ['event_log' => json_encode($event)])->assertOk()->assertJsonPath('result', 'present');
        $att = Attendance::where('student_id', $s->id)->where('date', today()->toDateString())->first();
        $this->assertSame('07:28:00', $att->checked_at);

        // ส่งเป็น JSON ตรง ๆ ก็ได้ และเหตุการณ์ที่ไม่มีรหัสบุคคล (เช่น สัญญาณตรวจการเชื่อมต่อ) ไม่ถูกบันทึก
        $this->postJson("/gate/hook/{$d->token}", $event)->assertJsonPath('result', 'repeat');
        $this->postJson("/gate/hook/{$d->token}", ['eventType' => 'heartBeat'])->assertOk()->assertJsonPath('result', 'ignored');
        $this->assertSame(2, GateEvent::count());
    }

    public function test_unknown_codes_bad_tokens_and_disabled_devices(): void
    {
        $d = $this->device('ประตู');

        $this->postJson("/gate/hook/{$d->token}", ['code' => 'no-such-code'])->assertOk()->assertJsonPath('result', 'unknown');
        $this->assertSame('no-such-code', GateEvent::where('result', 'unknown')->first()->code);
        $this->postJson('/gate/hook/wrong-token', ['code' => 'x'])->assertNotFound();

        $d->update(['is_active' => false]);
        $s = $this->student();
        $this->postJson("/gate/hook/{$d->token}", ['code' => $s->student_code])->assertForbidden();
        $this->assertNull(Attendance::where('student_id', $s->id)->where('date', today()->toDateString())->first());
    }

    public function test_a_device_clock_that_is_far_off_is_ignored(): void
    {
        $this->travelTo(today()->setTime(7, 50));
        $d = $this->device('นาฬิกาเพี้ยน');
        $s = $this->student();

        $this->postJson("/gate/hook/{$d->token}", ['code' => $s->student_code, 'time' => '2020-01-01T03:00:00+07:00'])->assertJsonPath('result', 'present');
        $this->assertSame('07:50:00', Attendance::where('student_id', $s->id)->where('date', today()->toDateString())->value('checked_at'));
    }

    public function test_simulator_runs_the_same_path_as_a_real_device(): void
    {
        $this->travelTo(today()->setTime(7, 45));
        $d = $this->device('ทดสอบ');
        $s = $this->student();

        $this->actingAs($this->admin())->post("/gate/devices/{$d->id}/simulate", ['code' => $s->student_code])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('present', Attendance::where('student_id', $s->id)->where('date', today()->toDateString())->value('status'));
        $this->actingAs($this->admin())->post("/gate/devices/{$d->id}/simulate", ['code' => 'zzz'])->assertSessionHas('warning');
    }

    public function test_face_photos_export_only_students_whose_guardian_agreed(): void
    {
        Storage::fake('public');
        [$agreedWithPhoto, $agreedNoPhoto, $refused, $silent] = Student::active()->take(4)->get()->all();
        foreach ([$agreedWithPhoto, $refused, $silent] as $s) {
            $path = "students/{$s->id}.png";
            Storage::disk('public')->put($path, UploadedFile::fake()->image('p.png', 1200, 1600)->getContent());
            $s->update(['photo' => $path]);
        }
        $agreedNoPhoto->update(['photo' => null]);

        // ยังไม่เลือกหนังสือยินยอม = ส่งออกไม่ได้
        $this->actingAs($this->admin())->get('/gate/devices-faces')->assertRedirect()->assertSessionHas('warning');

        $form = ConsentForm::create(['title' => 'ยินยอมให้ใช้ใบหน้าสแกนเข้า-ออก', 'body' => 'x', 'classroom_ids' => [$agreedWithPhoto->classroom_id], 'is_open' => true, 'created_by' => $this->admin()->id]);
        foreach ([[$agreedWithPhoto, true], [$agreedNoPhoto, true], [$refused, false]] as [$s, $agreed]) {
            ConsentResponse::create(['consent_form_id' => $form->id, 'student_id' => $s->id, 'user_id' => $this->admin()->id, 'agreed' => $agreed]);
        }
        $this->actingAs($this->admin())->post('/gate/devices-consent', ['consent_form_id' => $form->id])->assertRedirect();

        $this->actingAs($this->admin())->get('/gate/devices')->assertOk()
            ->assertSee('ยินยอมแล้วแต่ยังไม่มีรูปในระบบ 1 คน')->assertSee($agreedNoPhoto->student_code);

        $response = $this->actingAs($this->admin())->get('/gate/devices-faces')->assertOk();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        sort($names);
        $this->assertSame(['README.txt', "photos/{$agreedWithPhoto->student_code}.jpg", 'students.csv'], $names);
        $csv = $zip->getFromName('students.csv');
        $this->assertStringContainsString($agreedWithPhoto->student_code, $csv);
        $this->assertStringNotContainsString($refused->student_code, $csv);
        $this->assertStringNotContainsString($silent->student_code, $csv);
        // รูปถูกย่อเป็น JPEG ด้านยาวไม่เกิน 960px
        [$w, $h] = getimagesizefromstring($zip->getFromName("photos/{$agreedWithPhoto->student_code}.jpg"));
        $this->assertSame([720, 960], [$w, $h]);
        $zip->close();

        $this->actingAs(User::where('role', 'teacher')->first())->get('/gate/devices-faces')->assertForbidden();
    }

    private function teacher(): User
    {
        $t = User::where('username', 'teacher')->first();
        StaffAttendance::where('user_id', $t->id)->delete();

        return $t;
    }

    private function staffRecord(User $u): ?StaffAttendance
    {
        return StaffAttendance::where('user_id', $u->id)->where('date', today()->toDateString())->first();
    }

    public function test_teacher_scans_first_in_then_out_on_an_auto_device(): void
    {
        $d = $this->device('ประตูเดียว');
        $t = $this->teacher();

        $this->travelTo(today()->setTime(7, 35));
        $this->postJson("/gate/hook/{$d->token}", ['code' => $t->username])->assertOk()->assertJsonPath('result', 'present');
        // เครื่องอ่านหน้าเดิมซ้ำภายใน 5 นาที ไม่นับ
        $this->travelTo(today()->setTime(7, 37));
        $this->postJson("/gate/hook/{$d->token}", ['code' => $t->username])->assertJsonPath('result', 'repeat');
        $this->assertNull($this->staffRecord($t)->check_out);

        // ออกไปตอนเที่ยงแล้วกลับ ออกอีกครั้งตอนเย็น: ครั้งล่าสุดเป็นเวลาออก
        $this->travelTo(today()->setTime(12, 5));
        $this->postJson("/gate/hook/{$d->token}", ['code' => $t->username])->assertJsonPath('result', 'out');
        $this->travelTo(today()->setTime(16, 40));
        $this->postJson("/gate/hook/{$d->token}", ['code' => $t->username])->assertJsonPath('result', 'out');

        $rec = $this->staffRecord($t);
        $this->assertSame(['07:35:00', '16:40:00', 'present', 'gate'], [$rec->check_in, $rec->check_out, $rec->status, $rec->source]);
        $this->assertSame(4, GateEvent::where('user_id', $t->id)->count());
        $this->assertSame(0, Attendance::where('source', 'gate')->where('date', today()->toDateString())->count());

        $this->actingAs($this->admin())->get('/gate/devices')->assertOk()->assertSee($t->name)->assertSee('ครู/บุคลากร');
        $this->actingAs($this->admin())->get('/staff-attendance')->assertOk()->assertSee('สแกนที่ประตู');
    }

    public function test_teacher_late_uses_the_staff_late_time_and_fixed_direction_devices(): void
    {
        Settings::set(['staff_late_time' => '07:45', 'late_time' => '08:30']);
        $in = $this->device('ขาเข้า', 'in');
        $out = $this->device('ขาออก', 'out');
        $t = $this->teacher();

        $this->travelTo(today()->setTime(8, 0));
        $this->postJson("/gate/hook/{$in->token}", ['code' => $t->username])->assertJsonPath('result', 'late');
        $this->travelTo(today()->setTime(10, 0));
        $this->postJson("/gate/hook/{$in->token}", ['code' => $t->username])->assertJsonPath('result', 'repeat');
        $this->postJson("/gate/hook/{$out->token}", ['code' => $t->username])->assertJsonPath('result', 'out');

        $rec = $this->staffRecord($t);
        $this->assertSame(['08:00:00', '10:00:00', 'late'], [$rec->check_in, $rec->check_out, $rec->status]);
    }

    public function test_gate_scan_keeps_a_mobile_check_in_and_a_leave_status(): void
    {
        $d = $this->device('ประตู');
        $t = $this->teacher();
        $this->travelTo(today()->setTime(7, 20));
        $this->actingAs($t)->post('/checkin', ['action' => 'in'])->assertRedirect();
        auth()->logout();

        // ลงเวลาจากมือถือไปแล้ว: สแกนที่ประตูไม่ทับเวลาเข้า (นับเป็นขาออกเมื่อเลย 5 นาที)
        $this->travelTo(today()->setTime(7, 22));
        $this->postJson("/gate/hook/{$d->token}", ['code' => $t->username])->assertJsonPath('result', 'repeat');
        $this->assertSame('07:20:00', $this->staffRecord($t)->check_in);
        $this->assertNull($this->staffRecord($t)->source);

        // วันที่บันทึกลาไว้: เก็บเวลาแต่ไม่เปลี่ยนสถานะ
        $other = User::where('role', 'teacher')->where('id', '!=', $t->id)->where('is_active', true)->first();
        StaffAttendance::where('user_id', $other->id)->delete();
        StaffAttendance::create(['user_id' => $other->id, 'date' => today()->toDateString(), 'status' => 'leave']);
        $this->postJson("/gate/hook/{$d->token}", ['code' => $other->username])->assertOk();
        $rec = $this->staffRecord($other);
        $this->assertSame(['leave', '07:22:00'], [$rec->status, $rec->check_in]);
    }

    public function test_parent_and_inactive_accounts_are_not_treated_as_staff(): void
    {
        $d = $this->device('ประตู');
        $parent = User::where('role', 'parent')->first();
        $this->postJson("/gate/hook/{$d->token}", ['code' => $parent->username])->assertJsonPath('result', 'unknown');

        $t = $this->teacher();
        $t->update(['is_active' => false]);
        $this->postJson("/gate/hook/{$d->token}", ['code' => $t->username])->assertJsonPath('result', 'unknown');
        $this->assertSame(0, StaffAttendance::where('date', today()->toDateString())->whereIn('user_id', [$parent->id, $t->id])->count());
    }

    public function test_staff_face_photos_are_exported_only_after_consent_is_recorded(): void
    {
        Storage::fake('public');
        $t = $this->teacher();
        $other = User::where('role', 'teacher')->where('id', '!=', $t->id)->where('is_active', true)->first();
        foreach ([$t, $other] as $u) {
            Storage::disk('public')->put("avatars/{$u->id}.png", UploadedFile::fake()->image('a.png', 400, 500)->getContent());
            $u->update(['avatar' => "avatars/{$u->id}.png"]);
        }

        $this->actingAs($this->admin())->get('/gate/devices-faces')->assertRedirect()->assertSessionHas('warning');
        $this->actingAs($this->admin())->post('/gate/devices-staff-consent', ['staff' => [$t->id]])->assertRedirect();
        $this->assertNotNull($t->fresh()->face_consent_at);
        $this->assertNull($other->fresh()->face_consent_at);

        $response = $this->actingAs($this->admin())->get('/gate/devices-faces')->assertOk();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $this->assertNotFalse($zip->locateName("staff/{$t->username}.jpg"));
        $this->assertFalse($zip->locateName("staff/{$other->username}.jpg"));
        $this->assertStringContainsString($t->username, $zip->getFromName('staff.csv'));
        $zip->close();

        // เอาติ๊กออก = ถอนความยินยอม
        $this->actingAs($this->admin())->post('/gate/devices-staff-consent', [])->assertRedirect();
        $this->assertNull($t->fresh()->face_consent_at);
    }
}
