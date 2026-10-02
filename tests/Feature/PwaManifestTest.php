<?php

namespace Tests\Feature;

use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_uses_the_current_school_identity_and_installable_icons(): void
    {
        Settings::set([
            'school_name' => 'โรงเรียนทดสอบ',
            'school_short' => 'ทดสอบ',
            'theme_color' => '#DB2777',
        ]);

        $this->get('/app.webmanifest')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('name', 'โรงเรียนทดสอบ')
            ->assertJsonPath('short_name', 'ทดสอบ')
            ->assertJsonPath('theme_color', '#DB2777')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('icons.0.sizes', '192x192')
            ->assertJsonPath('icons.1.sizes', '512x512');
    }
}
