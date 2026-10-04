<?php

namespace App\Http\Controllers;

use App\Models\HomeVisit;
use App\Models\LeaveRequest;
use App\Models\Message;
use App\Models\OfficeDocument;
use App\Models\PaymentSlip;
use App\Models\StaffLeave;
use App\Models\StaffTraining;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * ไฟล์แนบที่มีข้อมูลส่วนบุคคล (สลิป ใบรับรองแพทย์ รูปในแชท งานที่ส่ง) เก็บในดิสก์ส่วนตัว
 * เปิดได้ผ่านหน้านี้เท่านั้น และเฉพาะคนที่เกี่ยวข้องกับเอกสารนั้น
 */
class FileController extends Controller
{
    /** type => [model, คอลัมน์ที่เก็บ path] */
    public const TYPES = [
        'slip' => [PaymentSlip::class, 'image'],
        'wallet-slip' => [\App\Models\WalletTopup::class, 'slip'],
        'leave' => [LeaveRequest::class, 'attachment'],
        'staff-leave' => [StaffLeave::class, 'attachment'],
        'chat' => [Message::class, 'attachment'],
        'submission' => [Submission::class, 'file'],
        'home-visit' => [HomeVisit::class, 'photo'],
        'home-visit-inside' => [HomeVisit::class, 'photo_inside'],
        'home-visit-sign-guardian' => [HomeVisit::class, 'sign_guardian'],
        'home-visit-sign-visitor' => [HomeVisit::class, 'sign_visitor'],
        'training' => [StaffTraining::class, 'file'],
        'office-doc' => [OfficeDocument::class, 'file'],
    ];

    public function show(Request $request, string $type, int $id)
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        [$class, $column] = self::TYPES[$type];
        $model = $class::findOrFail($id);
        abort_unless($this->allowed($request->user(), $type, $model), 403);

        $path = $model->{$column};
        // ไฟล์ที่อัปโหลดก่อนย้ายดิสก์ยังอยู่ใน public จนกว่าจะรัน `php artisan files:privatize`
        $disk = collect(['local', 'public'])->first(fn ($d) => $path && Storage::disk($d)->exists($path));
        abort_unless($disk, 404);

        return response()->file(Storage::disk($disk)->path($path), ['Cache-Control' => 'private, max-age=3600']);
    }

    private function allowed(User $user, string $type, $model): bool
    {
        return match ($type) {
            'slip' => $user->hasPermission('finance.view') || $model->invoice->student->isGuardedBy($user),
            'wallet-slip' => $user->hasPermission('wallet.manage') || $model->wallet->student->isGuardedBy($user),
            'leave' => $model->student->canBeViewedBy($user),
            'staff-leave' => $user->hasPermission('staff.manage') || $model->user_id === $user->id,
            'chat' => $model->conversation->hasParticipant($user),
            'submission' => $model->student->canBeViewedBy($user),
            'training' => $user->hasPermission('staff.manage') || $model->user_id === $user->id,
            'office-doc' => $user->isStaff() && $model->canBeViewedBy($user),
            // รูปเยี่ยมบ้าน: เฉพาะครูประจำชั้นและผู้มีสิทธิ์ดูแลช่วยเหลือ ผู้ปกครองเปิดไม่ได้
            'home-visit', 'home-visit-inside', 'home-visit-sign-guardian', 'home-visit-sign-visitor' => $user->hasPermission('care.manage') || $user->myClassrooms()->contains('id', $model->student->classroom_id),
            default => false,
        };
    }
}
