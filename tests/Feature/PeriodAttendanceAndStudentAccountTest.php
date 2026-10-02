<?php

namespace Tests\Feature;

use App\Http\Controllers\StudentAccountController;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\MessageLog;
use App\Models\PeriodAttendance;
use App\Models\Student;
use App\Models\Submission;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PeriodAttendanceAndStudentAccountTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function teacher(): User
    {
        return User::where('username', 'teacher')->first();
    }

    private function studentUser(): User
    {
        return User::where('role', 'student')->where('username', '69001')->first();
    }

    /** วิชาที่ครูตัวอย่างสอนในห้อง ม.1/1 */
    private function mathCourse(): Course
    {
        $room = $this->teacher()->myClassrooms()->first();

        return Course::where('teacher_id', $this->teacher()->id)->where('classroom_id', $room->id)->whereHas('term', fn ($q) => $q->where('is_current', true))->first();
    }

    public function test_period_attendance_pages_render_for_teacher(): void
    {
        $course = $this->mathCourse();
        $this->actingAs($this->teacher());
        $this->get('/period-attendance')->assertOk()->assertSee('เช็คชื่อรายคาบ');
        $this->get("/period-attendance/{$course->id}?period=2")->assertOk()->assertSee('id="attendanceForm"', false);
        $this->get("/period-attendance/{$course->id}/report")->assertOk()->assertSee('มส.');
        $this->get("/courses/{$course->id}/grades")->assertOk()->assertSee('เวลาเรียน');
    }

    public function test_teacher_cannot_check_other_teachers_course(): void
    {
        $other = Course::where('teacher_id', '!=', $this->teacher()->id)->first();
        $this->actingAs($this->teacher())->get("/period-attendance/{$other->id}?period=1")->assertForbidden();
        $this->actingAs($this->teacher())->post("/period-attendance/{$other->id}", ['date' => today()->toDateString(), 'period' => 1, 'status' => []])->assertForbidden();
    }

    public function test_save_upserts_and_summary_flags_ms_below_80_percent(): void
    {
        $course = $this->mathCourse();
        $students = $course->classroom->students()->get();
        $target = $students->first();
        PeriodAttendance::where('course_id', $course->id)->delete();

        // 10 คาบ: target มา 7 คาบ ขาด 3 → 70% = มส.
        for ($i = 0; $i < 10; $i++) {
            $status = $students->mapWithKeys(fn ($s) => [$s->id => 'present'])->all();
            $status[$target->id] = $i < 3 ? 'absent' : 'present';
            $this->actingAs($this->teacher())->post("/period-attendance/{$course->id}", [
                'date' => today()->subDays(20 + $i)->toDateString(), 'period' => 1, 'status' => $status,
            ])->assertRedirect();
        }
        // บันทึกคาบเดิมซ้ำ = แก้ไข ไม่ใช่เพิ่มแถว
        $this->actingAs($this->teacher())->post("/period-attendance/{$course->id}", [
            'date' => today()->subDays(20)->toDateString(), 'period' => 1, 'status' => [$target->id => 'absent'],
        ]);
        $this->assertSame(10, PeriodAttendance::where('course_id', $course->id)->where('student_id', $target->id)->count());

        $summary = PeriodAttendance::summaryFor($course);
        $this->assertSame(70.0, $summary[$target->id]['percent']);
        $this->assertTrue($summary[$target->id]['ms']);
        $this->assertSame(3, $summary[$target->id]['absent']);
        $this->assertFalse($summary[$students[1]->id]['ms']);

        // ลา/ป่วยไม่นับเป็นเวลาเรียน
        $this->actingAs($this->teacher())->post("/period-attendance/{$course->id}", [
            'date' => today()->subDays(40)->toDateString(), 'period' => 1, 'status' => [$students[1]->id => 'sick'],
        ]);
        $s1 = PeriodAttendance::summaryFor($course)[$students[1]->id];
        $this->assertSame(1, $s1['leave']);
        $this->assertSame(10, $s1['came']);

        // หน้าผู้ปกครองแสดง มส.
        $guardian = $target->guardians()->first();
        $this->actingAs($guardian)->get("/parent/child/{$target->id}?tab=grades")->assertOk()->assertSee('มส.');
    }

    public function test_students_from_other_rooms_are_ignored_and_status_is_validated(): void
    {
        $course = $this->mathCourse();
        $outsider = Student::where('classroom_id', '!=', $course->classroom_id)->first();
        PeriodAttendance::where('course_id', $course->id)->delete();

        $this->actingAs($this->teacher())->post("/period-attendance/{$course->id}", [
            'date' => today()->toDateString(), 'period' => 3, 'status' => [$outsider->id => 'present'],
        ]);
        $this->assertSame(0, PeriodAttendance::where('course_id', $course->id)->count());

        $this->actingAs($this->teacher())->post("/period-attendance/{$course->id}", [
            'date' => today()->toDateString(), 'period' => 3, 'status' => [$course->classroom->students()->first()->id => 'hacked'],
        ])->assertSessionHasErrors('status.*');
        $this->actingAs($this->teacher())->post("/period-attendance/{$course->id}", [
            'date' => today()->addDay()->toDateString(), 'period' => 3, 'status' => [],
        ])->assertSessionHasErrors('date');
    }

    public function test_cutting_class_today_notifies_parents(): void
    {
        $this->withoutDefer();
        $course = $this->mathCourse();
        [$cutter, $homeSick] = $course->classroom->students()->take(2)->get()->all();
        foreach ([$cutter, $homeSick] as $s) {
            $s->guardians()->first()->update(['line_user_id' => 'U'.$s->id]);
        }
        $today = today()->toDateString();
        Attendance::where('date', $today)->whereIn('student_id', [$cutter->id, $homeSick->id])->delete();
        Attendance::create(['student_id' => $cutter->id, 'classroom_id' => $cutter->classroom_id, 'date' => $today, 'status' => 'present']);
        Attendance::create(['student_id' => $homeSick->id, 'classroom_id' => $homeSick->classroom_id, 'date' => $today, 'status' => 'absent']);
        PeriodAttendance::where(['course_id' => $course->id, 'date' => $today])->delete();

        $this->actingAs($this->teacher())->post("/period-attendance/{$course->id}", [
            'date' => $today, 'period' => 4, 'status' => [$cutter->id => 'absent', $homeSick->id => 'absent'],
        ])->assertRedirect(route('period-attendance.index', ['date' => $today]));

        $cutterParent = $cutter->guardians()->first();
        $this->assertTrue(MessageLog::where('user_id', $cutterParent->id)->where('text', 'like', '%ไม่เข้าเรียนวิชา%')->exists());
        // ขาดทั้งวันอยู่แล้ว ไม่ใช่โดดเรียน → ไม่แจ้งซ้ำ
        $this->assertFalse(MessageLog::where('user_id', $homeSick->guardians()->first()->id)->where('text', 'like', '%ไม่เข้าเรียนวิชา%')->exists());

        // บันทึกซ้ำ (คนเดิมยังขาด) ไม่แจ้งซ้ำ
        $before = MessageLog::where('user_id', $cutterParent->id)->count();
        $this->actingAs($this->teacher())->post("/period-attendance/{$course->id}", [
            'date' => $today, 'period' => 4, 'status' => [$cutter->id => 'absent'],
        ]);
        $this->assertSame($before, MessageLog::where('user_id', $cutterParent->id)->count());
    }

    public function test_homeroom_teacher_creates_accounts_and_slips_show_once(): void
    {
        $room = $this->teacher()->myClassrooms()->first();
        $without = $room->students()->whereNull('user_id')->count();
        $this->assertGreaterThan(0, $without);

        $res = $this->actingAs($this->teacher())->post('/student-accounts', ['classroom_id' => $room->id]);
        $res->assertRedirect(route('student-accounts.slips'));
        $creds = session('student_credentials');
        $this->assertCount($without, $creds);
        $this->assertSame(0, $room->students()->whereNull('user_id')->count());

        $first = $creds[0];
        $user = User::where('username', $first['username'])->first();
        $this->assertTrue($user->isStudent());
        $this->assertTrue(Hash::check($first['password'], $user->password));
        $this->assertNotNull($user->studentProfile);

        // ใบแจกแสดงรหัสผ่าน แล้วหายไปเมื่อเปิดซ้ำ
        $this->actingAs($this->teacher())->withSession(['student_credentials' => $creds])->get('/student-accounts/slips')->assertOk()->assertSee($first['password']);
        $this->actingAs($this->teacher())->get('/student-accounts/slips')->assertRedirect(route('students.index'));

        // ห้องที่ไม่ได้ดูแลสร้างไม่ได้
        $otherRoom = \App\Models\Classroom::where('homeroom_teacher_id', '!=', $this->teacher()->id)->first();
        $this->actingAs($this->teacher())->post('/student-accounts', ['classroom_id' => $otherRoom->id])->assertForbidden();

        // รีเซ็ต → รหัสเดิมใช้ไม่ได้
        $student = $user->studentProfile;
        $this->actingAs($this->teacher())->post("/student-accounts/{$student->id}/reset")->assertRedirect(route('student-accounts.slips'));
        $this->assertFalse(Hash::check($first['password'], $user->fresh()->password));

        // ปิดบัญชี → ล็อกอินไม่ได้
        $this->actingAs($this->teacher())->post("/student-accounts/{$student->id}/toggle");
        $this->assertFalse((bool) $user->fresh()->is_active);
    }

    public function test_password_generator_avoids_ambiguous_characters(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $p = StudentAccountController::password();
            $this->assertSame(8, strlen($p));
            $this->assertDoesNotMatchRegularExpression('/[01ilo]/', $p);
        }
    }

    public function test_student_can_log_in_and_see_own_portal(): void
    {
        $this->post('/login', ['username' => '69001', 'password' => 'student1234'])->assertRedirect();
        $this->assertAuthenticatedAs($this->studentUser());

        $this->get('/')->assertRedirect(route('student.home'));
        $me = $this->studentUser()->studentProfile;
        $this->get('/me')->assertOk()->assertSee($me->student_code)->assertSee('การบ้านที่ต้องส่ง');
        foreach (['/me/info', '/me/info?tab=grades', '/me/info?tab=timetable', '/me/homework', '/menu', '/notifications', '/feed', '/calendar', '/announcements', '/profile',
            "/portfolio/{$me->id}", "/transcript/{$me->id}", "/report-card/{$me->id}"] as $url) {
            $this->assertSame(200, $this->get($url)->status(), $url);
        }
        // ไม่มีแท็บค่าเทอม/แบบประเมิน
        $this->get('/me/info?tab=fees')->assertDontSee('id="p-fees"', false)->assertDontSee('id="p-survey"', false);
    }

    public function test_student_is_confined_to_own_data(): void
    {
        $user = $this->studentUser();
        $me = $user->studentProfile;
        $other = Student::where('id', '!=', $me->id)->first();
        $this->actingAs($user);

        foreach (["/portfolio/{$other->id}", "/transcript/{$other->id}", "/report-card/{$other->id}"] as $url) {
            $this->assertSame(403, $this->get($url)->status(), $url);
        }
        // หน้าของครู/ผู้ปกครอง/แชท/แบบประเมิน
        foreach (['/students', '/attendance', '/period-attendance', '/courses', '/chat', '/parent', '/parent/homework', "/students/{$me->id}", '/search?q=a'] as $url) {
            $this->assertSame(403, $this->get($url)->status(), $url);
        }
        $survey = Survey::first();
        $this->assertSame(403, $this->get("/surveys/{$survey->id}/students/{$me->id}")->status());
        $invoice = $me->invoices()->first();
        $this->assertSame(403, $this->get("/invoices/{$invoice->id}")->status());
        $this->post('/feed', ['body' => 'hi'])->assertForbidden();

        // แก้ชื่อตัวเองไม่ได้ แต่เปลี่ยนรหัสผ่านได้
        $this->put('/profile', ['name' => 'ชื่อปลอม', 'current_password' => 'student1234', 'password' => 'newpass99', 'password_confirmation' => 'newpass99'])->assertRedirect();
        $this->assertSame($me->fullName(), $user->fresh()->name);
        $this->assertTrue(Hash::check('newpass99', $user->fresh()->password));
    }

    public function test_student_submits_own_homework_only(): void
    {
        $user = $this->studentUser();
        $me = $user->studentProfile;
        $hw = Assignment::whereHas('course', fn ($q) => $q->where('classroom_id', $me->classroom_id))->first();
        Submission::where('assignment_id', $hw->id)->where('student_id', $me->id)->delete();
        $classmate = Student::where('classroom_id', $me->classroom_id)->where('id', '!=', $me->id)->first();

        $this->actingAs($user)->post("/me/homework/{$hw->id}", ['student_id' => $classmate->id, 'text' => 'แอบส่งแทนเพื่อน'])->assertForbidden();
        $this->actingAs($user)->post("/me/homework/{$hw->id}", ['student_id' => $me->id, 'text' => 'ส่งงานค่ะ'])->assertRedirect();

        $sub = Submission::where('assignment_id', $hw->id)->where('student_id', $me->id)->first();
        $this->assertNotNull($sub?->submitted_at);
        $this->assertSame($user->id, $sub->submitted_by);
        $this->actingAs($user)->get('/me/homework')->assertOk()->assertSee('ส่งใหม่ / แก้ไขงาน');
    }

    public function test_student_sees_only_own_classroom_announcements(): void
    {
        $user = $this->studentUser();
        $me = $user->studentProfile;
        $mine = \App\Models\Announcement::where('audience', 'classroom')->where('classroom_id', $me->classroom_id)->first();
        $parentsOnly = \App\Models\Announcement::where('audience', 'parents')->first();
        $staffOnly = \App\Models\Announcement::where('audience', 'staff')->first();

        $this->actingAs($user)->get("/announcements/{$mine->id}")->assertOk();
        $this->actingAs($user)->get("/announcements/{$parentsOnly->id}")->assertNotFound();
        $this->actingAs($user)->get("/announcements/{$staffOnly->id}")->assertNotFound();
    }
}
