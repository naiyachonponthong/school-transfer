<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Grade;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeScaleSettingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_grade_scale_is_configurable_and_validated(): void
    {
        $this->assertSame('4', Grade::fromPercent(80));
        $this->assertSame('3.5', Grade::fromPercent(79.9));
        $this->assertNull(Grade::parseScale('80,75,70'));
        $this->assertNull(Grade::parseScale('80,85,70,65,60,55,50'));

        $admin = User::where('username', 'admin')->first();
        $base = ['school_name' => 'x', 'late_time' => '08:00', 'staff_late_time' => '08:00', 'periods_per_day' => 7, 'theme_color' => Settings::get('theme_color')];
        $this->actingAs($admin)->post(route('settings.update'), $base + ['grade_scale' => '90,80,x'])->assertSessionHasErrors('grade_scale');
        $this->post(route('settings.update'), $base + ['grade_scale' => '85,80,75,70,65,60,50'])->assertSessionHasNoErrors();

        $this->assertSame('3.5', Grade::fromPercent(84));
        $this->assertSame('4', Grade::fromPercent(85));
        $this->assertSame('0', Grade::fromPercent(49));
    }
}
