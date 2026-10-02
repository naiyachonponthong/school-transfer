<?php

namespace App\Http\Controllers;

use App\Models\Supply;
use App\Models\SupplyRequisition;
use App\Models\SupplyTransaction;
use App\Support\Audit;
use App\Support\SupplyNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** คลังวัสดุสิ้นเปลือง (งานพัสดุ): รายการ · รับเข้า · ปรับยอด · บัญชีวัสดุ · สรุปการเบิกรายเดือน */
class SupplyController extends Controller
{
    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()->canManageFacilities(), 403, 'เฉพาะงานพัสดุ');
    }

    public function index(Request $request)
    {
        $this->authorizeManager($request);
        $supplies = Supply::query()
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('code', 'like', "%{$t}%")))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($request->query('low'), fn ($q) => $q->whereColumn('stock', '<=', 'min_stock')->where('min_stock', '>', 0))
            ->orderByDesc('is_active')->orderBy('category')->orderBy('name')->get();

        return view('supplies.index', [
            'supplies' => $supplies,
            'lowCount' => Supply::where('is_active', true)->whereColumn('stock', '<=', 'min_stock')->where('min_stock', '>', 0)->count(),
            'pending' => SupplyRequisition::where('status', 'pending')->count(),
            'categories' => Supply::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'totalValue' => Supply::where('is_active', true)->get()->sum(fn ($s) => $s->value()),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeManager($request);

        return view('supplies.form', ['supply' => new Supply(['unit' => 'ชิ้น', 'is_active' => true]), 'categories' => self::categories()]);
    }

    public function edit(Request $request, Supply $supply)
    {
        $this->authorizeManager($request);

        return view('supplies.form', ['supply' => $supply, 'categories' => self::categories()]);
    }

    public function store(Request $request)
    {
        $this->authorizeManager($request);
        $data = $this->validated($request);
        $data['photo'] = $request->hasFile('photo') ? $request->file('photo')->store('supplies', 'public') : null;
        $initial = (int) $request->input('initial_stock', 0);
        // เว้นรหัสว่าง = ออกรหัสตามรูปแบบที่ตั้งไว้
        $supply = blank($data['code'] ?? null)
            ? SupplyNumber::assign($data['category'] ?? null, null, fn ($code) => Supply::create(['code' => $code, 'stock' => 0] + $data))[0]
            : Supply::create($data + ['stock' => 0]);
        if ($initial > 0) {
            $supply->move('in', $initial, 'ยอดยกมา');
        }
        Audit::log('supply.create', $supply, "เพิ่มวัสดุ {$supply->code} {$supply->name}".($initial ? " ยอดยกมา {$initial} {$supply->unit}" : ''));

        return redirect()->route($request->boolean('another') ? 'supplies.create' : 'supplies.index')->with('success', "เพิ่ม {$supply->code} {$supply->name} แล้ว");
    }

    public function update(Request $request, Supply $supply)
    {
        $this->authorizeManager($request);
        $data = $this->validated($request, $supply) + ['is_active' => $request->boolean('is_active', true)];
        unset($data['photo']);
        if ($request->hasFile('photo')) {
            $supply->photo && Storage::disk('public')->delete($supply->photo);
            $data['photo'] = $request->file('photo')->store('supplies', 'public');
        }
        $supply->fill($data);
        $save = function () use ($supply) {
            if ($diff = Audit::diff($supply)) {
                Audit::log('supply.update', $supply, "แก้ข้อมูลวัสดุ {$supply->name}", $diff);
            }
            $supply->save();
        };
        // ลบรหัสออก = ออกรหัสใหม่ตามรูปแบบ
        blank($supply->code)
            ? SupplyNumber::assign($supply->category, null, function ($code) use ($supply, $save) {
                $supply->code = $code;
                $save();
            })
            : $save();

        return redirect()->route('supplies.show', $supply)->with('success', 'บันทึกแล้ว');
    }

    /** รับเข้า (ซื้อ/ได้รับบริจาค) หรือปรับยอดตามการตรวจนับ */
    public function move(Request $request, Supply $supply)
    {
        $this->authorizeManager($request);
        $data = $request->validate([
            'type' => ['required', Rule::in(['in', 'adjust'])],
            'quantity' => ['required', 'integer', 'not_in:0', 'min:-100000', 'max:100000'],
            'note' => ['nullable', 'string', 'max:255'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ], [], ['quantity' => 'จำนวน']);
        if ($data['type'] === 'in' && $data['quantity'] < 0) {
            return back()->withErrors(['quantity' => 'รับเข้าต้องเป็นจำนวนบวก (ถ้าจะลดยอดให้ใช้ "ปรับยอด")']);
        }
        if ($supply->stock + $data['quantity'] < 0) {
            return back()->withErrors(['quantity' => "ยอดคงเหลือมีเพียง {$supply->stock} {$supply->unit}"]);
        }
        // รับเข้าจากการซื้อ: อัปเดตราคาต่อหน่วยล่าสุดได้พร้อมกัน
        if ($data['type'] === 'in' && isset($data['unit_price'])) {
            $supply->update(['unit_price' => $data['unit_price']]);
        }
        $supply->move($data['type'], $data['quantity'], $data['note'] ?? null);
        Audit::log('supply.'.$data['type'], $supply, SupplyTransaction::TYPES[$data['type']]." {$supply->name} ".($data['quantity'] > 0 ? '+' : '')."{$data['quantity']} {$supply->unit} คงเหลือ {$supply->stock}".(! empty($data['note']) ? " ({$data['note']})" : ''));

        return back()->with('success', "{$supply->name}: คงเหลือ {$supply->stock} {$supply->unit}");
    }

    /** บัญชีวัสดุ (stock card) */
    public function show(Request $request, Supply $supply)
    {
        $this->authorizeManager($request);

        return view('supplies.show', ['supply' => $supply, 'transactions' => $supply->transactions()->with(['user', 'requisition'])->reorder('id')->get()]);
    }

    /** สรุปการจ่ายวัสดุรายเดือน แยกหน่วยงานและรายการ */
    public function report(Request $request)
    {
        $this->authorizeManager($request);
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? $request->query('month') : now()->format('Y-m');
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $out = SupplyTransaction::with(['supply', 'requisition'])->where('type', 'out')
            ->whereBetween('created_at', [$start, $start->copy()->endOfMonth()])->get();

        return view('supplies.report', [
            'month' => $month, 'start' => $start,
            'byItem' => $out->groupBy('supply_id')->map(fn ($rows) => ['supply' => $rows->first()->supply, 'qty' => -$rows->sum('quantity')])->sortByDesc('qty'),
            'byDepartment' => $out->groupBy(fn ($t) => $t->requisition?->department ?: '-')->map(fn ($rows) => $rows->groupBy('supply_id')->map(fn ($r) => -$r->sum('quantity'))),
            'supplies' => Supply::whereIn('id', $out->pluck('supply_id'))->get()->keyBy('id'),
        ]);
    }

    /** รหัสที่จะได้ถ้าเว้นช่องรหัสว่าง (แสดงในฟอร์ม) */
    public function nextNumber(Request $request)
    {
        $this->authorizeManager($request);
        $data = $request->validate(['category' => ['nullable', 'string', 'max:50']]);
        $category = trim((string) ($data['category'] ?? '')) ?: null;

        return response()->json([
            'first' => SupplyNumber::next($category)[0],
            'needs_category' => str_contains(SupplyNumber::pattern(), '{CAT}') && ! array_key_exists((string) $category, SupplyNumber::codes()),
        ]);
    }

    private function validated(Request $request, ?Supply $supply = null): array
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('supplies')->ignore($supply?->id)],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:30'],
            'category' => ['nullable', 'string', 'max:50'],
            'min_stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'storage_location' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:8192'],
            'initial_stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ], [], ['name' => 'ชื่อวัสดุ', 'unit' => 'หน่วยนับ', 'code' => 'รหัสวัสดุ']);
        unset($data['initial_stock']);
        $data['min_stock'] ??= 0;
        $data['unit_price'] ??= 0;

        return $data;
    }

    private static function categories()
    {
        return Supply::whereNotNull('category')->distinct()->orderBy('category')->pluck('category')
            ->merge(SupplyNumber::categories())->unique()->values();
    }
}
