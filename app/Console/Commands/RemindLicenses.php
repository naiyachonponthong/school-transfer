<?php

namespace App\Console\Commands;

use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Console\Command;

/**
 * เตือนใบอนุญาตประกอบวิชาชีพใกล้หมดอายุ: เตือนเจ้าตัวและฝ่ายบุคคลเมื่อเหลือ 90, 30 และ 7 วัน (ช่วงละ 1 ครั้ง)
 */
class RemindLicenses extends Command
{
    public const STAGES = [90, 30, 7];

    protected $signature = 'staff:license-remind';

    protected $description = 'เตือนใบอนุญาตประกอบวิชาชีพครูใกล้หมดอายุทาง LINE';

    public function handle(): int
    {
        Notifier::viaQueue();
        $managers = User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->whereNotNull('line_user_id')->get()
            ->filter(fn (User $u) => $u->hasPermission('staff.manage'));
        $sent = 0;

        $profiles = StaffProfile::with('user')->whereNotNull('license_expires_on')
            ->whereDate('license_expires_on', '>=', today())->whereDate('license_expires_on', '<=', today()->addDays(max(self::STAGES)))->get();

        foreach ($profiles as $p) {
            if (! $p->user?->is_active) {
                continue;
            }
            $days = $p->licenseDaysLeft();
            // ช่วงเตือนปัจจุบัน = ค่าที่เล็กที่สุดที่ยังครอบคลุมจำนวนวันที่เหลือ
            $stage = collect(self::STAGES)->filter(fn ($s) => $days <= $s)->min();
            $stageStart = $p->license_expires_on->copy()->subDays($stage);
            if ($p->license_reminded_on && $p->license_reminded_on->gte($stageStart)) {
                continue; // ช่วงนี้เตือนไปแล้ว
            }
            $text = "🪪 ใบอนุญาตประกอบวิชาชีพของ {$p->user->name} จะหมดอายุวันที่ ".thai_date($p->license_expires_on)." (อีก {$days} วัน)";
            Notifier::users($managers->push($p->user)->unique('id'), $text, route('staff.show', $p->user));
            StaffProfile::whereKey($p->id)->update(['license_reminded_on' => today()->toDateString()]);
            $sent++;
        }

        $this->info("เตือนแล้ว {$sent} คน");

        return self::SUCCESS;
    }
}
