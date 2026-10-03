<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Attendance;
use App\Models\BehaviorRecord;
use App\Models\CareCase;
use App\Models\Classroom;
use App\Models\ConsentForm;
use App\Models\ConsentResponse;
use App\Models\HomeVisit;
use App\Models\Role;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\RiskScan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentCareTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function teacher(): User
    {
        return User::where('username', 'teacher')->first();
    }

    private function room(): Classroom
    {
        return Classroom::where('homeroom_teacher_id', $this->teacher()->id)->first();
    }

    private function otherRoom(): Classroom
    {
        return Classroom::currentYear()->whereNotIn('id', $this->teacher()->myClassrooms()->pluck('id'))->whereHas('students')->first();
    }

    /* ---------------- สัญญาณเตือน ---------------- */

    public function test_risk_scan_flags_absence_streak_and_low_behavior(): void
    {
        $room = $this->room();
        [$absent, $naughty, $fine] = $room->students()->limit(3)->get()->all();
        Attendance::whereIn('student_id', [$absent->id, $fine->id])->delete();
        BehaviorRecord::whereIn('student_id', [$naughty->id, $fine->id, $absent->id])->delete();
        foreach (range(1, 3) as $i) {
            Attendance::create(['student_id' => $absent->id, 'classroom_id' => $room->id, 'date' => today()->subDays($i)->toDateString(), 'status' => 'absent']);
            Attendance::create(['student_id' => $fine->id, 'classroom_id' => $room->id, 'date' => today()->subDays($i)->toDateString(), 'status' => $i === 1 ? 'present' : 'absent']);
        }
        BehaviorRecord::create(['student_id' => $naughty->id, 'title' => 'ทะเลาะวิวาท', 'points' => -40, 'date' => today()]);

        $risks = RiskScan::forClassrooms([$room->id])->keyBy(fn ($r) => $r['student']->id);

        $this->assertArrayHasKey('absent', $risks[$absent->id]['signals']);
        $this->assertArrayHasKey('behavior', $risks[$naughty->id]['signals']);
        // มาเรียนวันล่าสุดแล้ว ไม่นับว่าขาดติดกัน
        $this->assertArrayNotHasKey('absent', $risks[$fine->id]['signals'] ?? []);

        $this->actingAs($this->teacher())->get(route('care.index'))->assertOk()->assertSee($absent->fullName())->assertSee('ขาดเรียนติดกัน')->assertSee('เปิดกรณี');
    }

    /* ---------------- กรณีช่วยเหลือ ---------------- */

    public function test_homeroom_teacher_opens_case_logs_actions_and_others_cannot_see_it(): void
    {
        $teacher = $this->teacher();
        $student = $this->room()->students()->first();
        $outsider = Student::where('classroom_id', $this->otherRoom()->id)->first();

        $this->actingAs($teacher)->get(route('care.create', ['student' => $student->id]))->assertOk();
        $this->get(route('care.create', ['student' => $outsider->id]))->assertForbidden();
        $this->post(route('care.store'), ['student_id' => $outsider->id, 'category' => 'family', 'level' => 'risk', 'title' => 'x'])->assertForbidden();

        $this->post(route('care.store'), ['student_id' => $student->id, 'category' => 'family', 'level' => 'problem', 'title' => 'ผู้ปกครองย้ายไปทำงานต่างจังหวัด', 'detail' => 'อยู่กับยาย รายได้น้อย'])
            ->assertRedirect();
        $case = CareCase::first();
        $this->assertSame($teacher->id, $case->owner_id);

        $this->get(route('care.show', $case))->assertOk()->assertSee('อยู่กับยาย');
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.care_view', 'subject_type' => 'CareCase', 'subject_id' => $case->id, 'user_id' => $teacher->id]);
        // รายละเอียดของกรณีไม่ถูกคัดลอกลงประวัติการใช้งาน
        $this->assertStringNotContainsString('อยู่กับยาย', AuditLog::where('subject_type', 'CareCase')->get()->toJson(JSON_UNESCAPED_UNICODE));

        $this->post(route('care.actions.store', $case), ['date' => today()->toDateString(), 'action' => 'โทรคุยกับยาย', 'result' => 'รับทราบ', 'follow_up_on' => today()->addDays(7)->toDateString()])->assertSessionHasNoErrors();
        $this->put(route('care.update', $case), ['status' => 'closed', 'level' => 'risk'])->assertRedirect();
        $this->assertNotNull($case->fresh()->closed_at);
        $this->get(route('care.index', ['status' => 'closed']))->assertOk()->assertSee('ผู้ปกครองย้ายไปทำงานต่างจังหวัด');

        // ครูห้องอื่นไม่เห็น · ผู้มีสิทธิ์ care.manage เห็น · ผู้ปกครองเข้าไม่ได้
        $other = User::where('role', 'teacher')->where('id', '!=', $teacher->id)->whereDoesntHave('homerooms', fn ($q) => $q->whereKey($student->classroom_id))->first();
        $this->actingAs($other)->get(route('care.show', $case))->assertForbidden();
        $this->get(route('care.index', ['status' => 'all']))->assertOk()->assertDontSee('ผู้ปกครองย้ายไปทำงานต่างจังหวัด');
        $other->roles()->sync(Role::where('key', 'executive')->pluck('id'));
        $this->actingAs($other->fresh())->get(route('care.show', $case))->assertOk();
        $this->actingAs(User::where('phone', '0812345678')->first())->get(route('care.index'))->assertForbidden();
    }

    /* ---------------- เยี่ยมบ้าน ---------------- */

    public function test_home_visit_is_saved_once_per_term_with_private_photo(): void
    {
        Storage::fake('local');
        $teacher = $this->teacher();
        $room = $this->room();
        $student = $room->students()->first();
        $outsider = Student::where('classroom_id', $this->otherRoom()->id)->first();

        $this->actingAs($teacher)->get(route('care.visits'))->assertOk()->assertSee($student->fullName());
        $this->get(route('care.visits.form', $student))->assertOk();
        $this->get(route('care.visits.form', $outsider))->assertForbidden();

        $payload = ['visited_on' => today()->toDateString(), 'guardian_met' => 'มารดา', 'housing' => 'rent', 'family_status' => 'together', 'risks' => ['economic', 'travel'], 'note' => 'บ้านไกล'];
        $this->post(route('care.visits.save', $student), $payload + ['photo' => UploadedFile::fake()->image('home.jpg')])->assertSessionHasNoErrors();
        $this->post(route('care.visits.save', $student), ['guardian_met' => 'บิดา'] + $payload)->assertSessionHasNoErrors();

        $this->assertSame(1, HomeVisit::where('student_id', $student->id)->where('term_id', Term::current()->id)->count());
        $visit = HomeVisit::first();
        $this->assertSame('บิดา', $visit->guardian_met);
        $this->assertSame(['economic', 'travel'], $visit->risks);
        Storage::disk('local')->assertExists($visit->photo);
        $this->get(route('care.visits'))->assertOk()->assertSee('เศรษฐกิจ/รายได้');

        // รูปเยี่ยมบ้าน: ครูประจำชั้นเปิดได้ ผู้ปกครองของเด็กเองก็เปิดไม่ได้
        $this->get(route('files.show', ['home-visit', $visit->id]))->assertOk();
        $guardian = $student->guardians()->first();
        if ($guardian) {
            $this->actingAs($guardian)->get(route('files.show', ['home-visit', $visit->id]))->assertForbidden();
        }
    }

    /* ---------------- หนังสือขออนุญาต ---------------- */

    public function test_consent_form_flow_between_teacher_and_parent(): void
    {
        $teacher = $this->teacher();
        $parent = User::where('phone', '0812345678')->first();
        $child = $parent->children()->whereIn('classroom_id', $teacher->myClassrooms()->pluck('id'))->first();
        $this->assertNotNull($child, 'ข้อมูลตัวอย่าง: ลูกของผู้ปกครองตัวอย่างต้องอยู่ห้องของครูตัวอย่าง');
        $other = $this->otherRoom();

        $this->actingAs($teacher)->get(route('consents.index'))->assertOk();
        $this->post(route('consents.store'), ['title' => 'ทัศนศึกษา', 'body' => 'ไปสวนสัตว์', 'classroom_ids' => [$other->id]])->assertSessionHasErrors();
        $this->post(route('consents.store'), ['title' => 'ทัศนศึกษา', 'body' => 'ไปสวนสัตว์ วันศุกร์', 'classroom_ids' => [$child->classroom_id], 'due_date' => today()->addDays(3)->toDateString()])->assertRedirect();
        $form = ConsentForm::first();
        $this->get(route('consents.show', $form))->assertOk()->assertSee($child->fullName())->assertSee('ยังไม่ตอบ');

        // ผู้ปกครองตอบ แก้คำตอบได้ และตอบแทนเด็กคนอื่นไม่ได้
        $this->actingAs($parent)->get(route('parent.consents'))->assertOk()->assertSee('ไปสวนสัตว์ วันศุกร์');
        $this->post(route('parent.consents.respond', $form), ['student_id' => $child->id, 'agreed' => 1])->assertSessionHasNoErrors();
        $this->post(route('parent.consents.respond', $form), ['student_id' => $child->id, 'agreed' => 0, 'note' => 'ติดธุระ'])->assertSessionHasNoErrors();
        $this->assertSame(1, ConsentResponse::where('student_id', $child->id)->count());
        $this->assertFalse(ConsentResponse::first()->agreed);
        $stranger = Student::where('classroom_id', $child->classroom_id)->whereDoesntHave('guardians', fn ($q) => $q->whereKey($parent->id))->first();
        $this->post(route('parent.consents.respond', $form), ['student_id' => $stranger->id, 'agreed' => 1])->assertSessionHasErrors('student_id');

        // ครูบันทึกแทน แล้วปิดรับ ผู้ปกครองแก้ไม่ได้อีก
        $this->actingAs($teacher)->post(route('consents.record', $form), ['student_id' => $stranger->id, 'agreed' => 1])->assertRedirect();
        $this->assertTrue(ConsentResponse::where('student_id', $stranger->id)->first()->agreed);
        $this->post(route('consents.close', $form))->assertRedirect();
        $this->actingAs($parent)->post(route('parent.consents.respond', $form), ['student_id' => $child->id, 'agreed' => 1])->assertStatus(422);

        // ครูที่ไม่เกี่ยวข้องเปิดดูไม่ได้
        $outsider = User::where('role', 'teacher')->where('id', '!=', $teacher->id)->get()
            ->first(fn ($u) => ! $u->myClassrooms()->contains('id', $child->classroom_id));
        $this->actingAs($outsider)->get(route('consents.show', $form))->assertForbidden();
    }
}
