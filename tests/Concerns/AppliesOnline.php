<?php

namespace Tests\Concerns;

use App\Models\Admission;
use App\Support\AdmissionForm;
use Illuminate\Testing\TestResponse;

/** เดินฟอร์มสมัครเรียนทีละขั้นแบบผู้ปกครอง (ขั้นแรก → ทุกขั้น "ถัดไป" → ยืนยันส่ง) */
trait AppliesOnline
{
    protected function applicantData(array $extra = []): array
    {
        return array_merge([
            'level' => 'ม.4', 'prefix' => 'นางสาว', 'first_name' => 'ฟอร์ม', 'last_name' => 'ทดสอบ', 'gender' => 'F',
            'birthdate' => '2011-05-01', 'citizen_id' => '1100000'.random_int(100000, 999999), 'parent_name' => 'นางแม่ ทดสอบ',
            'parent_phone' => '0891112222', 'consent' => 1,
        ], $extra);
    }

    /** ขั้นแรก: สร้างร่าง/เปิดร่างเดิม */
    protected function beginApply(array $data): TestResponse
    {
        auth()->logout();

        return $this->post('/apply/start', array_intersect_key($data, array_flip(['level', 'citizen_id', 'birthdate', 'parent_phone', 'consent', 'website'])));
    }

    /**
     * สมัครครบทุกขั้นแล้วส่ง · คืนใบสมัคร (fresh) · ถ้าขั้นใดไม่ผ่าน คืน TestResponse ของขั้นนั้น
     */
    protected function applyOnline(array $data, bool $submit = true): Admission|TestResponse
    {
        $this->beginApply($data)->assertRedirect();
        $a = Admission::where('citizen_id', $data['citizen_id'])->latest('id')->firstOrFail();
        foreach (AdmissionForm::steps($data['level']) as $step) {
            $res = $this->post("/apply/form/{$step}", $data + ['nav' => 'next']);
            if (session('errors')?->any()) {
                return $res;
            }
        }
        if ($submit) {
            $res = $this->post('/apply/submit', ['confirm' => 1]);
            if (session('errors')?->any() || $res->status() !== 302) {
                return $res;
            }
        }

        return $a->fresh();
    }
}
