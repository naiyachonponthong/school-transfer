<?php

namespace App\Http\Controllers;

use App\Models\Supply;
use App\Models\SupplyRequisition;
use App\Models\SupplyTransaction;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            ->when($request->query('q'), fn ($q, $t) => $q->where('name', 'like', "%{$t}%"))
            ->when($request->query('low'), fn ($q) => $q->whereColumn('stock', '<=', 'min_stock')->where('min_stock', '>', 0))
            ->orderByDesc('is_active')->orderBy('category')->orderBy('name')->get();

        return view('supplies.index', [
            'supplies' => $supplies,
            'lowCount' => Supply::where('is_active', true)->whereColumn('stock', '<=', 'min_stock')->where('min_stock', '>', 0)->count(),
            'pending' => SupplyRequisition::where('status', 'pending')->count(),
            'categories' => Supply::whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeManager($request);
        $data = $this->validated($request);
        $initial = (int) $request->input('initial_stock', 0);
        $supply = Supply::create($data + ['stock' => 0]);
        if ($initial > 0) {
            $supply->move('in', $initial, 'ยอดยกมา');
        }
        Audit::log('supply.create', $supply, "เพิ่มวัสดุ {$supply->name}".($initial ? " ยอดยกมา {$initial} {$supply->unit}" : ''));

        return back()->with('success', "เพิ่ม {$supply->name} แล้ว");
    }

    public function update(Request $request, Supply $supply)
    {
        $this->authorizeManager($request);
        $supply->update($this->validated($request) + ['is_active' => $request->boolean('is_active', true)]);

        return back()->with('success', 'บันทึกแล้ว');
    }

    /** รับเข้า (ซื้อ/ได้รับบริจาค) หรือปรับยอดตามการตรวจนับ */
    public function move(Request $request, Supply $supply)
    {
        $this->authorizeManager($request);
        $data = $request->validate([
            'type' => ['required', Rule::in(['in', 'adjust'])],
            'quantity' => ['required', 'integer', 'not_in:0', 'min:-100000', 'max:100000'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['quantity' => 'จำนวน']);
        if ($data['type'] === 'in' && $data['quantity'] < 0) {
            return back()->withErrors(['quantity' => 'รับเข้าต้องเป็นจำนวนบวก (ถ้าจะลดยอดให้ใช้ "ปรับยอด")']);
        }
        if ($supply->stock + $data['quantity'] < 0) {
            return back()->withErrors(['quantity' => "ยอดคงเหลือมีเพียง {$supply->stock} {$supply->unit}"]);
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

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:30'],
            'category' => ['nullable', 'string', 'max:50'],
            'min_stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], [], ['name' => 'ชื่อวัสดุ', 'unit' => 'หน่วยนับ']);
        $data['min_stock'] ??= 0;

        return $data;
    }
}
