<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Artwork;
use App\Support\Settings;
use App\Support\SettingsMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** หน้าตั้งค่าแบบหมวด รูปภาพระบบ และเครดิตท้ายหน้า */
class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_every_section_renders(): void
    {
        $admin = $this->admin();
        foreach (array_keys(SettingsMenu::sections($admin)) as $key) {
            $url = $key === 'license' ? route('settings.license') : route('settings', ['s' => $key]);
            $this->actingAs($admin)->get($url)->assertOk()->assertSee(SettingsMenu::SECTIONS[$key][0], false);
        }
        $this->actingAs($admin)->get('/settings?s=nope')->assertOk()->assertSee('ข้อมูลโรงเรียน');
    }

    public function test_every_section_belongs_to_one_group(): void
    {
        $grouped = collect(SettingsMenu::GROUPS)->flatMap(fn ($g) => $g[3])->all();
        $this->assertEqualsCanonicalizing(array_keys(SettingsMenu::SECTIONS), $grouped);
        $this->assertSame(count($grouped), count(array_unique($grouped)));
    }

    public function test_section_form_only_touches_its_own_fields(): void
    {
        Settings::set(['line_notify_gate' => '1', 'gps_required' => '1']);

        // ฟอร์มเวลาเรียนไม่มีสวิตช์ LINE / GPS ค่าเดิมต้องไม่ถูกล้าง
        $this->actingAs($this->admin())->post(route('settings.update'), [
            'section' => 'schedule', 'late_time' => '08:15', 'staff_late_time' => '07:50', 'periods_per_day' => 8,
            'grade_scale' => '80,75,70,65,60,55,50', 'school_days' => 5, 'period_times' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('08:15', Settings::get('late_time'));
        $this->assertSame('1', Settings::get('line_notify_gate'));
        $this->assertSame('1', Settings::get('gps_required'));

        // ฟอร์ม GPS ปิดสวิตช์ได้ โดยไม่ต้องส่งชื่อโรงเรียน
        $this->post(route('settings.update'), ['section' => 'gate', 'gps_radius' => 200])->assertSessionHasNoErrors();
        $this->assertSame('0', Settings::get('gps_required'));
        $this->assertSame('1', Settings::get('line_notify_gate'));
    }

    public function test_brand_credit_shows_in_footer_only_when_set(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/settings')->assertDontSee('ดูแลระบบโดยงานไอที');

        $this->post(route('settings.update'), ['section' => 'appearance', 'theme_color' => '#1E88E5', 'brand_credit' => 'ดูแลระบบโดยงานไอที'])->assertSessionHasNoErrors();
        $this->get('/settings')->assertSee('ดูแลระบบโดยงานไอที');
    }

    public function test_upload_and_reset_system_image(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $this->assertStringContainsString('school-award.png', Artwork::url('settings'));

        $this->actingAs($admin)->post(route('settings.images'), ['settings' => UploadedFile::fake()->image('hero.png', 2400, 1200)])->assertSessionHas('success');
        $path = Settings::get('artwork_settings');
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString($path, Artwork::url('settings'));
        $this->assertLessThanOrEqual(1600, getimagesize(Storage::disk('public')->path($path))[0]); // ย่อแล้ว

        $this->post(route('settings.images'), ['reset_settings' => 1])->assertSessionHas('success');
        Storage::disk('public')->assertMissing($path);
        $this->assertStringContainsString('school-award.png', Artwork::url('settings'));
    }

    public function test_system_image_rejects_non_images(): void
    {
        $this->actingAs($this->admin())->post(route('settings.images'), ['login' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('login');
    }

    public function test_teacher_cannot_open_settings(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'teacher']))->get('/settings')->assertForbidden();
    }
}
