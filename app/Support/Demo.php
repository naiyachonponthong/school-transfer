<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * โหมดทดลองใช้: ให้คนภายนอกกดเข้าระบบตามบทบาทโดยไม่ต้องมีรหัสผ่าน (ปุ่มในหน้าเข้าสู่ระบบ)
 * ระหว่างทดลอง ระบบกันการเปลี่ยนรหัสผ่าน การตั้งค่า การจัดการผู้ใช้ และการลบข้อมูล (App\Http\Middleware\DemoRestrictions)
 * และคืนข้อมูลกลับเป็น "ต้นแบบ" ที่บันทึกไว้ได้ทุกคืน (demo:reset)
 */
class Demo
{
    /** บทบาทที่เปิดให้ทดลอง => [ชื่อปุ่ม, ไอคอน, คำอธิบาย] */
    public const ROLES = [
        'exec' => ['ผู้บริหาร', 'bi-speedometer2', 'ดูได้อย่างเดียว'],
        'teacher' => ['ครู', 'bi-person-workspace', 'เช็คชื่อ คะแนน การบ้าน'],
        'parent' => ['ผู้ปกครอง', 'bi-house-heart', 'ติดตามบุตรหลาน'],
        'student' => ['นักเรียน', 'bi-backpack', 'ตารางเรียน การบ้าน'],
    ];

    /** ตารางที่ไม่เก็บ/ไม่คืน: สถานะของระบบเอง ไม่ใช่ข้อมูลโรงเรียน */
    private const SKIP_TABLES = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'push_subscriptions', 'push_messages'];

    public static function enabled(): bool
    {
        return Settings::get('demo_mode') === '1';
    }

    /** กำลังใช้งานผ่านปุ่มทดลองใช้อยู่หรือไม่ (คืนชื่อบทบาท) */
    public static function role(): ?string
    {
        return session('demo_role');
    }

    public static function userFor(string $role): ?User
    {
        $id = (int) Settings::get('demo_user_'.$role);

        return $id ? User::where('is_active', true)->find($id) : null;
    }

    /** @return array<string, array{0:string,1:string,2:string}> บทบาทที่ตั้งผู้ใช้ไว้แล้ว */
    public static function available(): array
    {
        return self::enabled() ? array_filter(self::ROLES, fn ($_, $role) => self::userFor($role) !== null, ARRAY_FILTER_USE_BOTH) : [];
    }

    /* ---------------- ต้นแบบข้อมูล ---------------- */

    public static function dir(): string
    {
        return storage_path('app/demo-snapshot');
    }

    public static function snapshotAt(): ?string
    {
        $meta = self::meta();

        return $meta['taken_at'] ?? null;
    }

    private static function meta(): array
    {
        $file = self::dir().'/_meta.json';

        return File::exists($file) ? (json_decode(File::get($file), true) ?: []) : [];
    }

    /** @return list<string> */
    private static function tables(): array
    {
        return collect(Schema::getTables())->pluck('name')->reject(fn ($t) => in_array($t, self::SKIP_TABLES, true) || str_starts_with($t, 'sqlite_'))->values()->all();
    }

    /** บันทึกข้อมูลปัจจุบันทั้งฐานข้อมูลเป็นต้นแบบ (ไฟล์ละตาราง บรรทัดละแถว) คืนจำนวนแถว */
    public static function snapshot(): int
    {
        $dir = self::dir();
        File::deleteDirectory($dir);
        File::ensureDirectoryExists($dir);
        File::put($dir.'/.htaccess', "Require all denied\n");

        $rows = 0;
        foreach (self::tables() as $table) {
            $handle = fopen($dir.'/'.$table.'.jsonl', 'w');
            foreach (DB::table($table)->cursor() as $row) {
                fwrite($handle, json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)."\n");
                $rows++;
            }
            fclose($handle);
        }
        File::put($dir.'/_meta.json', json_encode(['taken_at' => now()->toDateTimeString(), 'migrations' => DB::table('migrations')->count(), 'rows' => $rows]));

        return $rows;
    }

    /** ทำงานกับทุกตาราง โดยตารางที่ล้มเพราะความสัมพันธ์ระหว่างตารางจะถูกลองใหม่จนกว่าจะไม่คืบหน้า */
    private static function inPasses(array $tables, callable $work): void
    {
        while ($tables) {
            $failed = [];
            $error = null;
            foreach ($tables as $table) {
                try {
                    $work($table);
                } catch (\Illuminate\Database\QueryException $e) {
                    $failed[] = $table;
                    $error = $e;
                }
            }
            if (count($failed) === count($tables)) {
                throw new RuntimeException('คืนข้อมูลไม่สำเร็จที่ตาราง '.implode(', ', $failed).': '.$error->getMessage());
            }
            $tables = $failed;
        }
    }

    private static function fill(string $table): int
    {
        $rows = 0;
        $batch = [];
        $handle = fopen(self::dir().'/'.$table.'.jsonl', 'r');
        try {
            while (($line = fgets($handle)) !== false) {
                $batch[] = json_decode($line, true);
                if (count($batch) >= 200) {
                    DB::table($table)->insert($batch);
                    $rows += count($batch);
                    $batch = [];
                }
            }
            if ($batch) {
                DB::table($table)->insert($batch);
                $rows += count($batch);
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    /** คืนข้อมูลทุกตารางกลับเป็นต้นแบบ · ถ้าโครงสร้างฐานข้อมูลเปลี่ยนหลังบันทึกต้นแบบ จะไม่ทำ (ต้องบันทึกต้นแบบใหม่) */
    public static function reset(): int
    {
        $meta = self::meta();
        if (! $meta) {
            throw new RuntimeException('ยังไม่ได้บันทึกต้นแบบข้อมูล');
        }
        if ((int) $meta['migrations'] !== DB::table('migrations')->count()) {
            throw new RuntimeException('โครงสร้างฐานข้อมูลเปลี่ยนไปหลังบันทึกต้นแบบ กรุณาบันทึกต้นแบบใหม่');
        }

        $tables = array_values(array_filter(self::tables(), fn ($t) => File::exists(self::dir().'/'.$t.'.jsonl'))); // ตารางที่ไม่มีในต้นแบบ ปล่อยไว้ตามเดิม
        $rows = 0;
        Schema::disableForeignKeyConstraints();
        try {
            // บางฐานข้อมูลปิดการตรวจ foreign key กลางรายการไม่ได้ จึงทำเป็นรอบ: ตารางที่ยังติดความสัมพันธ์ไว้ลองใหม่รอบถัดไป
            self::inPasses($tables, fn (string $table) => DB::table($table)->delete());
            self::inPasses($tables, function (string $table) use (&$rows) {
                $rows += DB::transaction(fn () => self::fill($table));
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        cache()->flush();
        Settings::flush();

        return $rows;
    }
}
