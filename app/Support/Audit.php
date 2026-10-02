<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * จุดเดียวสำหรับบันทึกประวัติการกระทำสำคัญ
 * action ใช้รูปแบบ "หมวด.การกระทำ" เช่น grade.remedial, finance.void (หมวดดู AuditLog::GROUPS)
 */
class Audit
{
    public static function log(string $action, ?Model $subject, string $description, array $changes = []): AuditLog
    {
        $user = auth()->user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => mb_substr($description, 0, 500),
            'changes' => $changes ?: null,
            'ip' => request()?->ip(),
        ]);
    }

    /** ค่าเดิม → ค่าใหม่ เฉพาะช่องที่เปลี่ยน (ใช้ก่อน save โดยส่ง model ที่ fill แล้ว) */
    public static function diff(Model $model, array $hidden = ['password', 'remember_token']): array
    {
        $out = [];
        foreach ($model->getDirty() as $key => $new) {
            if (in_array($key, $hidden, true) || in_array($key, ['updated_at', 'created_at'], true)) {
                continue;
            }
            $out[$key] = [$model->getOriginal($key), $new];
        }

        return $out;
    }
}
