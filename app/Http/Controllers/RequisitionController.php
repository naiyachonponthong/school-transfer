<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\Supply;
use App\Models\SupplyRequisition;
use App\Models\User;
use App\Services\Notifier;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** ใบเบิกวัสดุ: ครูเบิก → งานพัสดุจ่าย (ปรับจำนวนได้ไม่เกินคงคลัง) → ตัดสต็อก + บัญชีวัสดุ */
class RequisitionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $manager = $user->canManageFacilities();

        return view('requisitions.index', [
            'manager' => $manager,
            'requisitions' => SupplyRequisition::with(['requester', 'items.supply'])
                ->when(! $manager, fn ($q) => $q->where('requester_id', $user->id))
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->orderByRaw("case when status = 'pending' then 0 else 1 end")->latest('id')->paginate(30)->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('requisitions.create', [
            'supplies' => Supply::where('is_active', true)->orderBy('category')->orderBy('name')->get(),
            'departments' => array_merge(array_diff(Subject::GROUPS, ['กิจกรรมพัฒนาผู้เรียน']), ['งานธุรการ', 'งานอาคารสถานที่', 'งานกิจการนักเรียน', 'งานวิชาการ']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'department' => ['nullable', 'string', 'max:100'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array'],
            'items.*.supply_id' => ['nullable', 'exists:supplies,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);
        $items = collect($data['items'])->filter(fn ($i) => ! empty($i['supply_id']) && ! empty($i['quantity']))
            ->groupBy('supply_id')->map(fn ($rows, $id) => ['supply_id' => (int) $id, 'quantity' => $rows->sum('quantity')])->values();
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'เลือกวัสดุและจำนวนอย่างน้อย 1 รายการ']);
        }

        $req = DB::transaction(function () use ($data, $items, $request) {
            $req = SupplyRequisition::create(['req_no' => SupplyRequisition::nextNumber(), 'requester_id' => $request->user()->id,
                'department' => $data['department'] ?? null, 'purpose' => $data['purpose'] ?? null, 'status' => 'pending']);
            $req->items()->createMany($items->all());

            return $req;
        });

        $managers = User::where('is_active', true)->where(fn ($q) => $q->where('role', 'admin')->orWhereIn('id', User::facilityManagerIds()))->get()
            ->reject(fn ($u) => $u->id === $request->user()->id);
        Notifier::users($managers, "📦 ใบเบิกวัสดุ {$req->req_no} ({$items->count()} รายการ) จาก {$request->user()->name}".($req->department ? " · {$req->department}" : ''), route('requisitions.show', $req));

        return redirect()->route('requisitions.show', $req)->with('success', "ส่งใบเบิก {$req->req_no} แล้ว — จะแจ้งทาง LINE เมื่อจ่ายของ");
    }

    public function show(Request $request, SupplyRequisition $requisition)
    {
        $user = $request->user();
        abort_unless($user->canManageFacilities() || $requisition->requester_id === $user->id, 403);

        return view('requisitions.show', ['req' => $requisition->load(['items.supply', 'requester', 'reviewer']), 'manager' => $user->canManageFacilities()]);
    }

    /** จ่ายวัสดุ: จำนวนที่จ่ายต้องไม่เกินคงคลัง · 0 = ไม่จ่ายรายการนั้น */
    public function issue(Request $request, SupplyRequisition $requisition)
    {
        abort_unless($request->user()->canManageFacilities(), 403);
        abort_unless($requisition->status === 'pending', 422, 'ใบเบิกนี้ดำเนินการแล้ว');
        $data = $request->validate(['issued' => ['required', 'array'], 'issued.*' => ['required', 'integer', 'min:0'], 'review_note' => ['nullable', 'string', 'max:255']]);

        DB::transaction(function () use ($requisition, $data, $request) {
            foreach ($requisition->items()->with('supply')->get() as $item) {
                $qty = (int) ($data['issued'][$item->id] ?? 0);
                $supply = Supply::lockForUpdate()->find($item->supply_id);
                if ($qty > $item->quantity) {
                    throw ValidationException::withMessages(['issued' => "{$supply->name}: จ่ายเกินจำนวนที่ขอ ({$item->quantity})"]);
                }
                if ($qty > $supply->stock) {
                    throw ValidationException::withMessages(['issued' => "{$supply->name}: คงคลังมีเพียง {$supply->stock} {$supply->unit}"]);
                }
                $item->update(['issued' => $qty]);
                if ($qty > 0) {
                    $supply->move('out', -$qty, "จ่ายตามใบเบิก {$requisition->req_no}", $requisition->id);
                }
            }
            $requisition->update(['status' => 'issued', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['review_note'] ?? null]);
        });
        Audit::log('supply.issue', $requisition, "จ่ายวัสดุตามใบเบิก {$requisition->req_no} ให้ {$requisition->requester?->name}");
        $requisition->requester && Notifier::users([$requisition->requester], "📦 ใบเบิก {$requisition->req_no}: จ่ายวัสดุแล้ว มารับได้ที่งานพัสดุ".($requisition->review_note ? "\n{$requisition->review_note}" : ''), route('requisitions.show', $requisition));

        return back()->with('success', "จ่ายวัสดุตามใบเบิก {$requisition->req_no} แล้ว");
    }

    public function reject(Request $request, SupplyRequisition $requisition)
    {
        abort_unless($request->user()->canManageFacilities(), 403);
        abort_unless($requisition->status === 'pending', 422, 'ใบเบิกนี้ดำเนินการแล้ว');
        $requisition->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $request->input('review_note')]);
        $requisition->requester && Notifier::users([$requisition->requester], "📦 ใบเบิก {$requisition->req_no}: ไม่อนุมัติ".($requisition->review_note ? " — {$requisition->review_note}" : ''), route('requisitions.show', $requisition));

        return back()->with('success', 'ไม่อนุมัติใบเบิกแล้ว');
    }

    public function cancel(Request $request, SupplyRequisition $requisition)
    {
        abort_unless($requisition->requester_id === $request->user()->id && $requisition->status === 'pending', 403);
        $requisition->update(['status' => 'cancelled']);

        return back()->with('success', 'ยกเลิกใบเบิกแล้ว');
    }
}
