<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ตัวติดตั้งแบบ WordPress
 * - storage/installed  ไฟล์ล็อก (json: version, installed_at, login) มีไฟล์นี้ = ติดตั้งแล้ว ปิดหน้า /install ถาวร
 * - storage/installing ระหว่างติดตั้ง (หลังบันทึกฐานข้อมูล) กันไม่ให้ระบบเข้าใจผิดว่าเป็นระบบเดิม
 * - เวอร์ชันโค้ด (config school.version) ใหม่กว่าในไฟล์ล็อก = ต้องอัปเดต (/update)
 */
class Installer
{
    protected static ?array $lock = null;

    public static function lockPath(): string
    {
        // เฉพาะตอนรันชุดทดสอบ: ย้ายไฟล์ล็อกไปไฟล์ชั่วคราว ไม่แตะไฟล์ล็อกจริงของเครื่อง
        $test = config('school.test_lock_path');
        if ($test && \App\Support\License\Guard::testing()) {
            return (string) $test;
        }

        return storage_path('installed');
    }

    /** ล้างค่าที่อ่านจากไฟล์ล็อกไว้ (ไฟล์ถูกแก้จากที่อื่น หรือในเทสต์) */
    public static function flushLock(): void
    {
        static::$lock = null;
    }

    public static function markerPath(): string
    {
        return storage_path('installing');
    }

    public static function isInstalled(): bool
    {
        return is_file(static::lockPath());
    }

    public static function lock(): array
    {
        if (static::$lock === null) {
            $raw = is_file(static::lockPath()) ? (string) @file_get_contents(static::lockPath()) : '';
            static::$lock = json_decode($raw, true) ?: [];
        }

        return static::$lock;
    }

    public static function writeLock(string $version, array $extra = []): void
    {
        $data = array_merge(static::lock(), $extra, ['version' => $version, 'updated_at' => now()->toDateTimeString()]);
        $data['installed_at'] ??= now()->toDateTimeString();
        file_put_contents(static::lockPath(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        @unlink(static::markerPath());
        static::$lock = $data;
    }

    public static function codeVersion(): string
    {
        return (string) config('school.version', '1.0.0');
    }

    public static function installedVersion(): string
    {
        return (string) (static::lock()['version'] ?? '0.0.0');
    }

    public static function needsUpdate(): bool
    {
        return static::isInstalled() && version_compare(static::installedVersion(), static::codeVersion(), '<');
    }

    /**
     * .env ที่ preboot สร้างให้ตอนเปิดเว็บครั้งแรก (SCHOOL_INSTALL_PENDING=true)
     * ตัวติดตั้งเปิดได้เฉพาะกรณีนี้ ระบบเดิมที่ฐานข้อมูลล่มชั่วคราวจะไม่กลายเป็นหน้าติดตั้งให้คนอื่นเข้ามาตั้งค่าทับ
     */
    public static function installPending(): bool
    {
        return (bool) config('school.install_pending');
    }

    /**
     * ระบบที่ติดตั้งไว้ก่อนมีตัวติดตั้ง (ไม่มีไฟล์ล็อก แต่ฐานข้อมูลมีผู้ใช้แล้ว) รับเข้าเป็นระบบที่ติดตั้งแล้ว
     * ถ้ามี migration ค้างให้เวอร์ชันเป็น 0.0.0 เพื่อพาไปหน้าอัปเดต
     */
    public static function tryAdopt(): bool
    {
        if (static::isInstalled()) {
            return true;
        }
        if (static::installPending() || is_file(static::markerPath())) {
            return false;
        }
        try {
            DB::connection()->getPdo();
            if (Schema::hasTable('users') && DB::table('users')->exists()) {
                // ระบบเดิมก่อนมี License: ให้เวลาไปหาคีย์ (หน้า /update จะขอทดลองใช้ให้อัตโนมัติด้วย)
                static::writeLock(static::pendingMigrations() ? '0.0.0' : static::codeVersion(), [
                    'adopted' => true,
                    'upgrade_grace_until' => time() + (int) config('license.upgrade_grace_days', 30) * 86400,
                ]);

                return true;
            }
        } catch (\Throwable) {
            // เชื่อมต่อไม่ได้ หรือยังไม่ได้สร้างตาราง
        }

        return false;
    }

    /**
     * ผูกการติดตั้งกับเบราว์เซอร์ที่บันทึกฐานข้อมูลเป็นคนแรก (กันคนอื่นเข้ามาแทรกระหว่างติดตั้ง)
     *
     * @return array{token:string, age:int}|null
     */
    public static function marker(): ?array
    {
        $f = static::markerPath();
        if (! is_file($f)) {
            return null;
        }

        return ['token' => trim((string) @file_get_contents($f)), 'age' => time() - (int) filemtime($f)];
    }

    public static function writeMarker(string $token): void
    {
        file_put_contents(static::markerPath(), $token, LOCK_EX);
    }

    public static function ownsInstall(?string $sessionToken): bool
    {
        $m = static::marker();

        return $m && $sessionToken && $m['token'] !== '' && hash_equals($m['token'], $sessionToken);
    }

    /** ชื่อ migration ที่ยังไม่ได้รัน */
    public static function pendingMigrations(): array
    {
        $migrator = app('migrator');
        $files = $migrator->getMigrationFiles([database_path('migrations')]);
        if (! $migrator->repositoryExists()) {
            return array_keys($files);
        }

        return array_values(array_diff(array_keys($files), $migrator->getRepository()->getRan()));
    }

    /* ------------------------------------------------------------------
     | ตรวจเครื่อง
     * ------------------------------------------------------------------ */

    public static function phpNeed(): string
    {
        $need = 80300;
        $file = base_path('vendor/composer/platform_check.php');
        if (is_file($file) && preg_match('/PHP_VERSION_ID >= (\d+)/', (string) file_get_contents($file), $m)) {
            $need = (int) $m[1];
        }

        return intdiv($need, 10000).'.'.intdiv($need % 10000, 100).'.'.($need % 100);
    }

    /** @return array<string, array<int, array{label:string, ok:bool, note:string, required:bool}>> */
    public static function requirements(): array
    {
        $item = fn (string $label, bool $ok, string $note = '', bool $required = true) => compact('label', 'ok', 'note', 'required');
        $need = static::phpNeed();

        $php = [$item('PHP '.$need.' ขึ้นไป', version_compare(PHP_VERSION, $need, '>='), 'โฮสต์ใช้ '.PHP_VERSION)];

        $ext = [];
        $ext[] = $item('ไดรเวอร์ฐานข้อมูล (pdo_mysql หรือ pdo_sqlite)', extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite'),
            implode(' ', array_filter([extension_loaded('pdo_mysql') ? 'pdo_mysql' : null, extension_loaded('pdo_sqlite') ? 'pdo_sqlite' : null])) ?: 'ยังไม่เปิด');
        foreach (['mbstring', 'openssl', 'tokenizer', 'ctype', 'fileinfo', 'dom', 'xml', 'simplexml', 'xmlreader', 'xmlwriter', 'gd', 'zip', 'zlib', 'iconv'] as $e) {
            $ext[] = $item($e, extension_loaded($e), extension_loaded($e) ? '' : 'ยังไม่เปิด');
        }
        foreach (['intl' => 'จัดรูปแบบตัวเลขและวันที่', 'curl' => 'เชื่อมระบบภายนอก', 'bcmath' => 'คำนวณเลขทศนิยมละเอียด', 'sodium' => 'ตรวจลายเซ็น License ในเครื่อง'] as $e => $why) {
            $ext[] = $item($e, extension_loaded($e), extension_loaded($e) ? '' : 'แนะนำให้เปิด ('.$why.')', false);
        }

        $dirs = [];
        foreach (['.env' => base_path('.env'), 'storage' => storage_path(), 'storage/app/public' => storage_path('app/public'), 'storage/framework' => storage_path('framework'),
            'storage/logs' => storage_path('logs'), 'bootstrap/cache' => base_path('bootstrap/cache')] as $label => $path) {
            $dirs[] = $item($label, is_writable($path), is_writable($path) ? 'เขียนได้' : 'ต้องตั้ง Permissions 755 หรือ 775');
        }
        $dirs[] = $item('public (สร้างทางลัดโฟลเดอร์รูป)', is_writable(public_path()), is_writable(public_path()) ? 'เขียนได้' : 'ถ้าเขียนไม่ได้ ระบบใช้ทางสำรองส่งรูปให้เอง', false);

        $ini = [];
        $upload = static::bytes((string) ini_get('upload_max_filesize'));
        $post = static::bytes((string) ini_get('post_max_size'));
        $mem = static::bytes((string) ini_get('memory_limit'));
        $ini[] = $item('upload_max_filesize', $upload >= 8 * 1048576, ini_get('upload_max_filesize').' (แนะนำ 8M ขึ้นไป สำหรับรูปนักเรียน สลิป และภาพกระดาษคำตอบ)', false);
        $ini[] = $item('post_max_size', $post >= 8 * 1048576, ini_get('post_max_size').' (ควรมากกว่า upload_max_filesize)', false);
        $ini[] = $item('memory_limit', $mem < 0 || $mem >= 128 * 1048576, ini_get('memory_limit').' (แนะนำ 128M ขึ้นไป สำหรับออก PDF และ Excel)', false);
        $ini[] = $item('ฟังก์ชัน symlink', function_exists('symlink') && ! in_array('symlink', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true),
            'ปิดอยู่ก็ใช้ได้ ระบบมีทางสำรอง', false);

        return ['PHP' => $php, 'Extension' => $ext, 'สิทธิ์เขียนไฟล์' => $dirs, 'ค่าในโฮสต์' => $ini];
    }

    public static function requirementsOk(): bool
    {
        foreach (static::requirements() as $items) {
            foreach ($items as $i) {
                if ($i['required'] && ! $i['ok']) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function bytes(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') {
            return -1;
        }
        $n = (int) $v;

        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n,
        };
    }

    /* ------------------------------------------------------------------
     | ฐานข้อมูล
     * ------------------------------------------------------------------ */

    public static function sqlitePath(): string
    {
        return database_path('database.sqlite');
    }

    /**
     * ทดสอบเชื่อมต่อ คืน [ok, ข้อความ, ข้อมูลเพิ่ม]
     *
     * @return array{ok:bool, message:string, server?:string, existing?:bool, tables?:int}
     */
    public static function testDatabase(array $c): array
    {
        try {
            if ($c['driver'] === 'sqlite') {
                if (! extension_loaded('pdo_sqlite')) {
                    return ['ok' => false, 'message' => 'โฮสต์ยังไม่เปิด extension pdo_sqlite'];
                }
                $path = static::sqlitePath();
                if (! is_file($path) && ! @touch($path)) {
                    return ['ok' => false, 'message' => 'สร้างไฟล์ฐานข้อมูลในโฟลเดอร์ database ไม่ได้ ตั้ง Permissions โฟลเดอร์ database เป็น 755'];
                }
                $pdo = new \PDO('sqlite:'.$path, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                $server = 'SQLite '.$pdo->query('select sqlite_version()')->fetchColumn();
                $tables = $pdo->query("select name from sqlite_master where type = 'table' and name not like 'sqlite_%'")->fetchAll(\PDO::FETCH_COLUMN);
            } else {
                if (! extension_loaded('pdo_mysql')) {
                    return ['ok' => false, 'message' => 'โฮสต์ยังไม่เปิด extension pdo_mysql'];
                }
                $dsn = 'mysql:host='.$c['host'].';port='.((int) $c['port'] ?: 3306).';dbname='.$c['database'].';charset=utf8mb4';
                $pdo = new \PDO($dsn, $c['username'], (string) ($c['password'] ?? ''), [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 6]);
                $ver = (string) $pdo->query('select version()')->fetchColumn();
                $maria = stripos($ver, 'mariadb') !== false;
                preg_match('/^(\d+\.\d+(\.\d+)?)/', $ver, $m);
                $num = $m[1] ?? '0';
                $server = ($maria ? 'MariaDB ' : 'MySQL ').$num;
                if (version_compare($num, $maria ? '10.3' : '5.7', '<')) {
                    return ['ok' => false, 'message' => 'ฐานข้อมูลรุ่นเก่าเกินไป ('.$server.') ต้องเป็น MySQL 5.7 หรือ MariaDB 10.3 ขึ้นไป'];
                }
                $tables = $pdo->query('show tables')->fetchAll(\PDO::FETCH_COLUMN);
            }
        } catch (\PDOException $e) {
            return ['ok' => false, 'message' => static::dbError($e)];
        }

        $existing = in_array('migrations', $tables, true) && in_array('users', $tables, true);
        $msg = 'เชื่อมต่อสำเร็จ ('.$server.')';
        if ($existing) {
            $msg .= ' พบข้อมูล ระบบบริหารโรงเรียน เดิมในฐานข้อมูลนี้';
        } elseif ($tables) {
            $msg .= ' ฐานข้อมูลนี้มีตารางอื่นอยู่ '.count($tables).' ตาราง แนะนำให้ใช้ฐานข้อมูลว่าง';
        }

        return ['ok' => true, 'message' => $msg, 'server' => $server, 'existing' => $existing, 'tables' => count($tables)];
    }

    public static function dbError(\PDOException $e): string
    {
        $code = (int) ($e->errorInfo[1] ?? 0) ?: (int) $e->getCode();
        $raw = $e->getMessage();

        return match (true) {
            $code === 1045 => 'ชื่อผู้ใช้หรือรหัสผ่านฐานข้อมูลไม่ถูกต้อง',
            $code === 1044 => 'ผู้ใช้นี้ยังไม่มีสิทธิ์ในฐานข้อมูลนี้ ใน cPanel > MySQL Databases ให้เพิ่มผู้ใช้เข้าฐานข้อมูลและติ๊ก ALL PRIVILEGES',
            $code === 1049 => 'ไม่พบฐานข้อมูลชื่อนี้ สร้างฐานข้อมูลใน cPanel / DirectAdmin ก่อน (ชื่อมักขึ้นต้นด้วยชื่อบัญชีโฮสต์ เช่น user_school)',
            $code === 2002 || str_contains($raw, 'Connection refused') => 'เชื่อมต่อเซิร์ฟเวอร์ฐานข้อมูลไม่ได้ ตรวจ Host และ Port (โฮสต์ส่วนใหญ่ใช้ localhost พอร์ต 3306)',
            $code === 2005 || str_contains($raw, 'getaddrinfo') => 'ไม่รู้จัก Host ฐานข้อมูลนี้ ตรวจการสะกด',
            $code === 2006 || str_contains($raw, 'timed out') => 'เซิร์ฟเวอร์ฐานข้อมูลไม่ตอบ (หมดเวลา) ตรวจ Host และไฟร์วอลล์',
            default => 'เชื่อมต่อไม่สำเร็จ: '.$raw,
        };
    }

    /* ------------------------------------------------------------------
     | .env
     * ------------------------------------------------------------------ */

    public static function setEnv(array $pairs): void
    {
        $path = base_path('.env');
        $s = is_file($path) ? (string) file_get_contents($path) : '';
        foreach ($pairs as $k => $v) {
            $line = $k.'='.static::envValue($v);
            $pattern = '/^'.preg_quote($k, '/').'=.*$/m';
            if (preg_match($pattern, $s)) {
                $s = preg_replace_callback($pattern, fn () => $line, $s, 1);
            } else {
                $s = rtrim($s)."\n".$line."\n";
            }
        }
        if (file_put_contents($path, $s, LOCK_EX) === false) {
            throw new \RuntimeException('เขียนไฟล์ .env ไม่ได้ ตรวจ Permissions ของไฟล์ .env');
        }
    }

    public static function envValue(mixed $v): string
    {
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        $v = str_replace(["\r", "\n"], '', (string) $v);
        if ($v === '' || preg_match('/^[A-Za-z0-9_.:\/@+\-]+$/', $v)) {
            return $v;
        }
        if (! str_contains($v, "'")) {
            return "'".$v."'"; // single quote = ตามตัวอักษร ไม่แปล $ และ \
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $v).'"';
    }
}
