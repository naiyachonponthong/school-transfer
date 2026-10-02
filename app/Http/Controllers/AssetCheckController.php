<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCheck;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** ตรวจสอบพัสดุประจำปี: เดินสแกน QR ทีละห้อง → รายงานพบ / ชำรุด / ไม่พบ / ยังไม่ตรวจ */
class AssetCheckController extends Controller
{
    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()->canManageFacilities(), 403, 'เฉพาะงานพัสดุ/คณะกรรมการตรวจสอบพัสดุ');
    }

    private static function year(Request $request): int
    {
        return (int) ($request->input('year') ?: today()->year + 543);
    }

    public function index(Request $request)
    {
        $this->authorizeManager($request);
        $year = self::year($request);
        $assets = Asset::inService()->when($request->query('location'), fn ($q, $l) => $q->where('location', $l))
            ->orderBy('location')->orderBy('code')->get();
        $checks = AssetCheck::with('checker')->where('year', $year)->whereIn('asset_id', $assets->pluck('id'))->get()->keyBy('asset_id');

        return view('asset-checks.index', [
            'year' => $year,
            'assets' => $assets,
            'checks' => $checks,
            'locations' => AssetController::locations(),
            'counts' => collect(AssetCheck::RESULTS)->map(fn ($r, $k) => $checks->where('result', $k)->count())->put('unchecked', $assets->count() - $checks->count()),
        ]);
    }

    public function scan(Request $request)
    {
        $this->authorizeManager($request);

        return view('asset-checks.scan', ['year' => self::year($request)]);
    }

    /** บันทึกผลหนึ่งชิ้น (จากการสแกน = JSON หรือจากหน้ารายงาน = ฟอร์ม) */
    public function record(Request $request)
    {
        $this->authorizeManager($request);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255'],
            'result' => ['required', Rule::in(array_keys(AssetCheck::RESULTS))],
            'year' => ['required', 'integer', 'between:2500,2700'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        // QR บนสติกเกอร์เป็นลิงก์ .../a/{token} · เครื่องอ่าน/พิมพ์เอง = เลขครุภัณฑ์
        $code = trim($data['code']);
        $token = preg_match('~/a/([A-Za-z0-9]+)/?$~', $code, $m) ? $m[1] : $code;
        $asset = Asset::where('qr_token', $token)->orWhere('code', $code)->first();
        if (! $asset) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'ไม่พบครุภัณฑ์ '.$code], 404)
                : back()->withErrors(['code' => 'ไม่พบครุภัณฑ์ '.$code]);
        }

        $check = AssetCheck::updateOrCreate(['year' => $data['year'], 'asset_id' => $asset->id],
            ['result' => $data['result'], 'note' => $data['note'] ?? null, 'checked_by' => $request->user()->id]);
        if ($check->wasRecentlyCreated || $check->wasChanged('result')) {
            Audit::log('asset.check', $asset, "ตรวจสอบพัสดุปี {$data['year']}: {$asset->code} {$asset->name} = ".AssetCheck::RESULTS[$data['result']][0]);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'asset' => [
                'code' => $asset->code, 'name' => $asset->name, 'location' => $asset->location, 'status' => $asset->statusLabel(),
            ], 'result' => AssetCheck::RESULTS[$data['result']][0], 'color' => AssetCheck::RESULTS[$data['result']][1]]);
        }

        return back()->with('success', "{$asset->code}: ".AssetCheck::RESULTS[$data['result']][0]);
    }
}
