<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\User;
use App\Support\AdmissionForm;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\AppliesOnline;
use Tests\TestCase;

class AdmissionFormBuilderTest extends TestCase
{
    use AppliesOnline;
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    /** บันทึกฟอร์มผ่านหน้าตั้งค่า (เหมือนที่ JS ส่ง) */
    private function saveForm(array $config, array $extra = []): TestResponse
    {
        return $this->actingAs($this->admin())->put('/admissions/form', array_merge([
            'admission_open' => 1, 'admission_levels' => 'ม.1,ม.4', 'config' => json_encode($config),
        ], $extra));
    }

    private function config(array $questions, array $more = []): array
    {
        return array_merge(AdmissionForm::defaults(), ['questions' => $questions], $more);
    }

    /* ---------------- ตั้งค่าฟอร์ม ---------------- */

    public function test_builder_page_is_admin_only_and_renders(): void
    {
        $this->actingAs($this->admin())->get('/admissions/form')->assertOk()->assertSee('คำถามของโรงเรียน')->assertSee('ค่าสมัคร');
        $teacher = User::where('username', 'teacher')->first();
        $this->actingAs($teacher)->get('/admissions/form')->assertForbidden();
        $this->actingAs($teacher)->put('/admissions/form', ['config' => '{}', 'admission_levels' => 'ม.1'])->assertForbidden();
    }

    public function test_save_normalizes_questions(): void
    {
        $this->saveForm($this->config([
            ['id' => 'q_plan', 'type' => 'radio', 'label' => ' แผนการเรียน ', 'options' => ['วิทย์-คณิต', '', 'ศิลป์-ภาษา', 'วิทย์-คณิต'], 'required' => true, 'levels' => ['ม.4'], 'step' => 'education'],
            ['id' => 'q_plan', 'type' => 'text', 'label' => 'ซ้ำ id', 'step' => 'nowhere'],   // id ซ้ำ → สุ่มใหม่ · ขั้นไม่รู้จัก → extra
            ['type' => 'select', 'label' => 'ไม่มีตัวเลือก', 'options' => []],               // ตัดทิ้ง
            ['type' => 'file', 'label' => 'รูป 1', 'photo' => true],
            ['type' => 'file', 'label' => 'รูป 2', 'photo' => true],                         // รูปในเอกสารมีได้ข้อเดียว
            ['type' => 'text', 'label' => '   '],                                              // ไม่มีข้อความ → ตัดทิ้ง
        ], ['caps' => ['ม.1' => 2, 'ม.4' => 0], 'fees' => ['ม.4' => '150', 'ม.1' => 0], 'optional' => ['gpa' => 'required', 'address' => 'hidden', 'note' => 'weird']]))
            ->assertRedirect(route('admissions.form'));

        $c = AdmissionForm::config();
        $this->assertCount(4, $c['questions']);
        $this->assertSame(['id' => 'q_plan', 'type' => 'radio', 'label' => 'แผนการเรียน', 'help' => '', 'required' => true, 'options' => ['วิทย์-คณิต', 'ศิลป์-ภาษา'], 'levels' => ['ม.4'], 'step' => 'education', 'photo' => false], $c['questions'][0]);
        $this->assertNotSame('q_plan', $c['questions'][1]['id']);
        $this->assertSame('extra', $c['questions'][1]['step']);
        $this->assertSame([true, false], [$c['questions'][2]['photo'], $c['questions'][3]['photo']]);
        $this->assertSame('documents', $c['questions'][2]['step']);
        $this->assertSame(['ม.1' => 2], $c['caps']);
        $this->assertSame(['ม.4' => 150.0], $c['fees']);
        $this->assertSame(['required', 'hidden', 'show'], [$c['optional']['gpa'], $c['optional']['address'], $c['optional']['note']]);
    }

    public function test_settings_page_links_to_builder_and_does_not_close_admissions(): void
    {
        Settings::set(['admission_open' => '1']);
        $this->actingAs($this->admin())->get('/settings')->assertSee(route('admissions.form'));
        $this->assertTrue(AdmissionForm::isOpen());
    }

    /* ---------------- สมัครทีละขั้น + กลับมากรอกต่อ ---------------- */

    public function test_wizard_creates_draft_and_resumes_with_same_identity(): void
    {
        $data = $this->applicantData(['level' => 'ม.1', 'prefix' => 'เด็กหญิง']);
        $this->beginApply($data)->assertRedirect(route('apply.step', 'student'));
        $a = Admission::where('citizen_id', $data['citizen_id'])->first();
        $this->assertSame('draft', $a->status);
        $this->assertNotNull($a->app_no);

        // ขั้นแรกกด "ถัดไป" โดยไม่กรอก → ต้องกรอกช่องบังคับ · กด "บันทึกไว้ก่อน" ได้แม้ยังไม่ครบ
        $this->post('/apply/form/student', ['nav' => 'next', 'level' => 'ม.1'])->assertSessionHasErrors(['first_name', 'gender']);
        $this->post('/apply/form/student', ['nav' => 'save', 'level' => 'ม.1', 'first_name' => 'ครึ่งทาง'])->assertSessionHasNoErrors();
        $this->assertSame('ครึ่งทาง', $a->fresh()->first_name);

        // ออก แล้วกลับมาด้วยข้อมูลเดิม → เปิดร่างเดิม ไปขั้นที่ยังไม่ผ่าน
        $this->post('/apply/leave');
        $this->get('/apply/form/student')->assertRedirect(route('apply'));
        $this->beginApply($data)->assertRedirect(route('apply.step', 'student'));
        $this->assertSame(1, Admission::where('citizen_id', $data['citizen_id'])->count());
        $this->get('/apply/form/student')->assertOk()->assertSee('ครึ่งทาง');

        // วันเกิด/เบอร์ไม่ตรง → เปิดไม่ได้
        $this->beginApply(['birthdate' => '2000-01-01'] + $data)->assertSessionHasErrors('citizen_id');

        // ส่งก่อนครบ → พากลับไปขั้นที่ขาด
        $this->beginApply($data);
        $this->post('/apply/submit', ['confirm' => 1])->assertRedirect(route('apply.step', 'student'));
        $this->assertSame('draft', $a->fresh()->status);

        // ร่างไม่อยู่ในรายการ "ทั้งหมด" ของเจ้าหน้าที่ (ดูแยกได้)
        $this->actingAs($this->admin())->get('/admissions')->assertDontSee($a->app_no);
        $this->actingAs($this->admin())->get('/admissions?status=draft')->assertSee($a->app_no);
    }

    public function test_full_wizard_submit_and_edit_blocked_after(): void
    {
        $a = $this->applyOnline($this->applicantData(['address' => 'ขอนแก่น', 'previous_school' => 'บ้านหนองแวง']));
        $this->assertInstanceOf(Admission::class, $a);
        $this->assertSame(['submitted', 'none'], [$a->status, $a->fee_status]);
        $this->assertNotNull($a->submitted_at);
        $this->assertSame('ขอนแก่น', $a->address);
        $this->post('/apply/form/student', ['nav' => 'next', 'first_name' => 'แก้'])->assertStatus(422);
        $this->get('/apply/status')->assertOk()->assertSee($a->app_no)->assertSee('พิมพ์ใบสมัคร');
    }

    public function test_questions_by_step_level_and_files_kept_between_visits(): void
    {
        Storage::fake('local');
        $this->saveForm($this->config([
            ['id' => 'q_plan', 'type' => 'radio', 'label' => 'แผนการเรียน', 'options' => ['วิทย์-คณิต', 'ศิลป์-ภาษา'], 'required' => true, 'levels' => ['ม.4'], 'step' => 'education'],
            ['id' => 'q_photo', 'type' => 'file', 'label' => 'รูปถ่าย', 'required' => true, 'photo' => true],
            ['id' => 'q_news', 'type' => 'checkbox', 'label' => 'ทราบข่าวจาก', 'options' => ['เพจ', 'เพื่อน', 'ครู']],
        ]));
        $data = $this->applicantData();
        $this->beginApply($data);
        $this->assertSame(['student', 'education', 'family', 'extra', 'documents'], AdmissionForm::steps('ม.4'));
        $this->get('/apply/form/education')->assertSee('แผนการเรียน');

        $this->post('/apply/form/education', ['nav' => 'next', 'answers' => ['q_plan' => 'แผนปลอม']])->assertSessionHasErrors('answers.q_plan');
        $this->post('/apply/form/education', ['nav' => 'next', 'answers' => ['q_plan' => 'ศิลป์-ภาษา']])->assertRedirect(route('apply.step', 'family'));
        $this->post('/apply/form/extra', ['nav' => 'next', 'answers' => ['q_news' => ['ครู', 'เพจ', 'แฮก']]])->assertSessionHasErrors('answers.q_news.2');
        $this->post('/apply/form/extra', ['nav' => 'next', 'answers' => ['q_news' => ['ครู', 'เพจ']]]);
        // รูปถ่ายในเอกสารต้องเป็นรูปภาพ
        $this->post('/apply/form/documents', ['nav' => 'next', 'answers' => ['q_photo' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])->assertSessionHasErrors('answers.q_photo');
        $this->post('/apply/form/documents', ['nav' => 'next', 'answers' => ['q_photo' => UploadedFile::fake()->image('me.jpg')]])->assertSessionHasNoErrors();

        $a = Admission::where('citizen_id', $data['citizen_id'])->first();
        $photo = $a->answer('q_photo');
        $this->assertTrue($photo['photo']);
        Storage::disk('local')->assertExists($photo['value']['path']);
        $this->assertSame(['เพจ', 'ครู'], $a->answer('q_news')['value']); // เรียงตามลำดับตัวเลือก

        // กลับมาขั้นเอกสารอีกรอบโดยไม่แนบใหม่ → ไฟล์เดิมยังอยู่ ไม่ต้องแนบซ้ำ
        $this->post('/apply/form/documents', ['nav' => 'next'])->assertSessionHasNoErrors();
        $this->assertSame($photo['value']['path'], $a->fresh()->answer('q_photo')['value']['path']);
        $this->get('/apply/files/q_photo')->assertOk();
        // ลบไฟล์ → คำถามบังคับจึงไม่ผ่าน
        $this->post('/apply/form/documents', ['nav' => 'next', 'remove' => ['q_photo' => 1]])->assertSessionHasErrors('answers.q_photo');

        // คำถามเฉพาะ ม.4 ไม่อยู่ในฟอร์ม ม.1
        $this->assertNotContains('q_plan', array_column(AdmissionForm::questionsFor('ม.1'), 'id'));
    }

    public function test_optional_fields_modes(): void
    {
        $this->saveForm($this->config([], ['optional' => ['gpa' => 'required', 'address' => 'hidden'] + AdmissionForm::defaults()['optional']]));
        $data = $this->applicantData();
        $this->beginApply($data);
        $this->get('/apply/form/family')->assertOk()->assertDontSee('name="address"', false);
        $this->post('/apply/form/education', ['nav' => 'next'])->assertSessionHasErrors('gpa');
        // ช่องที่ซ่อนไว้ ถ้าถูกส่งมาก็ไม่บันทึก
        $this->post('/apply/form/family', $data + ['nav' => 'next', 'address' => 'แอบส่ง'])->assertSessionHasNoErrors();
        $this->assertNull(Admission::where('citizen_id', $data['citizen_id'])->value('address'));
        $this->assertSame('submitted', $this->applyOnline($data + ['gpa' => 3.5])->status);
    }

    /* ---------------- การเปิดรับ ---------------- */

    public function test_open_window_and_caps(): void
    {
        $this->saveForm($this->config([], ['open_from' => today()->addDays(3)->toDateString()]));
        $this->assertFalse(AdmissionForm::isOpen());
        auth()->logout();
        $this->get('/apply')->assertSee('เปิดรับสมัครวันที่')->assertDontSee(route('apply.begin'));
        $this->beginApply($this->applicantData())->assertForbidden();
        $this->get('/login')->assertOk()->assertDontSee(route('apply'));

        $this->saveForm($this->config([], ['open_from' => '2026-10-10', 'open_until' => '2026-10-01']))->assertSessionHasErrors('config');

        // รับ ม.4 ได้อีก 1 ใบ (ข้อมูลตัวอย่างมีใบสมัคร ม.4 อยู่แล้ว · ร่างไม่นับ)
        $year = (\App\Models\Term::current()?->year ?? 0) + 1;
        $existing = Admission::where('year', $year)->where('level', 'ม.4')->whereNotIn('status', ['rejected', 'draft'])->count();
        $this->saveForm($this->config([], ['caps' => ['ม.4' => $existing + 1]]));
        $this->beginApply($this->applicantData());
        $this->assertSame([], AdmissionForm::fullLevels($year));
        $this->assertSame('submitted', $this->applyOnline($this->applicantData())->status);
        $this->get('/apply')->assertSee('เต็มแล้ว');
        $this->beginApply($this->applicantData())->assertSessionHasErrors('level');

        $this->saveForm($this->config([]), ['admission_open' => 0]);
        $this->assertSame('0', Settings::get('admission_open'));
        $this->beginApply($this->applicantData(['level' => 'ม.1']))->assertForbidden();
    }

    public function test_preview_mode_for_admin_even_when_closed(): void
    {
        $this->saveForm($this->config([['type' => 'text', 'label' => 'คำถามตัวอย่าง']]), ['admission_open' => 0]);
        auth()->logout();
        $this->get('/apply?preview=1')->assertDontSee('ตัวอย่างหน้าสมัคร');
        $this->actingAs($this->admin())->get('/apply?preview=1')->assertSee('ตัวอย่างหน้าสมัคร')->assertSee('ขั้นตอนการสมัคร');
    }

    /* ---------------- ค่าสมัคร + เอกสาร ---------------- */

    public function test_fee_slip_verification_and_receipt(): void
    {
        Storage::fake('local');
        $this->saveForm($this->config([], ['fees' => ['ม.4' => 200]]));
        $a = $this->applyOnline($this->applicantData());
        $this->assertSame(['unpaid', 200.0], [$a->fee_status, $a->fee_amount]);
        $this->get('/apply/status')->assertSee('ค่าสมัคร')->assertSee('แนบสลิป');
        $this->get('/apply/print/receipt')->assertNotFound();

        $this->post('/apply/slip', ['slip' => UploadedFile::fake()->image('slip.jpg')])->assertSessionHasNoErrors();
        $this->assertSame('pending', $a->fresh()->fee_status);

        // เจ้าหน้าที่ตีกลับ → ผู้สมัครเห็นเหตุผล → ส่งใหม่ → ยืนยัน → เลขใบเสร็จ
        $this->actingAs($this->admin())->get("/admissions/{$a->id}/files/slip")->assertOk();
        $this->actingAs($this->admin())->post("/admissions/{$a->id}/fee", ['action' => 'reject', 'fee_note' => 'ยอดไม่ตรง']);
        auth()->logout();
        $this->get('/apply/status')->assertSee('ยอดไม่ตรง');
        $this->post('/apply/slip', ['slip' => UploadedFile::fake()->image('slip2.jpg')]);
        $this->actingAs($this->admin())->post("/admissions/{$a->id}/fee", ['action' => 'approve'])->assertSessionHas('success');
        $a->refresh();
        $this->assertSame('paid', $a->fee_status);
        $this->assertMatchesRegularExpression('/^RA\d{4}-0001$/', $a->fee_receipt_no);

        $this->actingAs($this->admin())->get("/admissions/{$a->id}/print/receipt")->assertOk()->assertSee($a->fee_receipt_no)->assertSee('สองร้อยบาทถ้วน');
        auth()->logout();
        $this->get('/apply/print/receipt')->assertOk()->assertSee($a->fee_receipt_no);
    }

    public function test_documents_availability_and_access(): void
    {
        $a = $this->applyOnline($this->applicantData(['relation' => 'มารดา']));
        $this->get('/apply/print/application')->assertOk()->assertSee('ใบสมัครเข้าเรียน')->assertSee($a->app_no)->assertSee('ส่วนที่ 2');
        $this->get('/apply/print/enrollment')->assertNotFound(); // ยังไม่ผ่านการคัดเลือก

        $this->actingAs($this->admin())->put("/admissions/{$a->id}/exam", ['exam_room' => '321', 'exam_seat' => '015']);
        $this->actingAs($this->admin())->put("/admissions/{$a->id}", ['status' => 'accepted']);
        $this->actingAs($this->admin())->get("/admissions/{$a->id}/print/application")->assertSee('321')->assertSee('015');
        $this->actingAs($this->admin())->get("/admissions/{$a->id}/print/enrollment")->assertOk()->assertSee('ใบมอบตัวนักเรียน')->assertSee('นางแม่ ทดสอบ');
        $this->actingAs($this->admin())->get("/admissions/{$a->id}/print/bogus")->assertNotFound();

        // ไม่ได้ยืนยันตัว → เปิดเอกสารไม่ได้
        auth()->logout();
        $this->post('/apply/leave');
        $this->get('/apply/print/application')->assertRedirect(route('apply'));
        // ตรวจสถานะด้วยเลขที่ + เบอร์ → เปิดได้
        $this->get('/apply/status?app_no='.$a->app_no.'&phone='.$a->parent_phone)->assertSee('พิมพ์ใบมอบตัว');
        $this->get('/apply/print/enrollment')->assertOk();
    }

    public function test_export_includes_custom_answer_columns_and_proper_bom(): void
    {
        $this->saveForm($this->config([['id' => 'q_job', 'type' => 'text', 'label' => 'อาชีพผู้ปกครอง', 'step' => 'family']]));
        $a = $this->applyOnline($this->applicantData(['answers' => ['q_job' => 'เกษตรกร']]));
        $csv = $this->actingAs($this->admin())->get('/admissions/export?year='.$a->year)->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('อาชีพผู้ปกครอง', $csv);
        $this->assertStringContainsString('เกษตรกร', $csv);
        $this->assertStringContainsString($a->app_no, $csv);
    }
}
