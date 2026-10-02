<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Course;
use App\Models\DocumentIssue;
use App\Models\HealthMeasurement;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** เอกสาร ปพ.5 / ปพ.6 / ปพ.7 */
class DocumentPrintTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function teacher(): User
    {
        return User::where('username', 'teacher')->first();
    }

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    public function test_pp5_shows_summary_scores_and_approval_signatures(): void
    {
        Settings::set(['academic_deputy_name' => 'นางวิชาการ ทดสอบ']);
        $course = Course::where('teacher_id', $this->teacher()->id)->whereHas('term', fn ($q) => $q->where('year', 2568))
            ->whereHas('subject', fn ($q) => $q->where('type', 'basic'))->first();
        $student = $course->classroom->students()->first();

        $this->actingAs($this->teacher())->get("/courses/{$course->id}/pp5")->assertOk()
            ->assertSee('(ปพ.5)')->assertSee($course->subject->code)->assertSee($student->fullName())
            ->assertSee('ครูผู้สอน')->assertSee('หัวหน้างานวัดผล')->assertSee('นางวิชาการ ทดสอบ')->assertSee('☐ อนุมัติ')
            ->assertViewHas('graded', $course->classroom->students()->count());
        $this->actingAs($this->teacher())->get("/courses/{$course->id}/grades")->assertSee(route('gradebook.pp5', $course));

        $other = Course::where('teacher_id', '!=', $this->teacher()->id)->first();
        $this->actingAs($this->teacher())->get("/courses/{$other->id}/pp5")->assertForbidden();

        // กิจกรรม: สรุปเป็น ผ / มผ
        $activity = Course::whereHas('subject', fn ($q) => $q->where('activity_kind', 'club'))->whereHas('term', fn ($q) => $q->where('year', 2568))->first();
        $this->actingAs($this->teacher())->get("/courses/{$activity->id}/pp5")->assertOk()->assertSee('แก้ตัวจาก มผ เป็น ผ')
            ->assertViewHas('distribution', fn ($d) => array_keys($d->all()) === ['ผ', 'มผ']);
    }

    public function test_pp6_has_activities_traits_and_measurement(): void
    {
        $prev = Term::where('year', 2568)->first();
        $student = Student::whereHas('classroom', fn ($q) => $q->where('level', 'ม.1')->where('room', 1))->orderBy('number')->first();
        HealthMeasurement::create(['student_id' => $student->id, 'measured_on' => '2026-01-10', 'weight' => 42.5, 'height' => 151, 'recorded_by' => $this->admin()->id]);

        $res = $this->actingAs($this->admin())->get("/report-card/{$student->id}?term={$prev->id}")->assertOk()
            ->assertSee('(ปพ.6)')->assertSee('กิจกรรมพัฒนาผู้เรียน')->assertSee('ลูกเสือ-เนตรนารี')
            ->assertSee('คุณลักษณะอันพึงประสงค์')->assertSee('42.5 กก. / 151 ซม.')->assertSee('ความคิดเห็นของผู้ปกครอง');
        $card = $res->viewData('cards')[0];
        // กิจกรรมแยกออกจากวิชาเรียน และไม่นับในหน่วยกิต
        $this->assertTrue($card['activities']->every(fn ($g) => $g['course']->isActivity()));
        $this->assertTrue($card['academic']->every(fn ($g) => ! $g['course']->isActivity()));
        $this->assertSame(4, $card['activities']->count());
    }

    public function test_pp6_prints_whole_classroom(): void
    {
        $classroom = Classroom::where('level', 'ม.1')->where('room', 1)->first();
        $count = $classroom->students()->count();

        $res = $this->actingAs($this->teacher())->get("/report-cards?classroom={$classroom->id}")->assertOk();
        $this->assertCount($count, $res->viewData('cards'));
        $this->assertSame($count, substr_count($res->getContent(), '(ปพ.6)</h2>'));

        $parent = User::where('role', 'parent')->first();
        $this->actingAs($parent)->get("/report-cards?classroom={$classroom->id}")->assertForbidden();
    }

    public function test_pp7_issue_numbers_register_and_reprint(): void
    {
        $student = Student::whereHas('classroom', fn ($q) => $q->where('level', 'ม.1')->where('room', 1))->first();
        $student->update(['father_name' => 'นายพ่อ ทดสอบ', 'mother_name' => 'นางแม่ ทดสอบ', 'citizen_id' => '1100000000001']);
        $year = today()->year + 543;

        $this->actingAs($this->admin())->get("/students/{$student->id}")->assertSee(route('certificates.create', $student));
        $this->actingAs($this->admin())->get("/students/{$student->id}/certificate")->assertOk()->assertSee('ออกให้เพื่อ');
        $this->actingAs($this->admin())->post("/students/{$student->id}/certificate", [])->assertSessionHasErrors('purpose');

        $this->actingAs($this->admin())->post("/students/{$student->id}/certificate", ['purpose' => 'ศึกษาต่อ'])->assertRedirect();
        $this->actingAs($this->admin())->post("/students/{$student->id}/certificate", ['purpose' => 'ขอทุนการศึกษา'])->assertRedirect();
        $this->assertSame([1, 2], DocumentIssue::where('year', $year)->orderBy('number')->pluck('number')->all());

        $first = DocumentIssue::where('number', 1)->first();
        $this->assertNotNull($first->snapshot['gpax']);
        // แก้ข้อมูลนักเรียนทีหลัง ฉบับที่ออกไปแล้วต้องพิมพ์ซ้ำได้เหมือนเดิม
        $student->update(['father_name' => 'ชื่อใหม่']);
        $this->actingAs($this->admin())->get("/certificates/{$first->id}")->assertOk()
            ->assertSee("เลขที่ 1/{$year}")->assertSee($student->fullName())->assertSee('นายพ่อ ทดสอบ')->assertDontSee('ชื่อใหม่')
            ->assertSee('กำลังศึกษาอยู่ชั้น ม.1/1')->assertSee('ออกให้เพื่อ ศึกษาต่อ');
        $this->actingAs($this->admin())->get('/certificates')->assertOk()->assertSee("2/{$year}")->assertSee('ขอทุนการศึกษา');

        // ครูออก/ดูทะเบียนไม่ได้
        $this->actingAs($this->teacher())->post("/students/{$student->id}/certificate", ['purpose' => 'x'])->assertForbidden();
        $this->actingAs($this->teacher())->get('/certificates')->assertForbidden();
    }

    public function test_pp7_wording_for_student_who_left(): void
    {
        $student = Student::first();
        $student->update(['status' => 'moved', 'admitted_on' => '2025-05-16', 'left_on' => '2026-03-31', 'leave_reason' => 'ย้ายตามผู้ปกครอง']);
        $this->actingAs($this->admin())->post("/students/{$student->id}/certificate", ['purpose' => 'ย้ายสถานศึกษา']);
        $this->actingAs($this->admin())->get('/certificates/'.DocumentIssue::first()->id)
            ->assertSee('เคยเป็นนักเรียนของโรงเรียนนี้')->assertSee('ย้ายตามผู้ปกครอง')->assertDontSee('กำลังศึกษาอยู่ชั้น');
    }

    public function test_reports_page_links_documents(): void
    {
        $this->actingAs($this->admin())->get('/reports')->assertOk()->assertSee('พิมพ์ทั้งห้อง')->assertSee(route('certificates.index'));
        $this->actingAs($this->teacher())->get('/reports')->assertOk()->assertDontSee(route('certificates.index'));
    }
}
