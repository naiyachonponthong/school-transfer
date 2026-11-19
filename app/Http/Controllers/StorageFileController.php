<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

/**
 * ทางสำรองเมื่อโฮสต์สร้างทางลัด public/storage ไม่ได้: ส่งไฟล์รูปจาก storage/app/public ผ่าน PHP
 * (ถ้ามีทางลัดอยู่แล้ว เว็บเซิร์ฟเวอร์ส่งไฟล์ให้เองโดยไม่ผ่านตรงนี้)
 */
class StorageFileController extends Controller
{
    public function __invoke(string $path)
    {
        $root = realpath(Storage::disk('public')->path(''));
        $file = $root ? realpath($root.DIRECTORY_SEPARATOR.$path) : false;
        abort_unless($file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file), 404);
        abort_if(str_starts_with(basename($file), '.'), 404);

        return response()->file($file, ['Cache-Control' => 'public, max-age=604800']);
    }
}
