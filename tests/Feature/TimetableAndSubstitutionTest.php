<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Course;
use App\Models\Role;
use App\Models\StaffLeave;
use App\Models\Student;
use App\Models\Substitution;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetableAndSubstitutionTest extends TestCase
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

    /* ---------------- วันเรียน ---------------- */

    public function test_school_days_setting_adds_saturday_to_the_timetable(): void
    {
        $this->assertSame([1, 2, 3, 4, 5], array_keys(TimetableSlot::days()));
        $this->actingAs($this->admin())->get(route('timetable.index'))->assertOk()->assertDontSee('เสาร์');

        Settings::set(['school_days' => '6']);

        $this->assertSame([1, 2, 3, 4, 5, 6], array_keys(TimetableSlot::days()));
        $this->get(route('timetable.index'))->assertOk()->assertSee('เสาร์');
    }

    /* ---------------- สอนแทน ---------------- */

    public function test_substitution_flow_for_a_teacher_on_approved_leave(): void
    {
        $term = Term::current();
        // ตารางสอนมีเฉพาะวันเรียน ทดสอบโดยตั้งเวลาเป็นวันจันทร์ถัดไป
        $this->travelTo(now()->next(\Carbon\Carbon::MONDAY)->setTime(8, 0));
        $absent = $this->teacher();
        $date = today();
        $slot = TimetableSlot::with('course')->where('term_id', $term->id)->where('day', $date->dayOfWeekIso)
            ->whereHas('course', fn ($q) => $q->where('teacher_id', $absent->id))->orderBy('period')->first();
        $this->assertNotNull($slot, 'ข้อมูลตัวอย่างต้องมีคาบสอนของครูตัวอย่างในวันนี้');
        StaffLeave::create(['user_id' => $absent->id, 'type' => 'sick', 'start_date' => $date, 'end_date' => $date, 'reason' => 'ป่วย', 'status' => 'approved']);

        $busy = TimetableSlot::where('term_id', $term->id)->where('day', $slot->day)->where('period', $slot->period)
            ->with('course')->get()->pluck('course.teacher_id')->filter()->unique();
        $free = User::where('role', 'teacher')->where('is_active', true)->whereNotIn('id', $busy)->where('id', '!=', $absent->id)->first();
        $busyTeacher = User::whereIn('id', $busy)->where('id', '!=', $absent->id)->first();
        $this->assertNotNull($free);

        $this->actingAs($this->admin())->get(route('substitutions.index'))->assertOk()
            ->assertSee($slot->course->subject->name)->assertSee($absent->name);

        // ครูที่มีสอนคาบเดียวกันรับสอนแทนไม่ได้
        if ($busyTeacher) {
            $this->post(route('substitutions.store'), ['date' => $date->toDateString(), 'timetable_slot_id' => $slot->id, 'substitute_id' => $busyTeacher->id])
                ->assertSessionHasErrors('substitute_id');
        }

        // ก่อนจัด ครูอีกคนเข้าเช็คชื่อวิชานี้ไม่ได้
        $this->actingAs($free)->get(route('period-attendance.sheet', ['course' => $slot->course_id, 'period' => $slot->period]))->assertForbidden();

        $this->actingAs($this->admin())->post(route('substitutions.store'), ['date' => $date->toDateString(), 'timetable_slot_id' => $slot->id, 'substitute_id' => $free->id])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('substitutions', ['timetable_slot_id' => $slot->id, 'substitute_id' => $free->id, 'absent_teacher_id' => $absent->id, 'period' => $slot->period]);

        // ครูสอนแทนเห็นคาบในรายการของตัวเอง และเช็คชื่อได้เฉพาะวันนั้น
        $this->actingAs($free)->get(route('period-attendance.index'))->assertOk()->assertSee($slot->course->subject->name);
        $this->get(route('period-attendance.sheet', ['course' => $slot->course_id, 'period' => $slot->period]))->assertOk();
        $this->get(route('period-attendance.sheet', ['course' => $slot->course_id, 'period' => $slot->period, 'date' => $date->copy()->subDays(7)->toDateString()]))->assertForbidden();
        $this->get(route('gradebook.show', $slot->course_id))->assertForbidden();

        // เลือก "ยังไม่จัด" = ยกเลิก · ครูทั่วไปเข้าหน้าจัดสอนแทนไม่ได้
        $this->actingAs($this->admin())->post(route('substitutions.store'), ['date' => $date->toDateString(), 'timetable_slot_id' => $slot->id, 'substitute_id' => ''])->assertRedirect();
        $this->assertSame(0, Substitution::count());
        $this->actingAs($free)->get(route('substitutions.index'))->assertForbidden();
    }
}
