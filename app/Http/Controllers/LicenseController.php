<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\License\Client;
use App\Support\License\Guard;
use App\Support\License\Integrity;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** License ของระบบ: เปิดใช้งานด้วยคีย์ ขอทดลองใช้ และตรวจสอบกับศูนย์ License (เฉพาะผู้ดูแลระบบ) */
class LicenseController extends Controller
{
    public function activate(Request $request): RedirectResponse
    {
        $this->owner($request);
        $data = $request->validate([
            'license_key' => ['required', 'string', 'min:10', 'max:50'],
        ], [], ['license_key' => 'คีย์ License']);

        $result = Client::activate($data['license_key']);
        Guard::flush();

        if (! $result['ok']) {
            return $this->toWorkspace()->withErrors(['license_key' => $result['message']]);
        }

        Audit::log('setting.license', null, 'เปิดใช้งาน License '.strtoupper(trim($data['license_key'])));

        return $this->toWorkspace()->with('success', $result['message']);
    }

    public function trial(Request $request): RedirectResponse
    {
        $this->owner($request);

        $result = Client::trial(null, (string) Settings::get('school_name'));
        Guard::flush();

        if (! $result['ok']) {
            return $this->toWorkspace()->withErrors(['license_key' => $result['message']]);
        }

        Audit::log('setting.license', null, 'เริ่มทดลองใช้งาน '.config('license.trial_days').' วัน');

        return $this->toWorkspace()->with('success', $result['message']);
    }

    public function refresh(Request $request): RedirectResponse
    {
        $this->owner($request);

        $result = Client::refresh();
        Guard::flush();

        return $result['ok']
            ? $this->toWorkspace()->with('success', $result['message'])
            : $this->toWorkspace()->withErrors(['license_key' => $result['message']]);
    }

    /** ตรวจไฟล์ของระบบใหม่เดี๋ยวนี้ */
    public function integrity(Request $request): RedirectResponse
    {
        $this->owner($request);

        Integrity::flush();
        Guard::flush();
        $status = Integrity::status();

        $message = match ($status['state']) {
            'ok' => 'ไฟล์ครบและตรงกับชุดที่ผู้พัฒนาส่งมา ('.$status['checked'].' ไฟล์)',
            'changed' => 'มีไฟล์ถูกแก้ '.count($status['changed']).' ไฟล์ ระบบยังใช้งานได้ แต่จะรายงานให้ผู้พัฒนาทราบ',
            'tampered' => 'ไฟล์สำคัญของระบบถูกแก้ '.count($status['critical']).' ไฟล์',
            default => (string) $status['reason'],
        };

        return $status['state'] === 'ok'
            ? $this->toWorkspace()->with('success', $message)
            : $this->toWorkspace()->withErrors(['integrity' => $message]);
    }

    protected function owner(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'จัดการ License ได้เฉพาะผู้ดูแลระบบ');
    }
}
