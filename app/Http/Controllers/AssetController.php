<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\User;
use App\Support\AssetNumber;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** ทะเบียนครุภัณฑ์ (งานพัสดุ) */
class AssetController extends Controller
{
    /** หัวคอลัมน์ที่รับได้ตอนนำเข้า (คัดลอกจาก Excel) */
    private const IMPORT_COLUMNS = [
        'code' => ['เลขครุภัณฑ์', 'รหัสครุภัณฑ์', 'หมายเลขครุภัณฑ์', 'code'],
        'name' => ['ชื่อ', 'ชื่อครุภัณฑ์', 'รายการ', 'name'],
        'category' => ['ประเภท', 'หมวด', 'category'],
        'brand' => ['ยี่ห้อ', 'รุ่น', 'ยี่ห้อ/รุ่น', 'brand'],
        'serial_no' => ['หมายเลขเครื่อง', 'serial', 'serial_no'],
        'acquired_on' => ['วันที่ได้มา', 'วันที่ซื้อ', 'acquired_on'],
        'price' => ['ราคา', 'ราคาต่อหน่วย', 'มูลค่า', 'price'],
        'budget_source' => ['แหล่งงบ', 'แหล่งเงิน', 'วิธีได้มา', 'budget_source'],
        'location' => ['สถานที่', 'ห้อง', 'สถานที่ตั้ง', 'location'],
        'note' => ['หมายเหตุ', 'note'],
    ];

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()->canManageFacilities(), 403, 'เฉพาะงานพัสดุ/อาคารสถานที่');
    }

    public function index(Request $request)
    {
        $this->authorizeManager($request);
        $query = Asset::with('responsible')
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('code', 'like', "%{$t}%")->orWhere('name', 'like', "%{$t}%")->orWhere('serial_no', 'like', "%{$t}%")))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->query('location'), fn ($q, $l) => $q->where('location', $l))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->inService())
            ->orderBy('location')->orderBy('code');

        if ($request->query('export') === 'csv') {
            return $this->csv($query->get());
        }

        return view('assets.index', [
            'assets' => $query->paginate(50)->withQueryString(),
            'locations' => self::locations(),
            'summary' => Asset::inService()->selectRaw('status, count(*) as n, sum(price) as total')->groupBy('status')->get()->keyBy('status'),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeManager($request);

        return view('assets.form', [
            'asset' => new Asset(['status' => 'normal', 'location' => $request->query('location'), 'category' => $request->query('category')]),
            'locations' => self::locations(), 'staff' => self::staff(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeManager($request);
        $data = $this->validated($request);
        $count = (int) ($data['quantity'] ?? 1);
        unset($data['quantity'], $data['photo']);
        if ($count > 1 && filled($data['code'] ?? null)) {
            throw ValidationException::withMessages(['code' => 'เพิ่มหลายชิ้นพร้อมกันให้เว้นเลขครุภัณฑ์ว่างไว้ ระบบจะออกเลขเรียงกันให้ทุกชิ้น']);
        }
        if ($count > 1) {
            $data['serial_no'] = null; // หมายเลขเครื่องไม่ซ้ำกัน ค่อยเติมรายชิ้น
        }

        // เว้นเลขว่าง = ออกเลขตามรูปแบบที่ตั้งไว้ (หลายชิ้นได้เลขเรียงกัน)
        $assets = blank($data['code'] ?? null)
            ? AssetNumber::assign($data['category'] ?? null, self::date($data['acquired_on'] ?? null), fn ($code) => Asset::create(['code' => $code] + $data), $count)
            : [Asset::create($data)];
        if ($request->hasFile('photo')) {
            $photo = $request->file('photo')->store('assets', 'public');
            foreach ($assets as $i => $asset) {
                // แต่ละชิ้นมีไฟล์รูปของตัวเอง (ลบ/เปลี่ยนรูปชิ้นหนึ่งไม่กระทบชิ้นอื่น)
                $path = $i === 0 ? $photo : 'assets/'.Str::random(40).'.'.pathinfo($photo, PATHINFO_EXTENSION);
                $i === 0 || Storage::disk('public')->copy($photo, $path);
                $asset->update(['photo' => $path]);
            }
        }
        $first = $assets[0];
        $last = end($assets);
        $range = $count > 1 ? "{$first->code} ถึง {$last->code}" : $first->code;
        Audit::log('asset.create', $first, "เพิ่มครุภัณฑ์ {$range} {$first->name}".($count > 1 ? " {$count} รายการ" : ''));

        if ($request->boolean('another')) {
            return redirect()->route('assets.create', ['location' => $first->location, 'category' => $first->category])->with('success', "บันทึก {$range} แล้ว");
        }
        if ($count > 1) {
            return redirect()->route('assets.index')->with('success', "เพิ่ม {$first->name} {$count} รายการ เลข {$range}")
                ->with('created_ids', collect($assets)->pluck('id')->all());
        }

        return redirect()->route('assets.show', $first)->with('success', "บันทึก {$first->code} แล้ว");
    }

    public function show(Request $request, Asset $asset)
    {
        $asset->load(['responsible', 'repairs.reporter', 'checks.checker']);

        // ครูทั่วไปที่สแกน QR เห็นข้อมูลพื้นฐาน + ปุ่มแจ้งซ่อม · งานพัสดุเห็นมูลค่าและประวัติทั้งหมด
        return view('assets.show', ['asset' => $asset, 'manager' => $request->user()->canManageFacilities()]);
    }

    public function edit(Request $request, Asset $asset)
    {
        $this->authorizeManager($request);

        return view('assets.form', ['asset' => $asset, 'locations' => self::locations(), 'staff' => self::staff()]);
    }

    public function update(Request $request, Asset $asset)
    {
        $this->authorizeManager($request);
        $data = $this->validated($request, $asset);
        unset($data['photo']);
        if ($request->hasFile('photo')) {
            $asset->photo && Storage::disk('public')->delete($asset->photo);
            $data['photo'] = $request->file('photo')->store('assets', 'public');
        }
        if (($data['status'] ?? null) === 'disposed' && ! $asset->disposed_on && empty($data['disposed_on'])) {
            $data['disposed_on'] = today();
        }
        unset($data['quantity']);
        $asset->fill($data);
        $save = function () use ($asset) {
            if ($diff = Audit::diff($asset)) {
                Audit::log('asset.update', $asset, "แก้ครุภัณฑ์ {$asset->code} {$asset->name}", $diff);
            }
            $asset->save();
        };
        // ลบเลขเดิมออก = ออกเลขใหม่ตามรูปแบบปัจจุบัน
        blank($asset->code)
            ? AssetNumber::assign($asset->category, $asset->acquired_on, function ($code) use ($asset, $save) {
                $asset->code = $code;
                $save();
            })
            : $save();

        return redirect()->route('assets.show', $asset)->with('success', 'บันทึกแล้ว');
    }

    public function destroy(Request $request, Asset $asset)
    {
        $this->authorizeManager($request);
        // ครุภัณฑ์ที่มีประวัติซ่อม/ตรวจสอบแล้ว ต้องทำเรื่องจำหน่าย ไม่ใช่ลบ
        if ($asset->repairs()->exists() || $asset->checks()->exists()) {
            return back()->withErrors(['asset' => 'ครุภัณฑ์นี้มีประวัติการซ่อมหรือการตรวจสอบแล้ว ลบไม่ได้ — เปลี่ยนสถานะเป็น "รอจำหน่าย" หรือ "จำหน่ายแล้ว" แทน']);
        }
        Audit::log('asset.delete', $asset, "ลบครุภัณฑ์ {$asset->code} {$asset->name} (บันทึกผิด)");
        $asset->photo && Storage::disk('public')->delete($asset->photo);
        $asset->delete();

        return redirect()->route('assets.index')->with('success', 'ลบแล้ว');
    }

    /** เปิดจาก QR บนสติกเกอร์ */
    public function go(Request $request, string $token)
    {
        $asset = Asset::where('qr_token', $token)->firstOrFail();

        return redirect()->route('assets.show', $asset);
    }

    /** พิมพ์สติกเกอร์ QR ตามตัวกรองเดียวกับหน้ารายการ */
    public function labels(Request $request)
    {
        $this->authorizeManager($request);
        $assets = Asset::inService()
            ->when($request->query('location'), fn ($q, $l) => $q->where('location', $l))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->query('ids'), fn ($q, $ids) => $q->whereIn('id', array_map('intval', explode(',', $ids))))
            ->orderBy('location')->orderBy('code')->get();

        return view('assets.labels', compact('assets'));
    }

    public function importForm(Request $request)
    {
        $this->authorizeManager($request);

        return view('assets.import', ['columns' => self::IMPORT_COLUMNS]);
    }

    /** นำเข้าจากการคัดลอกตาราง Excel (แถวแรกเป็นหัวคอลัมน์) · เลขซ้ำ = อัปเดต */
    public function import(Request $request)
    {
        $this->authorizeManager($request);
        $request->validate(['data' => ['required', 'string']], [], ['data' => 'ข้อมูล']);
        $rows = array_values(array_filter(array_map(fn ($l) => str_getcsv($l, "\t"), preg_split('/\r\n|\n|\r/', trim($request->input('data')))), fn ($r) => array_filter($r, 'strlen')));
        $header = array_map('trim', array_shift($rows));
        $map = [];
        foreach ($header as $i => $h) {
            foreach (self::IMPORT_COLUMNS as $key => $names) {
                if (in_array(mb_strtolower($h), array_map('mb_strtolower', $names), true)) {
                    $map[$key] = $i;
                }
            }
        }
        if (! isset($map['name'])) {
            return back()->withInput()->withErrors(['data' => 'ต้องมีคอลัมน์ "ชื่อครุภัณฑ์" ในแถวแรก']);
        }

        $created = $updated = 0;
        $errors = [];
        DB::transaction(function () use ($rows, $map, &$created, &$updated, &$errors) {
            foreach ($rows as $n => $row) {
                $get = fn ($k) => isset($map[$k]) ? trim((string) ($row[$map[$k]] ?? '')) : '';
                if ($get('name') === '') {
                    $errors[] = 'แถว '.($n + 2).': ไม่มีชื่อครุภัณฑ์';

                    continue;
                }
                $values = array_filter([
                    'name' => $get('name'), 'category' => $get('category'), 'brand' => $get('brand'), 'serial_no' => $get('serial_no'),
                    'budget_source' => $get('budget_source'), 'location' => $get('location'), 'note' => $get('note'),
                    'price' => $get('price') !== '' ? (float) str_replace(',', '', $get('price')) : null,
                    'acquired_on' => self::parseDate($get('acquired_on')),
                ], fn ($v) => $v !== null && $v !== '');
                // ไม่มีเลข = ออกเลขให้ตามรูปแบบ
                if ($get('code') === '') {
                    AssetNumber::assign($values['category'] ?? null, self::date($values['acquired_on'] ?? null), fn ($code) => Asset::create(['code' => $code, 'status' => 'normal'] + $values));
                    $created++;

                    continue;
                }
                $asset = Asset::firstOrNew(['code' => $get('code')]);
                $asset->exists ? $updated++ : $created++;
                $asset->fill($values + ['status' => $asset->status ?? 'normal'])->save();
            }
        });
        Audit::log('asset.import', null, "นำเข้าครุภัณฑ์ ใหม่ {$created} อัปเดต {$updated} รายการ");

        return redirect()->route('assets.index')->with('success', "นำเข้าแล้ว: ใหม่ {$created} · อัปเดต {$updated}".($errors ? ' · ข้าม '.count($errors).' แถว' : ''))
            ->withErrors($errors ? ['import' => implode(' · ', array_slice($errors, 0, 5))] : []);
    }

    /** รองรับ 2026-05-16 · 16/5/2569 · 16/05/2026 */
    private static function parseDate(string $v): ?string
    {
        if ($v === '') {
            return null;
        }
        if (preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/', $v, $m)) {
            $year = (int) $m[3] > 2400 ? (int) $m[3] - 543 : (int) $m[3];

            return checkdate((int) $m[2], (int) $m[1], $year) ? sprintf('%04d-%02d-%02d', $year, $m[2], $m[1]) : null;
        }

        return strtotime($v) ? date('Y-m-d', strtotime($v)) : null;
    }

    private function csv($assets)
    {
        return response()->streamDownload(function () use ($assets) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['เลขครุภัณฑ์', 'ชื่อครุภัณฑ์', 'ประเภท', 'ยี่ห้อ/รุ่น', 'หมายเลขเครื่อง', 'วันที่ได้มา', 'ราคา', 'แหล่งงบ', 'สถานที่', 'ผู้รับผิดชอบ', 'อายุใช้งาน (ปี)', 'ค่าเสื่อมสะสม', 'มูลค่าสุทธิ', 'สถานะ']);
            foreach ($assets as $a) {
                fputcsv($out, [$a->code, $a->name, $a->category, $a->brand, $a->serial_no, $a->acquired_on?->toDateString(), $a->price, $a->budget_source,
                    $a->location, $a->responsible?->name, $a->life(), $a->accumulatedDepreciation(), $a->bookValue(), $a->statusLabel()]);
            }
            fclose($out);
        }, 'ครุภัณฑ์-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validated(Request $request, ?Asset $asset = null): array
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:50', Rule::unique('assets')->ignore($asset?->id)],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:200'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:50'],
            'brand' => ['nullable', 'string', 'max:255'],
            'serial_no' => ['nullable', 'string', 'max:100'],
            'acquired_on' => ['nullable', 'date'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'budget_source' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:100'],
            'responsible_id' => ['nullable', 'exists:users,id'],
            'useful_life' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['required', Rule::in(array_keys(Asset::STATUSES))],
            'disposed_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:8192'],
        ], [], ['code' => 'เลขครุภัณฑ์', 'name' => 'ชื่อครุภัณฑ์', 'quantity' => 'จำนวน']);
        $data['price'] ??= 0;

        return $data;
    }

    /** เลขที่จะได้ถ้าเว้นช่องเลขว่าง (แสดงในฟอร์มเพิ่มครุภัณฑ์) */
    public function nextNumber(Request $request)
    {
        $this->authorizeManager($request);
        $data = $request->validate(['category' => ['nullable', 'string', 'max:50'], 'acquired_on' => ['nullable', 'date'], 'quantity' => ['nullable', 'integer', 'min:1', 'max:200']]);
        $codes = AssetNumber::next($data['category'] ?? null, self::date($data['acquired_on'] ?? null), (int) ($data['quantity'] ?? 1));

        return response()->json(['first' => $codes[0], 'last' => end($codes), 'count' => count($codes),
            'needs_category' => blank($data['category'] ?? null) && str_contains(AssetNumber::pattern(), '{CAT}')]);
    }

    private static function date(mixed $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    /** ห้อง/สถานที่ที่เคยใช้ (ใช้เป็นตัวเลือก) */
    public static function locations()
    {
        return Asset::whereNotNull('location')->distinct()->orderBy('location')->pluck('location');
    }

    private static function staff()
    {
        return User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->orderBy('name')->get();
    }
}
