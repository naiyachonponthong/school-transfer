<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\DbBackup;
use App\Support\Installer;
use App\Support\License\Client;
use App\Support\License\Guard;
use App\Support\License\Integrity;
use App\Support\SchoolSetup;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * ตัวติดตั้งแบบ WordPress: ตรวจเครื่อง > ฐานข้อมูล > โรงเรียนและผู้ดูแล > ติดตั้ง > เสร็จ
 * ทำงานได้ก่อนมีฐานข้อมูล (session / cache เป็นไฟล์ที่ preboot ตั้งไว้)
 */
class InstallController extends Controller
{
    protected ?string $doneUrl = null;

    /** ขั้นตอนหลังบันทึกฐานข้อมูล ทำได้เฉพาะเบราว์เซอร์ที่เริ่มติดตั้ง */
    protected function owns(Request $request): bool
    {
        return Installer::ownsInstall($request->session()->get('install.token'));
    }

    public const STEPS = [
        'prepare' => 'บันทึกไฟล์ตั้งค่าระบบ',
        'migrate' => 'สร้างตารางในฐานข้อมูล',
        'seed'    => 'เตรียมข้อมูลเริ่มต้น',
        'admin'   => 'ตั้งชื่อโรงเรียนและบัญชีผู้ดูแลระบบ',
        'license' => 'บันทึก License',
        'storage' => 'เตรียมโฟลเดอร์รูปภาพ',
        'finish'  => 'ปิดตัวติดตั้งและเปิดใช้ระบบ',
    ];

    public function index()
    {
        return view('install.requirements', ['groups' => Installer::requirements(), 'ok' => Installer::requirementsOk(), 'step' => 1]);
    }

    /** ขั้นที่ 2: License (มีคีย์ / ทดลอง / ข้าม) ยังไม่มีฐานข้อมูล เก็บผลไว้ใน session แล้วบันทึกตอนติดตั้ง */
    public function license(Request $request)
    {
        if (! Installer::requirementsOk()) {
            return redirect()->route('install.index');
        }
        $saved = $request->session()->get('install.license');

        return view('install.license', [
            'domain' => Client::domain($request->getHost()),
            'current' => $saved['license'] ?? null,
            'skipped' => $request->session()->get('install.license_skipped', false),
            'step' => 2,
        ]);
    }

    public function saveLicense(Request $request)
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['key', 'trial', 'skip', 'keep'])],
            'license_key' => ['exclude_unless:mode,key', 'required', 'string', 'min:10', 'max:50'],
        ], [], ['mode' => 'วิธีเปิดใช้งาน', 'license_key' => 'คีย์ License']);

        // ใช้ผลที่เปิดใช้งานไว้แล้วในขั้นนี้ (กลับมาแก้ขั้นอื่นแล้วกดไปต่อ)
        if ($data['mode'] === 'keep' && $request->session()->has('install.license')) {
            return redirect()->route('install.database');
        }
        // ติดตั้งก่อนแล้วค่อยเปิดใช้งานทีหลัง (ระบบจะถูกล็อกจนกว่าผู้ดูแลระบบจะกรอกคีย์)
        if ($data['mode'] === 'skip' || $data['mode'] === 'keep') {
            $request->session()->forget('install.license');
            $request->session()->put('install.license_skipped', true);

            return redirect()->route('install.database');
        }

        $domain = Client::domain($request->getHost());
        $result = $data['mode'] === 'key'
            ? Client::activate($data['license_key'], $domain, false)
            : Client::trial($domain, null, false);

        if (! $result['ok']) {
            return back()->withInput($request->only('license_key', 'mode'))->withErrors(['license_key' => $result['message']]);
        }

        $request->session()->put('install.license', ['key' => $result['key'], 'json' => $result['json'], 'license' => $result['license']]);
        $request->session()->forget('install.license_skipped');

        return redirect()->route('install.database');
    }

    public function database(Request $request)
    {
        if (! Installer::requirementsOk()) {
            return redirect()->route('install.index');
        }
        $db = $request->session()->get('install.db', []);
        $mysql = config('database.default') === 'mysql';
        $cfg = [
            // ใช้งานจริงต้องเป็น MySQL/MariaDB (SQLite ทดลองเท่านั้น) .env.example ตั้ง sqlite ไว้สำหรับนักพัฒนา จึงไม่อ่านจาก config
            'driver'   => $db['driver'] ?? (extension_loaded('pdo_mysql') ? 'mysql' : 'sqlite'),
            'host'     => $db['host'] ?? ($mysql && config('database.connections.mysql.host') !== '127.0.0.1' ? config('database.connections.mysql.host') : 'localhost'),
            'port'     => $db['port'] ?? ($mysql ? config('database.connections.mysql.port', 3306) : 3306),
            'database' => $db['database'] ?? ($mysql && config('database.connections.mysql.database') !== 'school' ? config('database.connections.mysql.database') : ''),
            'username' => $db['username'] ?? ($mysql && config('database.connections.mysql.username') !== 'root' ? config('database.connections.mysql.username') : ''),
        ];

        return view('install.database', ['cfg' => $cfg, 'step' => 3, 'sqliteOk' => extension_loaded('pdo_sqlite'), 'mysqlOk' => extension_loaded('pdo_mysql')]);
    }

    protected function dbInput(Request $request): array
    {
        $data = $request->validate([
            'driver'   => ['required', Rule::in(['mysql', 'sqlite'])],
            'host'     => ['exclude_unless:driver,mysql', 'required', 'string', 'max:190'],
            'port'     => ['exclude_unless:driver,mysql', 'nullable', 'integer', 'min:1', 'max:65535'],
            'database' => ['exclude_unless:driver,mysql', 'required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-$]+$/'],
            'username' => ['exclude_unless:driver,mysql', 'required', 'string', 'max:80'],
            'password' => ['exclude_unless:driver,mysql', 'nullable', 'string', 'max:190'],
        ], ['database.regex' => 'ชื่อฐานข้อมูลใช้ได้เฉพาะ a-z 0-9 และ _'], ['host' => 'Host', 'database' => 'ชื่อฐานข้อมูล', 'username' => 'ชื่อผู้ใช้ฐานข้อมูล']);
        $data['port'] = (int) ($data['port'] ?? 3306) ?: 3306;
        $data += ['host' => null, 'database' => null, 'username' => null, 'password' => null];

        return $data;
    }

    public function testDatabase(Request $request)
    {
        return response()->json(Installer::testDatabase($this->dbInput($request)));
    }

    public function saveDatabase(Request $request)
    {
        $c = $this->dbInput($request);
        $marker = Installer::marker();
        if ($marker && ! $this->owns($request) && $marker['age'] < 7200) {
            return back()->withInput($request->except('password'))->withErrors(['database' => 'มีการติดตั้งค้างอยู่จากเบราว์เซอร์อื่น ถ้าเป็นคุณเอง ให้ทำต่อในเบราว์เซอร์เดิม หรือลบไฟล์ storage/installing แล้วเริ่มใหม่ (หมดอายุเองใน 2 ชั่วโมง)']);
        }
        $t = Installer::testDatabase($c);
        if (! $t['ok']) {
            return back()->withInput($request->except('password'))->withErrors(['database' => $t['message']]);
        }

        Installer::setEnv($c['driver'] === 'sqlite'
            ? ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => Installer::sqlitePath()]
            : ['DB_CONNECTION' => 'mysql', 'DB_HOST' => $c['host'], 'DB_PORT' => $c['port'], 'DB_DATABASE' => $c['database'], 'DB_USERNAME' => $c['username'], 'DB_PASSWORD' => (string) ($c['password'] ?? '')]);
        $token = $this->owns($request) ? $request->session()->get('install.token') : \Illuminate\Support\Str::random(40);
        Installer::writeMarker($token);
        $request->session()->put('install.token', $token);

        $request->session()->put('install.db', array_merge(\Illuminate\Support\Arr::except($c, ['password']), ['server' => $t['server'], 'existing' => $t['existing']]));

        return redirect()->route('install.school');
    }

    public function school(Request $request)
    {
        $db = $request->session()->get('install.db');
        if (! $db || ! $this->owns($request)) {
            return redirect()->route('install.database');
        }

        return view('install.school', ['db' => $db, 'step' => 4, 'appUrl' => $this->detectUrl($request), 'school' => $request->session()->get('install.school', [])]);
    }

    public function saveSchool(Request $request)
    {
        $db = $request->session()->get('install.db');
        if (! $db || ! $this->owns($request)) {
            return redirect()->route('install.database');
        }
        $modes = $db['existing'] ? ['keep', 'wipe'] : ['new'];
        $data = $request->validate([
            'mode'         => ['required', Rule::in($modes)],
            'confirm'      => ['required_if:mode,wipe', 'nullable', Rule::in(['ลบทั้งหมด'])],
            'app_url'      => ['required', 'url', 'max:190'],
            'school_name'  => ['required_unless:mode,keep', 'nullable', 'string', 'max:150'],
            'school_short' => ['nullable', 'string', 'max:60'],
            'admin_name'   => ['required_unless:mode,keep', 'nullable', 'string', 'max:100'],
            'username'     => ['required_unless:mode,keep', 'nullable', 'string', 'max:50', 'alpha_dash:ascii'],
            'password'     => ['required_unless:mode,keep', 'nullable', 'string', 'min:8', 'max:100', 'confirmed'],
            'demo'         => ['nullable', 'boolean'],
        ], [
            'confirm.required_if' => 'พิมพ์คำว่า ลบทั้งหมด เพื่อยืนยัน', 'confirm.in' => 'พิมพ์คำว่า ลบทั้งหมด ให้ตรง',
            '*.required_unless' => 'กรุณากรอก:attribute',
            'username.alpha_dash' => 'ชื่อผู้ใช้ใช้ได้เฉพาะ a-z 0-9 - และ _',
            'password.confirmed' => 'ยืนยันรหัสผ่านไม่ตรงกัน', 'password.min' => 'รหัสผ่านอย่างน้อย 8 ตัว',
        ], ['app_url' => 'ที่อยู่เว็บ', 'school_name' => 'ชื่อโรงเรียน', 'school_short' => 'ชื่อย่อ', 'admin_name' => 'ชื่อผู้ดูแลระบบ', 'username' => 'ชื่อผู้ใช้', 'password' => 'รหัสผ่าน']);

        $request->session()->put('install.school', [
            'mode' => $data['mode'], 'app_url' => rtrim($data['app_url'], '/'), 'demo' => $request->boolean('demo') && $data['mode'] !== 'keep',
            'school_name' => $data['school_name'] ?? null, 'school_short' => $data['school_short'] ?? null, 'admin_name' => $data['admin_name'] ?? null,
            'username' => isset($data['username']) ? mb_strtolower($data['username']) : null,
            'password_hash' => ! empty($data['password']) ? Hash::make($data['password']) : null,
        ]);
        $request->session()->forget('install.seeded');

        return redirect()->route('install.run');
    }

    public function run(Request $request)
    {
        if (! $request->session()->has('install.school') || ! $this->owns($request)) {
            return redirect()->route('install.school');
        }
        $school = $request->session()->get('install.school');
        $steps = self::STEPS;
        if ($school['mode'] === 'keep') {
            $steps['seed'] = 'ใช้ข้อมูลเดิม (ข้าม)';
            $steps['admin'] = 'ใช้บัญชีเดิม (ข้าม)';
        } elseif ($school['demo']) {
            $steps['seed'] = 'ใส่ข้อมูลตัวอย่าง (นักเรียน ครู คะแนน การเงิน ฯลฯ)';
        }

        return view('install.run', ['steps' => $steps, 'step' => 5, 'school' => $school, 'db' => $request->session()->get('install.db')]);
    }

    public function runStep(Request $request, string $step)
    {
        abort_unless(isset(self::STEPS[$step]), 404);
        $school = $request->session()->get('install.school');
        if (! $school || ! $this->owns($request)) {
            return response()->json(['ok' => false, 'message' => 'ข้อมูลการติดตั้งหมดอายุ กรุณาเริ่มขั้นตอนฐานข้อมูลใหม่', 'restart' => route('install.database')], 419);
        }
        @set_time_limit(300);
        @ini_set('memory_limit', '1024M'); // ข้อมูลตัวอย่างของระบบโรงเรียนใช้หน่วยความจำมาก
        @touch(Installer::markerPath()); // ยืดอายุการจองการติดตั้ง

        try {
            $message = $this->{'step'.ucfirst($step)}($request, $school);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(['ok' => true, 'message' => $message] + ($step === 'finish' ? ['redirect' => $this->doneUrl] : []));
    }

    protected function stepPrepare(Request $request, array $school): string
    {
        // APP_ENV=production บังคับ https ทุกลิงก์ (AppServiceProvider) โฮสต์ที่ยังไม่มี SSL ตั้ง FORCE_HTTPS=false
        $https = str_starts_with($school['app_url'], 'https://');
        Installer::setEnv([
            'APP_URL' => $school['app_url'], 'APP_ENV' => 'production', 'APP_DEBUG' => false, 'FORCE_HTTPS' => $https,
        ]);
        @touch(Installer::markerPath());

        return 'บันทึกที่อยู่เว็บ '.$school['app_url'].($https ? '' : ' (ยังไม่ใช้ https กล้องสแกน QR และ GPS จะใช้ไม่ได้จนกว่าจะติด SSL)');
    }

    protected function stepMigrate(Request $request, array $school): string
    {
        DB::connection()->getPdo();
        $fresh = $school['mode'] === 'wipe';
        Artisan::call($fresh ? 'migrate:fresh' : 'migrate', ['--force' => true]);
        $n = count(DB::connection()->getSchemaBuilder()->getTableListing());

        return ($fresh ? 'ลบข้อมูลเดิมแล้วสร้างตารางใหม่ ' : 'ตารางพร้อมใช้ ').$n.' ตาราง';
    }

    protected function stepSeed(Request $request, array $school): string
    {
        if ($school['mode'] === 'keep') {
            return 'ใช้ข้อมูลเดิมในฐานข้อมูล';
        }
        if (! $school['demo']) {
            return 'บทบาทและสิทธิ์ตั้งต้นพร้อมแล้ว (ไม่มีข้อมูลตัวอย่าง)';
        }
        // ลองซ้ำหลังพลาดกลางทาง: ล้างก่อนกันข้อมูลซ้ำ
        if ($request->session()->get('install.seeded')) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
        $request->session()->put('install.seeded', true);
        $request->session()->save();

        Artisan::call('db:seed', ['--force' => true]);

        return 'ใส่ข้อมูลตัวอย่างแล้ว';
    }

    protected function stepAdmin(Request $request, array $school): string
    {
        if ($school['mode'] === 'keep') {
            return 'ใช้บัญชีเดิม';
        }
        SchoolSetup::school($school['school_name'], $school['school_short']);
        SchoolSetup::admin($school['admin_name'], $school['username'], $school['password_hash']);

        return 'บัญชีผู้ดูแลระบบ '.$school['username'];
    }

    protected function stepLicense(Request $request, array $school): string
    {
        // ทำหลัง seed เพราะการลองใหม่อาจ migrate:fresh ล้างตาราง settings
        $data = $request->session()->get('install.license');
        if (! $data) {
            return 'ยังไม่ได้เปิดใช้งาน เปิดใช้ได้ที่ ตั้งค่า > License หลังเข้าสู่ระบบ';
        }
        Client::remember($data['json'], $data['key'] ?? null);

        return ! empty($data['license']['trial']) ? 'เริ่มทดลองใช้งาน '.config('license.trial_days').' วันแล้ว' : 'เปิดใช้งาน License แล้ว';
    }

    protected function stepStorage(Request $request, array $school): string
    {
        foreach ([storage_path('app/public'), storage_path('app/private'), storage_path('fonts')] as $d) {
            if (! is_dir($d)) {
                @mkdir($d, 0775, true);
            }
        }
        DbBackup::dir(); // สร้างโฟลเดอร์สำรองพร้อม .htaccess กันเปิดผ่านเว็บ
        $link = public_path('storage');
        if (is_link($link) && ! is_dir($link)) {
            @unlink($link); // ทางลัดเสียจากเครื่องอื่น
        }
        if (! file_exists($link)) {
            if (function_exists('symlink') && @symlink('../storage/app/public', $link)) {
                return 'สร้างทางลัด public/storage แล้ว';
            }

            return 'โฮสต์ไม่อนุญาตทางลัด ใช้ทางสำรอง (ระบบส่งไฟล์รูปให้เอง)';
        }

        return 'มีโฟลเดอร์รูปภาพพร้อมแล้ว';
    }

    protected function stepFinish(Request $request, array $school): string
    {
        // กลับไปใช้ฐานข้อมูลเก็บ session / cache / คิว ตามปกติ
        Installer::setEnv(['SESSION_DRIVER' => 'database', 'CACHE_STORE' => 'database', 'QUEUE_CONNECTION' => 'database', 'SCHOOL_INSTALL_PENDING' => false]);
        foreach (['view:clear', 'config:clear', 'route:clear', 'cache:clear'] as $cmd) {
            try {
                Artisan::call($cmd);
            } catch (\Throwable) {
            }
        }
        Settings::flush();
        $login = $school['mode'] === 'keep' ? (User::query()->where('role', 'admin')->orderBy('id')->value('username') ?? '') : $school['username'];
        // หน้าสรุปเปิดได้เฉพาะลิงก์นี้ (มีชื่อผู้ใช้ผู้ดูแลและ path บนเซิร์ฟเวอร์)
        $done = \Illuminate\Support\Str::random(32);
        Installer::writeLock(Installer::codeVersion(), ['login' => $login, 'demo' => (bool) $school['demo'], 'database' => $request->session()->get('install.db.server'), 'done_token' => hash('sha256', $done), 'signed' => Integrity::hasManifest()]);
        $request->session()->forget(['install.school', 'install.db', 'install.seeded', 'install.token', 'install.license']);
        Guard::flush();
        $this->doneUrl = route('install.done', ['t' => $done]);

        return 'ติดตั้งเสร็จ';
    }

    public function done(Request $request)
    {
        if (! Installer::isInstalled()) {
            return redirect()->route('install.index');
        }
        $lock = Installer::lock();
        $ok = ! empty($lock['done_token']) && hash_equals($lock['done_token'], hash('sha256', (string) $request->query('t')));
        if (! $ok || now()->diffInHours(\Illuminate\Support\Carbon::parse($lock['installed_at'] ?? now()), true) > 3) {
            return redirect()->route('login');
        }

        return view('install.done', ['lock' => $lock, 'step' => 6, 'license' => Guard::status(), 'cron' => 'php '.base_path('artisan').' schedule:run >> /dev/null 2>&1']);
    }

    protected function detectUrl(Request $request): string
    {
        $env = (string) config('app.url');
        $req = rtrim($request->getSchemeAndHttpHost().$request->getBaseUrl(), '/');

        return $req ?: $env;
    }
}
