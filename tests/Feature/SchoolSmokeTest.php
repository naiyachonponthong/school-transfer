<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\FeedPost;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\Student;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Support\Notifications;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_login_page_and_login(): void
    {
        $this->get('/login')->assertOk()->assertSee('เข้าสู่ระบบ');
        $this->post('/login', ['username' => 'admin', 'password' => 'admin1234'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_parent_can_login_with_phone(): void
    {
        $this->post('/login', ['username' => '0812345678', 'password' => '345678'])->assertRedirect('/');
        $this->get('/')->assertRedirect(route('parent.home'));
    }

    public function test_admin_pages_render(): void
    {
        $admin = User::where('username', 'admin')->first();
        $student = Student::first();
        $course = Course::first();
        $invoice = Invoice::whereHas('payments')->first();

        $urls = [
            '/', '/attendance', '/attendance/today', '/attendance/report', '/attendance/report?export=csv',
            '/students', '/students/create', '/students/import', '/students/import/template',
            "/students/{$student->id}", "/students/{$student->id}/edit", "/report-card/{$student->id}",
            '/leaves', '/leaves?status=all', '/behavior', '/behavior?classroom='.$student->classroom_id,
            '/courses', "/courses/{$course->id}/grades", "/courses/{$course->id}/export", '/evaluations',
            '/timetable', '/timetable?edit=1', '/timetable/mine', '/checkin',
            '/announcements', '/announcements/create', '/announcements/'.Announcement::first()->id,
            '/invoices', '/invoices/create', "/invoices/{$invoice->id}", '/payments/'.$invoice->payments->first()->id.'/receipt',
            '/users', '/users?role=parent', '/users/create', "/users/{$admin->id}/edit",
            '/classrooms', '/subjects', '/terms', '/settings', '/staff-attendance', '/profile',
            '/search?q='.urlencode(mb_substr($student->first_name, 0, 2)),
        ];

        foreach ($urls as $url) {
            $res = $this->actingAs($admin)->get($url);
            $code = $res->baseResponse->getStatusCode();
            $this->assertSame(200, $code, "GET {$url} → {$code}\n".($code === 200 ? '' : mb_substr(strip_tags((string) $res->getContent()), 0, 600)));
        }
    }

    public function test_teacher_pages_render_and_admin_pages_blocked(): void
    {
        $teacher = User::where('username', 'teacher')->first();
        foreach (['/', '/attendance', '/courses', '/leaves', '/timetable/mine', '/behavior', '/announcements/create'] as $url) {
            $this->actingAs($teacher)->get($url)->assertOk();
        }
        foreach (['/users', '/settings', '/classrooms', '/invoices/create'] as $url) {
            $this->actingAs($teacher)->get($url)->assertForbidden();
        }
        // ครูกรอกคะแนนวิชาคนอื่นไม่ได้
        $other = Course::where('teacher_id', '!=', $teacher->id)->first();
        $this->actingAs($teacher)->get("/courses/{$other->id}/grades")->assertForbidden();
    }

    public function test_parent_pages_render_and_scoped(): void
    {
        $parent = User::where('phone', '0812345678')->first();
        $child = $parent->children()->first();
        $this->assertSame(2, $parent->children()->count());

        foreach (['/parent', "/parent/child/{$child->id}", '/parent/leave', '/announcements', "/report-card/{$child->id}"] as $url) {
            $res = $this->actingAs($parent)->get($url);
            $this->assertTrue($res->isOk(), "GET {$url} → {$res->status()}");
        }

        $stranger = Student::whereDoesntHave('guardians', fn ($q) => $q->whereKey($parent->id))->first();
        $this->actingAs($parent)->get("/parent/child/{$stranger->id}")->assertForbidden();
        $this->actingAs($parent)->get("/report-card/{$stranger->id}")->assertForbidden();
        $this->actingAs($parent)->get('/students')->assertForbidden();
    }

    public function test_attendance_save_and_leave_approval(): void
    {
        $teacher = User::where('username', 'teacher')->first();
        $classroom = Classroom::where('homeroom_teacher_id', $teacher->id)->first();
        $students = $classroom->students()->get();
        $date = '2026-09-01';

        $status = $students->mapWithKeys(fn ($s, $i) => [$s->id => $i === 0 ? 'absent' : 'present'])->all();
        $this->actingAs($teacher)->post('/attendance', ['classroom_id' => $classroom->id, 'date' => $date, 'status' => $status])->assertRedirect();
        $this->assertSame($students->count(), Attendance::where('date', $date)->where('classroom_id', $classroom->id)->count());
        $this->assertSame('absent', Attendance::where('date', $date)->where('student_id', $students[0]->id)->value('status'));

        // บันทึกซ้ำต้องแก้ของเดิม ไม่สร้างแถวใหม่
        $status[$students[0]->id] = 'late';
        $this->actingAs($teacher)->post('/attendance', ['classroom_id' => $classroom->id, 'date' => $date, 'status' => $status])->assertRedirect();
        $this->assertSame($students->count(), Attendance::where('date', $date)->where('classroom_id', $classroom->id)->count());

        $leave = LeaveRequest::where('status', 'pending')->first();
        $this->actingAs($teacher)->post("/leaves/{$leave->id}/approve")->assertRedirect();
        $leave->refresh();
        $this->assertSame('approved', $leave->status);
        $this->assertTrue(Attendance::where('student_id', $leave->student_id)->where('date', $leave->start_date->toDateString())->whereIn('status', ['sick', 'leave'])->exists());
    }

    public function test_leave_details_review_confirmation_and_reviewed_status_guard(): void
    {
        $teacher = User::where('username', 'teacher')->first();
        $parent = User::where('phone', '0812345678')->first();
        $leave = LeaveRequest::where('status', 'pending')->first();

        $this->actingAs($teacher)->get(route('leaves.index'))
            ->assertOk()
            ->assertSee(route('leaves.show', $leave))
            ->assertSee('leaveReviewModal')
            ->assertSee('ยืนยันอนุมัติ');

        $this->actingAs($teacher)->get(route('leaves.show', $leave))
            ->assertOk()
            ->assertSee($leave->student->fullName())
            ->assertSee($leave->reason)
            ->assertSee($leave->requester->name)
            ->assertSee('ยืนยันไม่อนุมัติ');
        $this->actingAs($parent)->get(route('leaves.show', $leave))->assertForbidden();

        $this->actingAs($teacher)->post(route('leaves.reject', $leave), ['note' => 'ข้อมูลยังไม่ครบ'])->assertRedirect();
        $this->assertSame('rejected', $leave->fresh()->status);
        $this->actingAs($teacher)->get(route('leaves.show', $leave))->assertOk()->assertSee('ข้อมูลยังไม่ครบ');

        $this->actingAs($teacher)->post(route('leaves.approve', $leave))
            ->assertRedirect()->assertSessionHas('warning', 'ใบลานี้ได้รับการพิจารณาแล้ว');
        $this->assertSame('rejected', $leave->fresh()->status);
    }

    public function test_gradebook_autosave_json(): void
    {
        $teacher = User::where('username', 'teacher')->first();
        $course = Course::where('teacher_id', $teacher->id)->with('assessments')->first();
        $student = $course->classroom->students()->first();
        $final = $course->assessments->last();

        $res = $this->actingAs($teacher)->postJson("/courses/{$course->id}/grades", ['scores' => [$student->id => [$final->id => '25']]]);
        $res->assertOk()->assertJsonPath('ok', true);
        $this->assertNotNull($res->json("results.{$student->id}.grade"));

        // เกินคะแนนเต็มต้องไม่บันทึก
        $this->actingAs($teacher)->postJson("/courses/{$course->id}/grades", ['scores' => [$student->id => [$final->id => '999']]])
            ->assertJsonCount(1, 'errors');
    }

    public function test_invoice_create_and_pay(): void
    {
        $admin = User::where('username', 'admin')->first();
        $classroom = Classroom::first();
        $count = $classroom->students()->count();

        $this->actingAs($admin)->post('/invoices', [
            'title' => 'ค่าชุดกีฬา', 'classroom_ids' => [$classroom->id],
            'items' => [['description' => 'เสื้อกีฬา', 'amount' => 350], ['description' => '', 'amount' => '']],
        ])->assertRedirect(route('invoices.index'));
        $this->assertSame($count, Invoice::where('title', 'ค่าชุดกีฬา')->count());

        $inv = Invoice::where('title', 'ค่าชุดกีฬา')->first();
        $this->actingAs($admin)->post("/invoices/{$inv->id}/payments", ['amount' => 350, 'method' => 'cash'])->assertRedirect();
        $this->assertSame('paid', $inv->fresh()->status);
    }

    public function test_super_app_pages_render_for_every_role(): void
    {
        foreach (['admin', 'teacher', '0812345678'] as $login) {
            $user = User::where('username', $login)->first();
            foreach (['/menu', '/notifications', '/feed', '/feed?page=2', '/profile'] as $url) {
                $res = $this->actingAs($user)->get($url);
                $this->assertSame(200, $res->status(), "{$login} GET {$url}");
            }
        }
        // หน้าแจ้งเตือนเปิดแล้ว ตัวนับต้องเป็นศูนย์
        $parent = User::where('phone', '0812345678')->first();
        $this->assertSame(0, Notifications::unreadCount($parent->fresh()));
    }

    public function test_feed_post_react_and_visibility(): void
    {
        $teacher = User::where('username', 'teacher')->first();
        $parent = User::where('phone', '0812345678')->first();
        $student = $parent->children()->first();

        $this->actingAs($teacher)->post('/feed', [
            'type' => 'achievement', 'title' => 'รางวัลทดสอบ', 'body' => 'เก่งมาก',
            'student_code' => $student->student_code.' '.$student->fullName(), 'audience' => 'all',
        ])->assertRedirect();
        $post = FeedPost::where('title', 'รางวัลทดสอบ')->first();
        $this->assertSame($student->id, $post->student_id);

        $this->actingAs($parent)->get('/parent')->assertOk()->assertSee('รางวัลทดสอบ');
        $this->actingAs($parent)->postJson("/feed/{$post->id}/react", ['emoji' => '👏'])->assertOk()->assertJsonPath('reactions.👏.mine', true);
        $this->actingAs($parent)->postJson("/feed/{$post->id}/react", ['emoji' => '👏'])->assertOk()->assertJsonMissingPath('reactions.👏');

        // ผู้ปกครองไม่เห็นโพสต์เฉพาะครู และโพสต์เองไม่ได้
        $staffOnly = FeedPost::create(['type' => 'post', 'author_id' => $teacher->id, 'title' => 'ลับเฉพาะครู', 'audience' => 'staff']);
        $this->actingAs($parent)->get('/feed')->assertDontSee('ลับเฉพาะครู');
        $this->actingAs($parent)->postJson("/feed/{$staffOnly->id}/react", ['emoji' => '👍'])->assertNotFound();
        $this->actingAs($parent)->post('/feed', ['type' => 'post', 'body' => 'x', 'audience' => 'all'])->assertForbidden();
    }

    public function test_theme_color_setting_changes_css(): void
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin)->get('/')->assertSee('--sb-primary:#F26522', false);

        $settings = Settings::all();
        $settings['theme_color'] = '#2563eb';
        unset($settings['logo']);
        $this->actingAs($admin)->post('/settings', $settings)->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($admin)->get('/')->assertSee('--sb-primary:#2563EB', false);

        $settings['theme_color'] = 'orange';
        $this->actingAs($admin)->post('/settings', $settings)->assertSessionHasErrors('theme_color');
    }

    public function test_seeded_timetable_has_no_teacher_clash(): void
    {
        $clashes = TimetableSlot::join('courses', 'courses.id', '=', 'timetable_slots.course_id')
            ->selectRaw('courses.teacher_id, timetable_slots.day, timetable_slots.period, count(*) as n')
            ->groupBy('courses.teacher_id', 'timetable_slots.day', 'timetable_slots.period')
            ->havingRaw('count(*) > 1')->count();
        $this->assertSame(0, $clashes);
    }

    public function test_attendance_sheet_flags_pending_leave(): void
    {
        $teacher = User::where('username', 'teacher')->first();
        $leave = LeaveRequest::where('status', 'pending')->first();
        $this->actingAs($teacher)
            ->get('/attendance?classroom='.$leave->student->classroom_id.'&date='.$leave->start_date->toDateString())
            ->assertOk()->assertSee('รออนุมัติ');
    }

    public function test_student_import_by_paste(): void
    {
        $admin = User::where('username', 'admin')->first();
        $paste = "รหัสนักเรียน\tคำนำหน้า\tชื่อ\tนามสกุล\tห้อง\tเลขที่\tวันเกิด\tเบอร์ผู้ปกครอง\n"
            ."90001\tเด็กหญิง\tทดสอบ\tนำเข้า\tม.1/3\t1\t15/05/2557\t0899999999\n"
            ."90002\tเด็กชาย\tสอง\tนำเข้า\tม.1/3\t2\t\t\n";
        $this->actingAs($admin)->post('/students/import', ['paste' => $paste])->assertRedirect();

        $s = Student::where('student_code', '90001')->first();
        $this->assertNotNull($s);
        $this->assertSame('F', $s->gender);
        $this->assertSame('2014-05-15', $s->birthdate->toDateString());
        $this->assertSame('ม.1/3', $s->classroom->name());
        $this->assertTrue(User::where('phone', '0899999999')->where('role', 'parent')->exists());
    }
}
