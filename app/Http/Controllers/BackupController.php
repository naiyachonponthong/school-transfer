<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/** หน้าสำรองข้อมูล (ผู้ดูแลระบบ): ดูรายการ · ดาวน์โหลดไปเก็บนอกเครื่อง · สำรองทันที — การกู้คืนทำผ่านคำสั่งเท่านั้น */
class BackupController extends Controller
{
    private const EXTENSIONS = ['sql' => 'ฐานข้อมูล', 'sqlite' => 'ฐานข้อมูล', 'zip' => 'ไฟล์อัปโหลด'];

    public function index()
    {
        $files = collect(array_keys(self::EXTENSIONS))
            ->flatMap(fn ($ext) => File::glob(self::dir().'/*.'.$ext))
            ->map(fn ($f) => ['name' => basename($f), 'type' => self::EXTENSIONS[pathinfo($f, PATHINFO_EXTENSION)], 'size' => File::size($f), 'time' => File::lastModified($f)])
            ->sortByDesc('time')->values();

        return view('backups.index', ['files' => $files]);
    }

    public function run()
    {
        $results = [];
        foreach (['backup:database' => 'ฐานข้อมูล', 'backup:files' => 'ไฟล์อัปโหลด'] as $command => $label) {
            $ok = Artisan::call($command) === 0;
            $results[] = ($ok ? '✓ ' : '✗ ').$label.': '.trim(strtok(Artisan::output(), "\n"));
        }
        Audit::log('setting.backup', null, 'สำรองข้อมูลทันทีจากหน้าเว็บ');

        return back()->with(str_contains(implode('', $results), '✗') ? 'warning' : 'success', implode(' · ', $results));
    }

    public function download(string $name)
    {
        $path = self::dir().'/'.basename($name);
        abort_unless(isset(self::EXTENSIONS[pathinfo($path, PATHINFO_EXTENSION)]) && File::exists($path), 404);
        Audit::log('setting.backup_download', null, 'ดาวน์โหลดไฟล์สำรอง '.basename($path));

        return response()->download($path);
    }

    private static function dir(): string
    {
        return storage_path('app/backups');
    }
}
