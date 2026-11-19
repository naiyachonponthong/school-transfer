<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\BehaviorRecord;
use App\Models\Student;
use App\Models\Term;
use App\Services\Notifier;
use Illuminate\Support\Facades\DB;

/**
 * หักคะแนนความประพฤติอัตโนมัติจากการเช็กชื่อรายวัน (ตั้งค่าที่ ตั้งค่า > คะแนนความประพฤติ)
 * เช่น สายทุก 3 ครั้ง หัก 5 คะแนน นับใหม่ทุกภาคเรียน
 *
 * คำนวณใหม่ทั้งภาคเรียนทุกครั้งที่สถานะการมาเรียนของนักเรียนคนนั้นเปลี่ยน (ครูเช็กชื่อ สแกนหน้าประตู อนุมัติใบลา)
 * รายการที่หักไว้มี auto_key กำกับ จึงไม่หักซ้ำ และถ้าแก้ขาดเป็นลาจนไม่ครบรอบ ระบบลบรายการนั้น (คืนคะแนน) ให้เอง
 */
class AutoBehavior
{
    /** สถานะการมาเรียน => ชื่อที่ใช้ในประวัติพฤติกรรม */
    public const KINDS = ['late' => 'มาสาย', 'absent' => 'ขาดเรียน'];

    /** @return array{every:int, points:int} */
    public static function rule(string $kind): array
    {
        return [
            'every' => max(0, (int) Settings::get('behavior_auto_'.$kind.'_every', 0)),
            'points' => max(0, (int) Settings::get('behavior_auto_'.$kind.'_points', 0)),
        ];
    }

    public static function enabled(): bool
    {
        foreach (array_keys(self::KINDS) as $kind) {
            $r = self::rule($kind);
            if ($r['every'] > 0 && $r['points'] > 0) {
                return true;
            }
        }

        return false;
    }

    /** ภาคเรียนที่ครอบวันนี้ (ต้องตั้งวันเริ่ม/สิ้นสุดภาคเรียนไว้ ไม่งั้นนับไม่ได้) */
    public static function termOf(string $date): ?Term
    {
        return Term::whereNotNull('start_date')->whereNotNull('end_date')
            ->where('start_date', '<=', $date)->where('end_date', '>=', $date)->first();
    }

    /** คำนวณการหักคะแนนของนักเรียนคนนี้ในภาคเรียนที่ครอบวันที่นี้ใหม่ คืนจำนวนรายการที่เพิ่ม/ลบ */
    public static function sync(int $studentId, string $date, bool $notify = true): int
    {
        $term = self::termOf($date);
        if (! $term) {
            return 0;
        }
        $from = $term->start_date->toDateString();
        $to = $term->end_date->toDateString();
        $changed = 0;

        foreach (self::KINDS as $kind => $label) {
            $rule = self::rule($kind);
            $prefix = $kind.':'.$term->id.':';
            $dates = $rule['every'] > 0 && $rule['points'] > 0
                ? Attendance::where('student_id', $studentId)->where('status', $kind)->whereBetween('date', [$from, $to])->orderBy('date')->pluck('date')->map(fn ($d) => (string) ($d instanceof \DateTimeInterface ? $d->format('Y-m-d') : $d))->all()
                : [];
            $due = $rule['every'] > 0 ? intdiv(count($dates), $rule['every']) : 0;

            DB::transaction(function () use ($studentId, $prefix, $due, $dates, $rule, $label, $kind, $notify, &$changed) {
                $existing = BehaviorRecord::where('student_id', $studentId)->where('auto_key', 'like', $prefix.'%')->lockForUpdate()->get()
                    ->keyBy(fn ($r) => (int) substr($r->auto_key, strlen($prefix)));

                // เกินจำนวนที่ควรมี (แก้สถานะแล้วไม่ครบรอบ) = คืนคะแนน
                foreach ($existing as $n => $record) {
                    if ($n > $due) {
                        $record->delete();
                        $changed++;
                    }
                }
                for ($n = 1; $n <= $due; $n++) {
                    if ($existing->has($n)) {
                        continue;
                    }
                    $count = $n * $rule['every'];
                    BehaviorRecord::create([
                        'student_id' => $studentId,
                        'title' => $label.'ครบ '.$count.' ครั้ง (หักอัตโนมัติ)',
                        'points' => -$rule['points'],
                        'note' => 'ระบบหักให้เองตามเกณฑ์ '.$label.'ทุก '.$rule['every'].' ครั้ง หัก '.$rule['points'].' คะแนน',
                        'date' => $dates[$count - 1],
                        'auto_key' => $prefix.$n,
                    ]);
                    $changed++;
                    if ($notify && ($s = Student::find($studentId))) {
                        rescue(fn () => Notifier::parents($s, '⚠️ น้อง'.($s->nickname ?: $s->first_name).' ถูกหักคะแนนความประพฤติ: '.$label.'ครบ '.$count.' ครั้งในภาคเรียนนี้ (-'.$rule['points'].')'), null, true);
                    }
                }
            });
        }

        return $changed;
    }
}
