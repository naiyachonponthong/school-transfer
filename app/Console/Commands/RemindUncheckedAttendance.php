<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\SchoolEvent;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Console\Command;

/** เตือนครูประจำชั้นที่ยังไม่ได้เช็คชื่อวันนี้ (ข้ามเสาร์-อาทิตย์และวันหยุดในปฏิทินโรงเรียน) */
class RemindUncheckedAttendance extends Command
{
    protected $signature = 'attendance:remind-unchecked';

    protected $description = 'เตือนครูประจำชั้นที่ยังไม่เช็คชื่อวันนี้ทาง LINE';

    public function handle(): int
    {
        Notifier::viaQueue();
        $today = today()->toDateString();
        if (today()->isWeekend() || SchoolEvent::where('type', 'holiday')->overlapping($today, $today)->exists()) {
            $this->info('วันนี้เป็นวันหยุด ไม่ส่งเตือน');

            return self::SUCCESS;
        }

        $checked = Attendance::where('date', $today)->distinct()->pluck('classroom_id')->filter()->all();
        $rooms = Classroom::currentYear()->whereNotIn('id', $checked)->whereHas('students')->ordered()->get();

        foreach ($rooms as $room) {
            $teachers = User::whereIn('id', array_filter([$room->homeroom_teacher_id, $room->co_teacher_id]))
                ->where('is_active', true)->whereNotNull('line_user_id')->get();
            if ($teachers->isNotEmpty()) {
                Notifier::users($teachers, "⏰ ห้อง {$room->name()} ยังไม่ได้เช็คชื่อวันนี้ ผู้ปกครองจะเห็นสถานะหลังครูเช็คชื่อ", route('attendance.index', ['classroom' => $room->id]));
            }
        }

        $this->info("ยังไม่เช็คชื่อ {$rooms->count()} ห้อง");

        return self::SUCCESS;
    }
}
