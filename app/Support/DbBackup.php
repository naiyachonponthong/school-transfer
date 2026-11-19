<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * สำรอง / กู้คืนฐานข้อมูล MySQL เป็นไฟล์ .sql.gz ด้วย PHP ล้วน
 * ใช้แทน mysqldump / mysql บน shared hosting ที่ไม่มีโปรแกรมเหล่านี้หรือปิดการสั่งโปรแกรมภายนอก
 * ไฟล์นำเข้าคืนได้ทั้ง `php artisan backup:restore` และ phpMyAdmin > Import
 */
class DbBackup
{
    /** โฟลเดอร์ไฟล์สำรอง (กันเปิดตรงผ่านเว็บด้วย .htaccess เผื่อวางโปรเจ็คไว้ใต้ public_html) */
    public static function dir(): string
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (! is_file($dir.'/.htaccess')) {
            @file_put_contents($dir.'/.htaccess', "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
        }

        return $dir;
    }

    private static function isMysql(string $driver): bool
    {
        return $driver === 'mysql' || $driver === 'mariadb';
    }

    /** เขียนฐานข้อมูลปัจจุบันลงไฟล์ $path (.sql.gz) */
    public static function create(string $path): void
    {
        @set_time_limit(300);
        $conn = DB::connection();
        $driver = $conn->getDriverName();
        $pdo = $conn->getPdo();
        if (! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }
        $gz = @gzopen($path.'.part', 'wb6');
        if (! $gz) {
            throw new \RuntimeException('สร้างไฟล์สำรองไม่ได้ ตรวจสิทธิ์เขียนโฟลเดอร์ storage/app/backups');
        }
        $mysql = static::isMysql($driver);
        $q = $mysql ? fn ($n) => '`'.str_replace('`', '``', $n).'`' : fn ($n) => '"'.str_replace('"', '""', $n).'"';

        try {
            gzwrite($gz, '-- ระบบบริหารโรงเรียน backup '.now()->toDateTimeString().' | version '.Installer::installedVersion().' | '.$driver."\n");
            if ($mysql) {
                gzwrite($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
                $tables = [];
                foreach ($pdo->query('SHOW FULL TABLES')->fetchAll(\PDO::FETCH_NUM) as $r) {
                    if (($r[1] ?? '') === 'BASE TABLE') {
                        $tables[] = $r[0];
                    }
                }
            } else {
                gzwrite($gz, "PRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n\n");
                $tables = $pdo->query("select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name")->fetchAll(\PDO::FETCH_COLUMN);
            }

            foreach ($tables as $t) {
                if ($mysql) {
                    $create = $pdo->query('SHOW CREATE TABLE '.$q($t))->fetch(\PDO::FETCH_NUM)[1];
                } else {
                    $st = $pdo->prepare("select sql from sqlite_master where type = 'table' and name = ?");
                    $st->execute([$t]);
                    $create = $st->fetchColumn();
                }
                gzwrite($gz, 'DROP TABLE IF EXISTS '.$q($t).";\n".$create.";\n");

                // ข้อมูลทีละ 500 แถว เรียงตาม id ถ้ามี
                $cols = $conn->getSchemaBuilder()->getColumnListing($t);
                $order = in_array('id', $cols, true) ? ' ORDER BY '.$q('id') : '';
                $offset = 0;
                do {
                    $rows = $pdo->query('SELECT * FROM '.$q($t).$order.' LIMIT 500 OFFSET '.$offset)->fetchAll(\PDO::FETCH_ASSOC);
                    if ($rows) {
                        $head = 'INSERT INTO '.$q($t).' ('.implode(',', array_map($q, array_keys($rows[0]))).') VALUES'."\n";
                        $vals = [];
                        foreach ($rows as $r) {
                            $vals[] = '('.implode(',', array_map(fn ($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : static::quote($pdo, (string) $v, $mysql)), $r)).')';
                        }
                        gzwrite($gz, $head.implode(",\n", $vals).";\n");
                    }
                    $offset += 500;
                } while (count($rows) === 500);
                gzwrite($gz, "\n");
            }

            gzwrite($gz, $mysql ? "SET FOREIGN_KEY_CHECKS=1;\n" : "COMMIT;\nPRAGMA foreign_keys=ON;\n");
            gzclose($gz);
        } catch (\Throwable $e) {
            @gzclose($gz);
            @unlink($path.'.part');
            throw $e;
        }
        rename($path.'.part', $path);
    }

    /**
     * ค่าข้อความในคำสั่ง SQL: MySQL ใช้ quote ของ PDO (ขึ้นบรรทัดใหม่ถูก escape เป็น \n)
     * SQLite ไม่มี backslash escape จึงแทนขึ้นบรรทัดใหม่ด้วย char(10) ให้ทุกคำสั่งจบในบรรทัดที่ลงท้ายด้วย ;
     */
    private static function quote(\PDO $pdo, string $v, bool $mysql): string
    {
        $s = $pdo->quote($v);
        if ($mysql || ! preg_match('/[\r\n]/', $v)) {
            return $s;
        }

        return str_replace(["\r", "\n"], ["'||char(13)||'", "'||char(10)||'"], $s);
    }

    /**
     * นำเข้าไฟล์ .sql.gz หรือ .sql (ทับข้อมูลเดิมทั้งหมด) คืนจำนวนคำสั่งที่รัน
     * แยกคำสั่งที่บรรทัดที่ลงท้ายด้วย ; (ไฟล์จาก create() และ mysqldump ปกติเป็นแบบนี้)
     */
    public static function restore(string $path): int
    {
        @set_time_limit(1800);
        $gz = @gzopen($path, 'rb'); // อ่านไฟล์ .sql ธรรมดาได้ด้วย
        if (! $gz) {
            throw new \RuntimeException('เปิดไฟล์สำรองไม่ได้: '.basename($path));
        }
        $pdo = DB::connection()->getPdo();
        if (DB::connection()->getDriverName() === 'sqlite' && $pdo->inTransaction()) {
            $pdo->exec('PRAGMA defer_foreign_keys = ON'); // ใน transaction ปิด foreign_keys ไม่ได้ เลื่อนการตรวจไปตอน commit แทน
        }
        $buf = '';
        $count = 0;
        try {
            while (($line = gzgets($gz)) !== false) {
                $trim = rtrim($line);
                if ($buf === '' && ($trim === '' || str_starts_with($trim, '--'))) {
                    continue;
                }
                // ไฟล์ SQLite ครอบด้วย BEGIN/COMMIT: ถ้าเปิด transaction ไว้แล้ว (เช่นในเทสต์) ใช้ของเดิม
                if ($buf === '' && in_array($trim, ['BEGIN TRANSACTION;', 'COMMIT;'], true) && $pdo->inTransaction()) {
                    continue;
                }
                $buf .= $line;
                if (str_ends_with($trim, ';')) {
                    $pdo->exec($buf);
                    $buf = '';
                    $count++;
                }
            }
            if (trim($buf) !== '') {
                $pdo->exec($buf);
                $count++;
            }
        } finally {
            gzclose($gz);
        }

        return $count;
    }
}
