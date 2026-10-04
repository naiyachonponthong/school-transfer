<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/** ข้อความแจ้งเมื่อกรอกฟอร์มผิดต้องเป็นภาษาไทย แม้ controller ไม่ได้กำหนดข้อความเอง */
class ThaiMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_thai_validation_file_covers_every_framework_rule(): void
    {
        $en = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
        $th = require base_path('lang/th/validation.php');
        foreach ($en as $rule => $message) {
            if (in_array($rule, ['custom', 'attributes'], true)) {
                continue;
            }
            $this->assertArrayHasKey($rule, $th, "ยังไม่ได้แปลกฎ {$rule}");
            if (is_array($message)) {
                $this->assertSame(array_keys($message), array_keys($th[$rule]), "กฎ {$rule} แปลไม่ครบทุกแบบ");
            }
        }
    }

    public function test_default_messages_and_field_names_are_thai(): void
    {
        $errors = Validator::make(['email' => 'x', 'amount' => 'abc'], [
            'name' => ['required'], 'email' => ['email'], 'amount' => ['numeric'], 'photo' => ['required', 'image'],
        ])->errors();
        $this->assertSame('กรุณากรอกชื่อ', $errors->first('name'));
        $this->assertSame('อีเมล ต้องเป็นอีเมลที่ถูกต้อง', $errors->first('email'));
        $this->assertSame('จำนวนเงิน ต้องเป็นตัวเลข', $errors->first('amount'));

        // ผ่านหน้าเว็บจริง: ฟอร์มที่ไม่ได้กำหนดข้อความเอง
        $admin = User::where('username', 'admin')->first();
        $response = $this->actingAs($admin)->from(route('profile'))->put(route('profile.update'), ['name' => '', 'email' => 'not-an-email']);
        $response->assertSessionHasErrors(['name', 'email']);
        foreach (session('errors')->all() as $message) {
            $this->assertDoesNotMatchRegularExpression('/\bThe\b|field|must be/', $message, $message);
        }
    }
}
