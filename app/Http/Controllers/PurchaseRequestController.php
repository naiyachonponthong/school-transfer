<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\Notifier;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * ใบขอซื้อ/ขอจ้าง: ครูทุกคนขอได้จากงบของโครงการ แล้วผ่านการอนุมัติตามลำดับขั้น
 * อนุมัติครบ = เงินถูกผูกพันกับงบบรรทัดนั้น (การจัดซื้อ ตรวจรับ และเบิกจ่ายเป็นเฟสถัดไป)
 */
class PurchaseRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tab = in_array($request->query('tab'), ['mine', 'all'], true) ? $request->query('tab') : 'mine';
        $all = Project::seesAll($user);

        return view('purchases.index', [
            'tab' => $all ? $tab : 'mine',
            'canSeeAll' => $all,
            'awaiting' => PurchaseRequest::awaiting($user),
            'requests' => PurchaseRequest::with(['budget.project', 'budget.source', 'requester'])
                ->when(! $all || $tab === 'mine', fn ($q) => $q->where('requester_id', $user->id))
                ->latest('id')->paginate(30)->withQueryString(),
        ]);
    }

    /** บรรทัดงบที่ขอใช้ได้: โครงการที่ยังไม่ปิด */
    private function openLines()
    {
        return ProjectBudget::with(['project', 'source'])->whereHas('project', fn ($q) => $q->where('status', 'active'))->get()
            ->sortBy(fn (ProjectBudget $l) => [-$l->project->fiscal_year, $l->project->code, $l->label()])->values();
    }

    public function create(Request $request)
    {
        $lines = $this->openLines();

        return view('purchases.create', [
            'lines' => $lines,
            'available' => $lines->mapWithKeys(fn (ProjectBudget $l) => [$l->id => $l->available()]),
            'selected' => (int) $request->query('line'),
            'steps' => PurchaseRequest::configuredSteps(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_budget_id' => ['required', 'exists:project_budgets,id'],
            'kind' => ['required', Rule::in(array_keys(PurchaseRequest::KINDS))],
            'title' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'method' => ['required', Rule::in(array_keys(PurchaseRequest::METHODS))],
            'vendor' => ['nullable', 'string', 'max:255'],
            'needed_on' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.item_type' => ['required', Rule::in(array_keys(PurchaseRequest::ITEM_TYPES))],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ], ['items.required' => 'ต้องมีอย่างน้อย 1 รายการ'], [
            'project_budget_id' => 'งบที่ใช้', 'title' => 'เรื่อง', 'items.*.description' => 'รายการ', 'items.*.quantity' => 'จำนวน', 'items.*.unit_price' => 'ราคาต่อหน่วย',
        ]);

        $items = collect($data['items'])->map(fn ($i) => $i + ['amount' => round((float) $i['quantity'] * (float) $i['unit_price'], 2)]);
        $total = round($items->sum('amount'), 2);
        if ($total <= 0) {
            return back()->withInput()->with('warning', 'ยอดรวมต้องมากกว่า 0');
        }

        $result = DB::transaction(function () use ($data, $items, $total, $request) {
            // ล็อกบรรทัดงบ: ใบที่ยื่นพร้อมกันจะไม่รวมกันเกินงบ
            $line = ProjectBudget::with('project')->whereKey($data['project_budget_id'])->lockForUpdate()->firstOrFail();
            if ($line->project->isClosed()) {
                return 'โครงการนี้ปิดแล้ว ขอซื้อ/ขอจ้างเพิ่มไม่ได้';
            }
            if ($total > $line->available() + 0.001) {
                return 'เกินงบคงเหลือของ'.$line->label().' (เหลือ '.baht(max(0, $line->available())).' บาท)';
            }
            // ผู้รับผิดชอบโครงการขอเอง ไม่ต้องอนุมัติคำขอของตัวเองอีกขั้น
            $steps = array_values(array_filter(PurchaseRequest::configuredSteps(), fn ($k) => ! ($k === 'owner' && $line->project->owner_id === $request->user()->id)));
            $purchase = PurchaseRequest::create([
                'req_no' => PurchaseRequest::nextNumber(), 'project_budget_id' => $line->id, 'requester_id' => $request->user()->id,
                'kind' => $data['kind'], 'title' => $data['title'], 'reason' => $data['reason'] ?? null, 'method' => $data['method'],
                'vendor' => $data['vendor'] ?? null, 'needed_on' => $data['needed_on'] ?? null, 'total' => $total,
                'steps' => $steps ?: ['owner'], 'step' => 0,
            ]);
            $purchase->items()->createMany($items->all());

            return $purchase;
        });
        if (is_string($result)) {
            return back()->withInput()->with('warning', $result);
        }
        $this->notifyApprovers($result);

        return redirect()->route('purchases.show', $result)->with('success', "ยื่น{$this->kindLabel($result)}เลขที่ {$result->req_no} แล้ว รอ{$result->currentStepLabel()}พิจารณา");
    }

    private function kindLabel(PurchaseRequest $purchase): string
    {
        return 'ใบ'.PurchaseRequest::KINDS[$purchase->kind];
    }

    /** แจ้งผู้ที่ตัดสินขั้นปัจจุบันได้ */
    private function notifyApprovers(PurchaseRequest $purchase): void
    {
        $purchase->load('budget.project');
        $approvers = User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->get()
            ->filter(fn (User $u) => $u->id !== $purchase->requester_id && $purchase->canBeDecidedBy($u));
        Notifier::users($approvers, "📝 {$this->kindLabel($purchase)} {$purchase->req_no} รอ{$purchase->currentStepLabel()}พิจารณา: {$purchase->title} ".baht($purchase->total)
            ." บาท (โครงการ {$purchase->budget->project->name})", route('purchases.show', $purchase));
    }

    public function show(Request $request, PurchaseRequest $purchase)
    {
        $purchase->load(['budget.project.owner', 'budget.project.department', 'budget.source', 'requester', 'items', 'approvals.user']);
        abort_unless($purchase->canBeViewedBy($request->user()) || $purchase->canBeDecidedBy($request->user()), 403);

        return view('purchases.show', [
            'purchase' => $purchase,
            'canDecide' => $purchase->canBeDecidedBy($request->user()),
            'canCancel' => $purchase->status === 'pending' && ($purchase->requester_id === $request->user()->id || $request->user()->hasPermission('budget.manage')),
            'available' => $purchase->budget->available(),
        ]);
    }

    /** ตัดสินขั้นที่รออยู่: อนุมัติ = ไปขั้นถัดไป (ขั้นสุดท้าย = ผูกพันงบ) · ไม่อนุมัติ = จบ เงินที่กันไว้คืนงบ */
    public function decide(Request $request, PurchaseRequest $purchase)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'note' => ['nullable', 'required_if:decision,rejected', 'string', 'max:255'],
        ], ['note.required_if' => 'กรุณาระบุเหตุผลที่ไม่อนุมัติ'], ['note' => 'เหตุผล']);

        $done = DB::transaction(function () use ($purchase, $data, $request) {
            $purchase = PurchaseRequest::with('budget.project')->whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            if (! $purchase->canBeDecidedBy($request->user())) {
                return null;
            }
            $purchase->approvals()->create(['step' => $purchase->step, 'step_key' => $purchase->currentStepKey(), 'user_id' => $request->user()->id,
                'decision' => $data['decision'], 'note' => $data['note'] ?? null]);
            $last = $purchase->step >= count($purchase->steps) - 1;
            $purchase->update(match (true) {
                $data['decision'] === 'rejected' => ['status' => 'rejected', 'decided_at' => now()],
                $last => ['status' => 'approved', 'decided_at' => now()],
                default => ['step' => $purchase->step + 1],
            });

            return $purchase;
        });
        abort_unless($done, 403, 'คุณไม่ใช่ผู้พิจารณาของขั้นนี้ หรือใบนี้ถูกพิจารณาไปแล้ว');

        if ($done->status === 'pending') {
            $this->notifyApprovers($done);
        } else {
            Audit::log('finance.purchase', $done, "{$done->statusLabel()}{$this->kindLabel($done)} {$done->req_no} ".baht($done->total).' บาท'.(($data['note'] ?? null) ? ": {$data['note']}" : ''));
            Notifier::users(array_filter([$done->requester]), ($done->status === 'approved' ? '✅' : '⚠️')." {$this->kindLabel($done)} {$done->req_no} {$done->statusLabel()}"
                .(($data['note'] ?? null) ? ": {$data['note']}" : ''), route('purchases.show', $done));
        }

        return back()->with('success', $done->status === 'pending' ? 'อนุมัติแล้ว ส่งต่อให้'.$done->currentStepLabel() : 'บันทึกผล: '.$done->statusLabel());
    }

    /** ผู้ขอยกเลิกเองได้ตราบที่ยังไม่อนุมัติครบ เงินที่กันไว้คืนงบ */
    public function cancel(Request $request, PurchaseRequest $purchase)
    {
        abort_unless($purchase->status === 'pending', 422, 'ยกเลิกได้เฉพาะใบที่รออนุมัติ');
        abort_unless($purchase->requester_id === $request->user()->id || $request->user()->hasPermission('budget.manage'), 403);
        $purchase->update(['status' => 'cancelled', 'decided_at' => now()]);

        return back()->with('success', 'ยกเลิกใบนี้แล้ว');
    }
}
