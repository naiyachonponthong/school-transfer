<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Installer;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * อัปเดตหลังอัปโหลดไฟล์ชุดใหม่ทับ: ผู้ดูแลระบบกดปุ่มเดียว ระบบสำรองฐานข้อมูล (backup:database) แล้ว migrate
 */
class UpdateController extends Controller
{
    public function index(Request $request)
    {
        if (! Installer::needsUpdate()) {
            return redirect()->route('home');
        }
        $user = $request->user();

        return view('install.update', [
            'from' => Installer::installedVersion(), 'to' => Installer::codeVersion(), 'step' => 0,
            'pending' => Installer::pendingMigrations(), 'user' => $user, 'canUpdate' => $user?->isAdmin(),
        ]);
    }

    public function run(Request $request)
    {
        abort_unless(Installer::needsUpdate(), 404);
        abort_unless($request->user()?->isAdmin(), 403, 'เฉพาะผู้ดูแลระบบเท่านั้นที่อัปเดตระบบได้');
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $from = Installer::installedVersion();
        $backup = null;
        try {
            if ($request->boolean('backup', true)) {
                $code = Artisan::call('backup:database', ['--keep-days' => 3650]);
                $out = Artisan::output(); // fetch() ล้างบัฟเฟอร์ อ่านได้ครั้งเดียว
                if ($code !== 0) {
                    return back()->withErrors(['update' => 'สำรองฐานข้อมูลไม่สำเร็จ จึงยังไม่อัปเดต: '.trim($out).' (ถ้าต้องการอัปเดตโดยไม่สำรอง ให้เอาเครื่องหมายช่องสำรองข้อมูลออก)']);
                }
                $backup = preg_match('/สำรองข้อมูลแล้ว: (.+?) \(\d/u', $out, $m) ? basename(str_replace('\\', '/', $m[1])) : 'เรียบร้อย';
            }
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['update' => 'อัปเดตไม่สำเร็จ: '.$e->getMessage().($backup ? ' (สำรองข้อมูลไว้แล้วที่ '.$backup.')' : '')]);
        }
        foreach (['view:clear', 'config:clear', 'route:clear'] as $cmd) {
            try {
                Artisan::call($cmd);
            } catch (\Throwable) {
            }
        }
        Settings::flush();
        Installer::writeLock(Installer::codeVersion(), ['previous' => $from]);
        Audit::log('system.update', null, 'อัปเดตระบบจาก '.$from.' เป็น '.Installer::codeVersion().($backup ? ' สำรองข้อมูล '.$backup : ''));

        return redirect()->route('home')->with('success', 'อัปเดตเป็นเวอร์ชัน '.Installer::codeVersion().' แล้ว'.($backup ? ' (ไฟล์สำรองอยู่ที่เมนู สำรองข้อมูล)' : ''));
    }
}
