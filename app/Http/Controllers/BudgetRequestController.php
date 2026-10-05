<?php

namespace App\Http\Controllers;

use App\Models\BudgetRequest;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use App\Services\Notifier;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * คำขอใช้งบ: ขอซื้อ/จ้าง ขอเบิกเงิน ขอยืมเงิน จากงบของกิจกรรม
 * ลำดับ: (ผู้ตรวจสอบเอกสาร → รองผู้อำนวยการ → ผู้อำนวยการ) → เจ้าหน้าที่ตัดงบ ผู้พิจารณาทุกขั้นเซ็นด้วยลายเซ็นที่บันทึกไว้
 */
class BudgetRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tab = in_array($request->query('tab'), ['mine', 'all'], true) ? $request->query('tab') : 'mine';
        $all = Project::seesAll($user);

        return view('budget-requests.index', [
            'tab' => $all ? $tab : 'mine',
            'canSeeAll' => $all,
            'awaiting' => BudgetRequest::awaiting($user),
            'requests' => BudgetRequest::with(['activity.project', 'requester'])
                ->when(! $all || $tab === 'mine', fn ($q) => $q->where('requester_id', $user->id))
                ->latest('id')->paginate(30)->withQueryString(),
        ]);
    }

    public function create(Request $request)
    {
        // กิจกรรมที่ขอใช้งบได้: โครงการที่ยังไม่ปิด และตั้งงบแล้ว
        $activities = ProjectActivity::with('project')->whereHas('project', fn ($q) => $q->where('status', 'active'))->whereHas('budgets')->get()
            ->sortBy(fn (ProjectActivity $a) => [-$a->project->fiscal_year, $a->project->code, $a->id])->values();

        return view('budget-requests.create', [
            'activities' => $activities,
            'available' => $activities->mapWithKeys(fn (ProjectActivity $a) => [$a->id => $a->available()]),
            'selected' => (int) $request->query('activity'),
            'steps' => BudgetRequest::configuredSteps(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_activity_id' => ['required', 'exists:project_activities,id'],
            'type' => ['required', Rule::in(array_keys(BudgetRequest::TYPES))],
            'title' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'method' => ['nullable', Rule::in(array_keys(BudgetRequest::METHODS))],
            'vendor' => ['nullable', 'string', 'max:255'],
            'needed_on' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.item_type' => ['required', Rule::in(array_keys(BudgetRequest::ITEM_TYPES))],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ], ['items.required' => 'ต้องมีอย่างน้อย 1 รายการ'], [
            'project_activity_id' => 'กิจกรรม', 'title' => 'เรื่อง', 'items.*.description' => 'รายการ', 'items.*.quantity' => 'จำนวน', 'items.*.unit_price' => 'ราคาต่อหน่วย',
        ]);

        $items = collect($data['items'])->map(fn ($i) => $i + ['amount' => round((float) $i['quantity'] * (float) $i['unit_price'], 2)]);
        $total = round($items->sum('amount'), 2);
        if ($total <= 0) {
            return back()->withInput()->with('warning', 'ยอดรวมต้องมากกว่า 0');
        }

        $result = DB::transaction(function () use ($data, $items, $total, $request) {
            // ล็อกกิจกรรม: ใบที่ยื่นพร้อมกันจะไม่รวมกันเกินงบ
            $activity = ProjectActivity::with('project')->whereKey($data['project_activity_id'])->lockForUpdate()->firstOrFail();
            if ($activity->project->isClosed()) {
                return 'โครงการนี้ปิดแล้ว ขอใช้งบเพิ่มไม่ได้';
            }
            if ($total > $activity->available() + 0.001) {
                return "เกินงบคงเหลือของกิจกรรม {$activity->name} (เหลือ ".baht(max(0, $activity->available())).' บาท)';
            }
            $budgetRequest = BudgetRequest::create([
                'req_no' => BudgetRequest::nextNumber(), 'project_activity_id' => $activity->id, 'requester_id' => $request->user()->id,
                'type' => $data['type'], 'title' => $data['title'], 'reason' => $data['reason'] ?? null,
                'method' => $data['type'] === 'buy_hire' ? ($data['method'] ?? 'specific') : null,
                'vendor' => $data['vendor'] ?? null, 'needed_on' => $data['needed_on'] ?? null, 'total' => $total,
                'steps' => BudgetRequest::configuredSteps(), 'step' => 0,
            ]);
            $budgetRequest->items()->createMany($items->all());

            return $budgetRequest;
        });
        if (is_string($result)) {
            return back()->withInput()->with('warning', $result);
        }
        $this->notifyApprovers($result);

        return redirect()->route('budget-requests.show', $result)->with('success', "ยื่นใบ{$result->typeLabel()}เลขที่ {$result->req_no} แล้ว รอ{$result->currentStepLabel()}");
    }

    /** แจ้งผู้ที่พิจารณาขั้นปัจจุบันได้ */
    private function notifyApprovers(BudgetRequest $budgetRequest): void
    {
        $budgetRequest->load('activity.project');
        $approvers = User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->get()
            ->filter(fn (User $u) => $u->id !== $budgetRequest->requester_id && $budgetRequest->canBeDecidedBy($u));
        Notifier::users($approvers, "📝 ใบ{$budgetRequest->typeLabel()} {$budgetRequest->req_no} รอ{$budgetRequest->currentStepLabel()}: {$budgetRequest->title} ".baht($budgetRequest->total)
            ." บาท ({$budgetRequest->activity->project->name})", route('budget-requests.show', $budgetRequest));
    }

    public function show(Request $request, BudgetRequest $budgetRequest)
    {
        $budgetRequest->load(['activity.project.owner', 'activity.project.department', 'requester', 'items', 'approvals.user', 'cuts.source']);
        $user = $request->user();
        $canDecide = $budgetRequest->canBeDecidedBy($user);
        abort_unless($budgetRequest->canBeViewedBy($user) || $canDecide, 403);

        return view('budget-requests.show', [
            'r' => $budgetRequest,
            'canDecide' => $canDecide,
            'isCutStep' => $budgetRequest->currentStepKey() === 'cut',
            'canCancel' => $budgetRequest->status === 'pending' && ($budgetRequest->requester_id === $user->id || $user->hasPermission('budget.manage')),
            'bySource' => $budgetRequest->activity->bySource(),
            'available' => $budgetRequest->activity->available(),
            'hasSignature' => filled($user->signature),
        ]);
    }

    /**
     * พิจารณาขั้นที่รออยู่ · อนุมัติ = ไปขั้นถัดไป · ไม่อนุมัติ = จบ เงินที่กันไว้คืนงบ
     * ขั้นตัดงบ: ระบุยอดที่ตัดจากแต่ละประเภทเงิน รวมต้องเท่ายอดคำขอ และไม่เกินงบคงเหลือของประเภทเงินนั้นในกิจกรรม
     */
    public function decide(Request $request, BudgetRequest $budgetRequest)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'note' => ['nullable', 'required_if:decision,rejected', 'string', 'max:255'],
            'cuts' => ['nullable', 'array'],
            'cuts.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ], ['note.required_if' => 'กรุณาระบุเหตุผลที่ไม่อนุมัติ'], ['note' => 'เหตุผล', 'cuts.*' => 'ยอดตัด']);
        $user = $request->user();
        // ลายเซ็นเป็นหลักฐานว่าใครอนุมัติ จึงต้องมีก่อนอนุมัติ (ไม่อนุมัติไม่ต้องเซ็น)
        if ($data['decision'] === 'approved' && blank($user->signature)) {
            return back()->with('warning', 'ยังไม่มีลายเซ็นของคุณในระบบ ให้เซ็นที่หน้าข้อมูลส่วนตัวก่อนจึงอนุมัติได้');
        }

        $result = DB::transaction(function () use ($budgetRequest, $data, $user) {
            $budgetRequest = BudgetRequest::whereKey($budgetRequest->id)->lockForUpdate()->firstOrFail();
            // ล็อกกิจกรรมด้วย: ตัดงบสองใบพร้อมกันจะไม่รวมกันเกินงบของประเภทเงิน
            $activity = ProjectActivity::with('project')->whereKey($budgetRequest->project_activity_id)->lockForUpdate()->firstOrFail();
            $budgetRequest->setRelation('activity', $activity);
            if (! $budgetRequest->canBeDecidedBy($user)) {
                return null;
            }
            $key = $budgetRequest->currentStepKey();
            if ($key === 'cut' && $data['decision'] === 'approved') {
                $left = $activity->bySource();
                $cuts = collect($data['cuts'] ?? [])->map(fn ($v) => round((float) $v, 2))->filter(fn ($v) => $v > 0);
                if (abs($cuts->sum() - (float) $budgetRequest->total) > 0.005) {
                    return 'ยอดตัดรวม '.baht($cuts->sum()).' บาท ไม่เท่ากับยอดคำขอ '.baht($budgetRequest->total).' บาท';
                }
                foreach ($cuts as $sourceId => $amount) {
                    if (! isset($left[$sourceId])) {
                        return 'กิจกรรมนี้ไม่มีงบจากประเภทเงินที่เลือก';
                    }
                    if ($amount > $left[$sourceId]['left'] + 0.001) {
                        return "ตัด{$left[$sourceId]['source']->name}เกินงบคงเหลือ (เหลือ ".baht(max(0, $left[$sourceId]['left'])).' บาท)';
                    }
                }
                foreach ($cuts as $sourceId => $amount) {
                    $budgetRequest->cuts()->create(['budget_source_id' => $sourceId, 'amount' => $amount]);
                }
            }
            $budgetRequest->approvals()->create(['step' => $budgetRequest->step, 'step_key' => $key, 'user_id' => $user->id, 'decision' => $data['decision'],
                'note' => $data['note'] ?? null, 'signature' => $data['decision'] === 'approved' ? $this->copySignature($user) : null]);
            $last = $budgetRequest->step >= count($budgetRequest->steps) - 1;
            $budgetRequest->update(match (true) {
                $data['decision'] === 'rejected' => ['status' => 'rejected', 'decided_at' => now()],
                $last => ['status' => 'approved', 'decided_at' => now()],
                default => ['step' => $budgetRequest->step + 1],
            });

            return $budgetRequest;
        });
        abort_if($result === null, 403, 'คุณไม่ใช่ผู้พิจารณาของขั้นนี้ หรือใบนี้ถูกพิจารณาไปแล้ว');
        if (is_string($result)) {
            return back()->withInput()->with('warning', $result);
        }

        if ($result->status === 'pending') {
            $this->notifyApprovers($result);
        } else {
            Audit::log('finance.request', $result, "{$result->statusLabel()} ใบ{$result->typeLabel()} {$result->req_no} ".baht($result->total).' บาท'.(($data['note'] ?? null) ? ": {$data['note']}" : ''));
            Notifier::users(array_filter([$result->requester]), ($result->status === 'approved' ? '✅' : '⚠️')." ใบ{$result->typeLabel()} {$result->req_no} {$result->statusLabel()}"
                .(($data['note'] ?? null) ? ": {$data['note']}" : ''), route('budget-requests.show', $result));
        }

        return back()->with('success', $result->status === 'pending' ? 'อนุมัติแล้ว ส่งต่อให้'.$result->currentStepLabel() : 'บันทึกผล: '.$result->statusLabel());
    }

    /** เก็บสำเนาลายเซ็น ณ ตอนที่เซ็น เอกสารเดิมจึงไม่เปลี่ยนเมื่อเจ้าของเปลี่ยนลายเซ็นภายหลัง */
    private function copySignature(User $user): ?string
    {
        $disk = Storage::disk('local');
        if (! $user->signature || ! $disk->exists($user->signature)) {
            return null;
        }
        $path = 'budget-signatures/'.\Illuminate\Support\Str::random(40).'.png';
        $disk->copy($user->signature, $path);

        return $path;
    }

    /** ผู้ขอยกเลิกเองได้ตราบที่ยังพิจารณาไม่ครบ เงินที่กันไว้คืนงบ */
    public function cancel(Request $request, BudgetRequest $budgetRequest)
    {
        abort_unless($budgetRequest->status === 'pending', 422, 'ยกเลิกได้เฉพาะใบที่รอพิจารณา');
        abort_unless($budgetRequest->requester_id === $request->user()->id || $request->user()->hasPermission('budget.manage'), 403);
        $budgetRequest->update(['status' => 'cancelled', 'decided_at' => now()]);

        return back()->with('success', 'ยกเลิกใบนี้แล้ว');
    }
}
