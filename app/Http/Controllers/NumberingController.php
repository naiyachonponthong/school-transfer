<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Supply;
use App\Support\AssetNumber;
use App\Support\Audit;
use App\Support\CodeSeries;
use App\Support\SupplyNumber;
use Illuminate\Http\Request;

/** ตั้งรูปแบบเลขอัตโนมัติ + รหัสหมวด (ครุภัณฑ์ / วัสดุ) — งานพัสดุ */
class NumberingController extends Controller
{
    private const KINDS = [
        'assets' => [
            'series' => AssetNumber::class, 'model' => Asset::class, 'title' => 'รูปแบบเลขครุภัณฑ์', 'item' => 'ครุภัณฑ์', 'cat' => 'ประเภท',
            'example' => 'ครุภัณฑ์คอมพิวเตอร์', 'index' => 'assets.index', 'create' => 'assets.create', 'update' => 'assets.numbering.update',
            'max' => 40, 'note' => 'ใส่ปีในรูปแบบ = เริ่มนับใหม่ทุกปี (ปีนับจากวันที่ได้มา) · ประเภทที่ไม่ได้เลือกใช้รหัสของ "ครุภัณฑ์อื่น ๆ"',
        ],
        'supplies' => [
            'series' => SupplyNumber::class, 'model' => Supply::class, 'title' => 'รูปแบบรหัสวัสดุ', 'item' => 'วัสดุ', 'cat' => 'หมวด',
            'example' => 'วัสดุสำนักงาน', 'index' => 'supplies.index', 'create' => 'supplies.create', 'update' => 'supplies.numbering.update',
            'max' => 20, 'note' => 'หมวดที่พิมพ์ขึ้นใหม่ในหน้าวัสดุจะมาอยู่ในตารางนี้ให้ตั้งรหัสได้ · หมวดที่ยังไม่ตั้งรหัสใช้รหัสของ "วัสดุอื่น ๆ"',
        ],
    ];

    public function show(Request $request, string $kind)
    {
        abort_unless($request->user()->canManageFacilities(), 403);
        $k = self::KINDS[$kind];
        /** @var class-string<CodeSeries> $series */
        $series = $k['series'];
        $categories = $series::categories();

        return view('numbering.edit', [
            'k' => $k, 'series' => $series, 'categories' => $categories,
            'pattern' => $series::pattern(), 'codes' => $series::codes(),
            'counts' => ($k['model'])::selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category'),
            'next' => collect($categories)->mapWithKeys(fn ($c) => [$c => $series::next($c)[0]]),
            'fy' => $series::fiscalYear(today()),
        ]);
    }

    public function save(Request $request, string $kind)
    {
        abort_unless($request->user()->canManageFacilities(), 403);
        $k = self::KINDS[$kind];
        $series = $k['series'];
        $data = $request->validate([
            'pattern' => ['required', 'string', 'max:'.$k['max'], function ($attr, $value, $fail) use ($series) {
                ($problem = $series::problem($value)) && $fail($problem);
            }],
            'codes' => ['required', 'array'],
            'codes.*' => ['required', 'string', 'max:10', 'regex:/^[\pL\pN.\-\/]+$/u'],
        ], ['codes.*.required' => 'ใส่รหัสให้ครบทุก'.$k['cat'], 'codes.*.regex' => 'รหัสใช้ได้เฉพาะตัวอักษร ตัวเลข . - /'], ['pattern' => 'รูปแบบเลข']);
        $codes = collect($data['codes'])->only($series::categories())->map(fn ($c) => trim($c))->all();
        if ($changes = $series::save(trim($data['pattern']), $codes)) {
            Audit::log('setting.update', null, 'แก้'.$k['title'].' '.trim($data['pattern']), $changes);
        }

        return redirect()->route(str_replace('.update', '', $k['update']))->with('success', 'บันทึก'.$k['title'].'แล้ว');
    }
}
