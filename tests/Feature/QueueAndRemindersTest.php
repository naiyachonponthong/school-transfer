<?php

namespace Tests\Feature;

use App\Jobs\SendLineMessage;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\SchoolEvent;
use App\Models\Student;
use App\Models\User;
use App\Services\Line;
use App\Services\Notifier;
use App\Support\Settings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueueAndRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function linkedParent(): User
    {
        $parent = User::where('phone', '0812345678')->first();
        $parent->forceFill(['line_user_id' => 'Uparent'])->save();

        return $parent;
    }

    private function configureLine(): void
    {
        Settings::set(['line_channel_token' => 'tok', 'line_channel_secret' => 'sec']);
    }

    /* ---------------- คิวและการส่งซ้ำ ---------------- */

    public function test_transient_line_failure_is_retried_only_for_the_failed_recipients(): void
    {
        $this->configureLine();
        $parent = $this->linkedParent();
        Queue::fake();

        Http::fake(['api.line.me/*' => Http::response('down', 503)]);
        $this->assertSame([$parent->id], Line::send([$parent], 'x')->pluck('id')->all());

        (new SendLineMessage([$parent->id], 'hello'))->handle();
        Queue::assertPushed(SendLineMessage::class, fn ($job) => $job->userIds === [$parent->id] && $job->attempt === 2);

        // ครบจำนวนครั้งแล้วไม่วนส่งต่อ
        Queue::fake();
        (new SendLineMessage([$parent->id], 'hello', SendLineMessage::MAX_ATTEMPTS))->handle();
        Queue::assertNothingPushed();
    }

    public function test_permanent_failure_and_success_are_not_retried(): void
    {
        $this->configureLine();
        $parent = $this->linkedParent();

        Http::fake(['api.line.me/*' => Http::sequence()->push('bad token', 401)->push('{}', 200)]);
        $this->assertTrue(Line::send([$parent], 'x')->isEmpty());

        $before = Line::sentThisMonth();
        $this->assertTrue(Line::send([$parent], 'x')->isEmpty());
        $this->assertSame($before + 1, Line::sentThisMonth());
    }

    public function test_bulk_notifications_go_to_the_queue_after_the_inline_limit(): void
    {
        $this->configureLine();
        $parent = $this->linkedParent();
        Http::fake(['api.line.me/*' => Http::response('{}', 200)]);
        Queue::fake();

        foreach (range(1, Notifier::INLINE_LIMIT + 3) as $i) {
            Notifier::users([$parent], 'ข้อความ '.$i);
        }

        Queue::assertPushed(SendLineMessage::class, 3);
    }

    /* ---------------- เตือนตามเวลา ---------------- */

    public function test_fee_reminders_fire_before_due_and_weekly_when_overdue_without_duplicates(): void
    {
        $this->configureLine();
        $parent = $this->linkedParent();
        Http::fake(['api.line.me/*' => Http::response('{}', 200)]);
        Invoice::query()->update(['due_date' => null]);
        $child = $parent->children()->first();
        $make = fn (string $title, $due) => Invoice::create(['invoice_no' => 'T'.$title, 'student_id' => $child->id, 'title' => $title, 'total' => 500, 'due_date' => $due]);
        $soon = $make('ใกล้ครบ', today()->addDays(3));
        $overdue = $make('เลยกำหนด', today()->subDays(2));
        $far = $make('อีกนาน', today()->addDays(20));
        foreach ([$soon, $overdue, $far] as $inv) {
            $inv->items()->create(['description' => 'x', 'amount' => 500]);
        }

        $this->artisan('fees:remind --dry-run')->assertSuccessful();
        $this->assertNull($soon->fresh()->last_reminded_at);

        $this->artisan('fees:remind')->assertSuccessful();
        $this->assertNotNull($soon->fresh()->last_reminded_at);
        $this->assertNotNull($overdue->fresh()->last_reminded_at);
        $this->assertNull($far->fresh()->last_reminded_at);
        $sent = MessageLog::where('user_id', $parent->id)->where('status', 'sent')->count();
        $this->assertSame(2, $sent);

        // รันซ้ำวันเดียวกันไม่ส่งซ้ำ
        $this->artisan('fees:remind');
        $this->assertSame($sent, MessageLog::where('user_id', $parent->id)->where('status', 'sent')->count());

        // เลยกำหนดครบ 7 วันหลังเตือนครั้งก่อน เตือนอีกครั้ง
        $this->travel(8)->days();
        $this->artisan('fees:remind');
        $this->assertSame($sent + 2, MessageLog::where('user_id', $parent->id)->where('status', 'sent')->count());

        // ชำระครบแล้วไม่เตือน
        Invoice::whereIn('id', [$soon->id, $overdue->id])->update(['status' => 'paid']);
        $this->travel(8)->days();
        $this->artisan('fees:remind');
        $this->assertSame($sent + 2, MessageLog::where('user_id', $parent->id)->where('status', 'sent')->count());
    }

    public function test_unchecked_attendance_reminder_skips_checked_rooms_and_holidays(): void
    {
        $this->configureLine();
        Http::fake(['api.line.me/*' => Http::response('{}', 200)]);
        // คำสั่งนี้ไม่ส่งในวันเสาร์-อาทิตย์ ทดสอบโดยตั้งเวลาเป็นวันจันทร์ถัดไป
        $this->travelTo(now()->next(\Carbon\Carbon::MONDAY)->setTime(9, 0));
        $teacher = User::where('username', 'teacher')->first();
        $teacher->forceFill(['line_user_id' => 'Uteacher'])->save();
        $room = Classroom::where('homeroom_teacher_id', $teacher->id)->first();
        Attendance::where('date', today()->toDateString())->delete();

        $this->artisan('attendance:remind-unchecked')->assertSuccessful();
        $this->assertSame(1, MessageLog::where('user_id', $teacher->id)->where('text', 'like', '%'.$room->name().'%')->count());

        // เช็คชื่อแล้วไม่เตือนอีก
        Attendance::create(['student_id' => Student::where('classroom_id', $room->id)->value('id'), 'classroom_id' => $room->id, 'date' => today()->toDateString(), 'status' => 'present']);
        $this->artisan('attendance:remind-unchecked');
        $this->assertSame(1, MessageLog::where('user_id', $teacher->id)->count());

        // วันหยุดในปฏิทินไม่เตือน
        Attendance::where('date', today()->toDateString())->delete();
        SchoolEvent::create(['title' => 'วันหยุด', 'start_date' => today(), 'end_date' => today(), 'type' => 'holiday', 'audience' => 'all']);
        $this->artisan('attendance:remind-unchecked');
        $this->assertSame(1, MessageLog::where('user_id', $teacher->id)->count());
    }

    public function test_schedule_contains_backups_reminders_and_queue_runner(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command)->implode("\n");

        foreach (['backup:database', 'backup:files', 'fees:remind', 'attendance:remind-unchecked', 'library:remind-overdue', 'queue:work'] as $name) {
            $this->assertStringContainsString($name, $commands);
        }
    }
}
