<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\DocumentIssue;
use App\Models\Score;
use App\Models\Student;
use App\Models\StudentEvaluation;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Support\AcademicRecord;
use App\Support\Curriculum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** ปพ.1 (ป / บ / พ) + ทะเบียนคุมแบบพิมพ์ + ปพ.3 อนุมัติการจบ */
class GraduationDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    /** เปิดรายวิชาหนึ่งวิชาให้ห้อง แล้วให้คะแนนรวมตามที่กำหนด (เต็ม 100) */
    private function course(Classroom $room, Term $term, array $subject, array $scores): Course
    {
        $s = Subject::create($subject + ['group' => $subject['type'] === 'activity' ? 'กิจกรรมพัฒนาผู้เรียน' : 'คณิตศาสตร์']);
        $c = Course::create(['term_id' => $term->id, 'classroom_id' => $room->id, 'subject_id' => $s->id]);
        $a = Assessment::create(['course_id' => $c->id, 'name' => 'รวม', 'max_score' => 100, 'sort' => 1]);
        foreach ($scores as $studentId => $score) {
            Score::create(['assessment_id' => $a->id, 'student_id' => $studentId, 'score' => $score]);
        }

        return $c;
    }

    private function student(Classroom $room, string $code, int $no): Student
    {
        return Student::create(['student_code' => $code, 'prefix' => 'เด็กชาย', 'first_name' => 'ทดสอบ'.$no, 'last_name' => 'จบดี', 'gender' => 'M', 'classroom_id' => $room->id, 'number' => $no, 'citizen_id' => '110000000000'.$no]);
    }

    public function test_stage_mapping(): void
    {
        $this->assertSame('p', Curriculum::stageOf('ป.4'));
        $this->assertSame('b', Curriculum::stageOf('ม.2'));
        $this->assertSame('w', Curriculum::stageOf('ม.6'));
        $this->assertNull(Curriculum::stageOf('อ.2'));
        $this->assertSame('w', Curriculum::stageOfFinal('ม.6'));
    }

    public function test_secondary_transcript_shows_full_pp1_content(): void
    {
        $student = Student::whereHas('classroom', fn ($q) => $q->where('level', 'ม.1')->where('room', 1))->orderByDesc('number')->first();

        $res = $this->actingAs($this->admin())->get("/transcript/{$student->id}")->assertOk()
            ->assertSee('ปพ.1 : บ')->assertSee('ระดับมัธยมศึกษาตอนต้น')->assertSee('ภาคเรียนที่ 2')
            ->assertSee('กลุ่มสาระการเรียนรู้')->assertSee('ชุมนุม/ชมรม')->assertSee('ผลการประเมินคุณลักษณะอันพึงประสงค์')
            ->assertSee('โรงเรียนบ้านหนองบัว')->assertSee('ชุดที่ ..........');
        $this->assertSame('b', $res->viewData('stage'));
        // กิจกรรมทุกประเภทผ่าน (คนนี้ซ่อม มผ → ผ แล้ว)
        $this->assertTrue($res->viewData('record')->activitySummary()->every(fn ($a) => $a['result'] === 'ผ'));
    }

    public function test_primary_transcript_uses_yearly_hours_and_time_weighted_gpa(): void
    {
        $term = Term::where('year', 2568)->first();
        $room = Classroom::create(['year' => 2568, 'level' => 'ป.4', 'room' => 1]);
        $kid = $this->student($room, 'P001', 1);
        // ไทย 200 ชม. ได้ 4 · ศิลปะ 40 ชม. ได้ 1 → (4×5 + 1×1) / 6 = 3.5
        $this->course($room, $term, ['code' => 'ท14101', 'name' => 'ภาษาไทย', 'credit' => 0, 'hours' => 200, 'type' => 'basic'], [$kid->id => 90]);
        $this->course($room, $term, ['code' => 'ศ14101', 'name' => 'ศิลปะ', 'credit' => 0, 'hours' => 40, 'type' => 'basic'], [$kid->id => 52]);

        $record = new AcademicRecord($kid, 'p');
        $this->assertSame(3.5, $record->gpax());
        $this->assertSame(240, $record->totals()['total']['earned']);
        $this->assertSame('p', AcademicRecord::defaultStage($kid->fresh('classroom')));

        $this->actingAs($this->admin())->get("/transcript/{$kid->id}")->assertOk()
            ->assertSee('ปพ.1 : ป')->assertSee('ปีการศึกษา 2568 ชั้น ป.4')->assertSee('เวลาเรียน (ชม.)')->assertSee('3.50');
    }

    public function test_pp1_form_numbers_are_registered_once(): void
    {
        $student = Student::first();
        $teacher = User::where('username', 'teacher')->first();
        $this->actingAs($this->admin())->post("/students/{$student->id}/transcript-issue", ['stage' => 'b', 'form_series' => 'A12', 'form_number' => '0005'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($this->admin())->post("/students/{$student->id}/transcript-issue", ['stage' => 'b', 'form_series' => 'A12', 'form_number' => '0005'])
            ->assertSessionHasErrors('form_number');
        $this->assertSame(1, DocumentIssue::where('type', 'pp1')->count());

        $this->actingAs($this->admin())->get("/transcript/{$student->id}?stage=b")->assertSee('ชุดที่ A12 เลขที่ 0005');
        $this->actingAs($this->admin())->get('/certificates')->assertSee('แบบพิมพ์ชุด A12 เลขที่ 0005')->assertSee('ปพ.1 ระเบียนแสดงผลการเรียน');
        $this->actingAs($teacher)->post("/students/{$student->id}/transcript-issue", ['stage' => 'b', 'form_series' => 'B', 'form_number' => '1'])->assertForbidden();
        // ครูเห็น ปพ.1 แต่ไม่เห็นฟอร์มทะเบียนคุม
        $this->actingAs($teacher)->get("/transcript/{$student->id}")->assertOk()->assertDontSee('บันทึกการออกฉบับจริง');
    }

    public function test_pp3_checks_criteria_and_approves_only_eligible(): void
    {
        $term = Term::where('year', 2568)->first();
        $room = Classroom::create(['year' => 2568, 'level' => 'ม.3', 'room' => 9]);
        $good = $this->student($room, 'G001', 1);
        $weak = $this->student($room, 'G002', 2);
        // หน่วยกิตครบเกณฑ์ ม.ต้น: พื้นฐาน 66 + เพิ่มเติม 11
        $this->course($room, $term, ['code' => 'X23101', 'name' => 'พื้นฐานรวม', 'credit' => 66, 'type' => 'basic'], [$good->id => 80, $weak->id => 80]);
        $this->course($room, $term, ['code' => 'X23201', 'name' => 'เพิ่มเติมรวม', 'credit' => 11, 'type' => 'extra'], [$good->id => 70, $weak->id => 30]);
        $this->course($room, $term, ['code' => 'X23901', 'name' => 'แนะแนว', 'credit' => 0, 'hours' => 20, 'type' => 'activity', 'activity_kind' => 'guidance'], [$good->id => 90, $weak->id => 90]);
        StudentEvaluation::create(['term_id' => $term->id, 'student_id' => $good->id, 'traits' => array_fill_keys(range(1, 8), 2), 'rtw' => 2]);

        $this->assertTrue((new AcademicRecord($good, 'b'))->eligible());
        $this->assertFalse((new AcademicRecord($weak, 'b'))->eligible());

        $this->actingAs($this->admin())->get('/graduates?level=ม.3&year=2568&approved_on=2026-03-31')->assertOk()
            ->assertSee('(ปพ.3)')->assertSee('จบการศึกษาภาคบังคับ')->assertSee($good->fullName())
            ->assertSee('ยังไม่ผ่าน X23201 (0)')->assertSee('หน่วยกิตเพิ่มเติม 0/11');

        $this->actingAs($this->admin())->post('/graduates/approve', ['level' => 'ม.3', 'year' => 2568, 'approved_on' => '2026-03-31', 'student_ids' => [$good->id, $weak->id]])
            ->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'อนุมัติการจบ 1 คน') && str_contains($m, 'ข้าม 1 คน'));
        $this->assertSame(['graduated', '2026-03-31'], [$good->fresh()->status, $good->fresh()->left_on->toDateString()]);
        $this->assertSame('active', $weak->fresh()->status);

        $this->actingAs($this->admin())->get("/transcript/{$good->id}")->assertSee('จบการศึกษาภาคบังคับ')->assertSee('31 มีนาคม 2569');
        $csv = $this->actingAs($this->admin())->get('/graduates?level=ม.3&year=2568&export=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('ผลการเรียนเฉลี่ย', $csv);
        $this->assertStringContainsString('G001', $csv);

        $teacher = User::where('username', 'teacher')->first();
        $this->actingAs($teacher)->get('/graduates')->assertForbidden();
    }
}
