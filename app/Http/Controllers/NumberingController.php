<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\BookCopy;
use App\Models\Supply;
use App\Support\AccessionNumber;
use App\Support\AssetNumber;
use App\Support\Audit;
use App\Support\BookBarcode;
use App\Support\CodeSeries;
use App\Support\SupplyNumber;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** ตั้งรูปแบบเลขอัตโนมัติ + รหัสหมวด (ครุภัณฑ์ / วัสดุ = งานพัสดุ · บาร์โค้ด/เลขทะเบียนหนังสือ = ห้องสมุด) */
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
        'library-barcode' => [
            'series' => BookBarcode::class, 'model' => BookCopy::class, 'title' => 'รูปแบบบาร์โค้ดหนังสือ', 'item' => 'หนังสือ', 'cat' => '',
            'example' => null, 'index' => 'library.index', 'create' => 'library.create', 'update' => 'library.numbering.update', 'params' => ['kind' => 'library-barcode'],
            'max' => 20, 'ascii' => true, 'staff' => true,
            'note' => 'บาร์โค้ดติดปกนอก/ปกใน ใช้ยิงยืม-คืน · ใช้ได้เฉพาะตัวอักษรอังกฤษและตัวเลข (Code 128) · เล่มเดิมที่มีบาร์โค้ดแล้วไม่เปลี่ยน',
        ],
        'library-accession' => [
            'series' => AccessionNumber::class, 'model' => BookCopy::class, 'title' => 'รูปแบบเลขทะเบียนหนังสือ', 'item' => 'หนังสือ', 'cat' => '',
            'example' => null, 'index' => 'library.index', 'create' => 'library.create', 'update' => 'library.numbering.update', 'params' => ['kind' => 'library-accession'],
            'max' => 20, 'staff' => true,
            'note' => 'เลขทะเบียนลงให้ทุกเล่มที่รับเข้าห้องสมุด เรียงต่อเนื่อง (พิมพ์บนบาร์โค้ดปกใน) · ใส่ปีในรูปแบบ = เริ่มนับใหม่ทุกปี',
        ],
    ];

    private static function authorize(Request $request, array $k): void
    {
        $user = $request->user();
        abort_unless(! empty($k['staff']) ? $user->isAdmin() || $user->isTeacher() : $user->canManageFacilities(), 403);
    }

    public function show(Request $request, string $kind)
    {
        $k = self::KINDS[$kind];
        self::authorize($request, $k);
        /** @var class-string<CodeSeries> $series */
        $series = $k['series'];
        $categories = $series::categories();

        return view('numbering.edit', [
            'k' => $k, 'series' => $series, 'categories' => $categories,
            'pattern' => $series::pattern(), 'codes' => $series::codes(),
            'counts' => $categories ? ($k['model'])::selectRaw('category, count(*) as n')->groupBy('category')->pluck('n', 'category') : collect(),
            'nextPlain' => $categories ? null : $series::next()[0],
            'next' => collect($categories)->mapWithKeys(fn ($c) => [$c => $series::next($c)[0]]),
            'fy' => $series::fiscalYear(today()),
        ]);
    }

    public function save(Request $request, string $kind)
    {
        $k = self::KINDS[$kind];
        self::authorize($request, $k);
        $series = $k['series'];
        $data = $request->validate([
            'pattern' => ['required', 'string', 'max:'.$k['max'], function ($attr, $value, $fail) use ($series) {
                ($problem = $series::problem($value)) && $fail($problem);
            }],
            'codes' => [$series::categories() ? 'required' : 'nullable', 'array'],
            'codes.*' => ['required', 'string', 'max:10', 'regex:/^[\pL\pN.\-\/]+$/u'],
        ], ['codes.*.required' => 'ใส่รหัสให้ครบทุก'.$k['cat'], 'codes.*.regex' => 'รหัสใช้ได้เฉพาะตัวอักษร ตัวเลข . - /'], ['pattern' => 'รูปแบบเลข']);
        if (! empty($k['ascii']) && preg_match('/[^\x20-\x7E]/', $data['pattern'])) {
            throw ValidationException::withMessages(['pattern' => 'บาร์โค้ดใช้ได้เฉพาะตัวอักษรอังกฤษ ตัวเลข และสัญลักษณ์ (เครื่องอ่านบาร์โค้ดอ่านภาษาไทยไม่ได้)']);
        }
        $codes = collect($data['codes'] ?? [])->only($series::categories())->map(fn ($c) => trim($c))->all();
        if ($changes = $series::save(trim($data['pattern']), $codes)) {
            Audit::log('setting.update', null, 'แก้'.$k['title'].' '.trim($data['pattern']), $changes);
        }

        return redirect()->route(str_replace('.update', '', $k['update']), $k['params'] ?? [])->with('success', 'บันทึก'.$k['title'].'แล้ว');
    }
}
