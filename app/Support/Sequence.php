<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * เลขที่เอกสารแบบไม่ชนกันเมื่อออกพร้อมกันหลายเครื่อง
 * ล็อกแถวของชุดเลขนั้นไว้จนจบ transaction ของผู้เรียก คนที่มาทีหลังจึงรอแล้วได้เลขถัดไป
 */
class Sequence
{
    /**
     * @param  Closure():int  $seed  เลขล่าสุดที่ใช้ไปแล้วก่อนมีตารางนี้ (เรียกครั้งแรกของแต่ละชุดเท่านั้น)
     */
    public static function next(string $key, string $period, Closure $seed): int
    {
        return DB::transaction(function () use ($key, $period, $seed) {
            $where = ['key' => $key, 'period' => $period];
            if (! DB::table('document_sequences')->where($where)->exists()) {
                DB::table('document_sequences')->insertOrIgnore($where + ['last_no' => $seed()]);
            }
            $last = (int) DB::table('document_sequences')->where($where)->lockForUpdate()->value('last_no');
            DB::table('document_sequences')->where($where)->update(['last_no' => $last + 1]);

            return $last + 1;
        });
    }
}
