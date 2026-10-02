<?php

namespace App\Http\Controllers;

use App\Support\Theme;
use Illuminate\Http\JsonResponse;

class ManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $iconVersion = (string) filemtime(public_path('assets/icons/icon-192.png'));
        $iconVersion512 = (string) filemtime(public_path('assets/icons/icon-512.png'));

        return response()->json([
            'id' => '/',
            'name' => school('school_name'),
            'short_name' => school('school_short') ?: school('school_name'),
            'description' => 'ระบบบริหารจัดการโรงเรียนสำหรับครู นักเรียน และผู้ปกครอง',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#F4F5F7',
            'theme_color' => Theme::color(),
            'lang' => 'th',
            'icons' => [
                ['src' => '/assets/icons/icon-192.png?v=' . $iconVersion, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => '/assets/icons/icon-512.png?v=' . $iconVersion512, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'no-store',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
