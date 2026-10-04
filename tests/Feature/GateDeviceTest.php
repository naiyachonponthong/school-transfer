<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\GateDevice;
use App\Models\GateEvent;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
