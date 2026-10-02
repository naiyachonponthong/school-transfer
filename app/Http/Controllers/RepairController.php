<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\RepairRequest;
use App\Models\User;
use App\Services\Notifier;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** แจ้งซ่อม: ครูแจ้ง (มือถือ + รูป + สแกน QR ครุภัณฑ์) → งานอาคารสถานที่รับเรื่อง มอบหมาย ปิดงาน */
class RepairController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $manager = $user->canManageFacilities();
        $status = $request->query('status', $manager ? 'open' : 'all');

        $repairs = RepairRequest::with(['asset', 'reporter', 'assignee'])
            ->when(! $manager, fn ($q) => $q->where('reporter_id', $user->id))
            ->when($status === 'open', fn ($q) => $q->whereIn('status', RepairRequest::OPEN))
            ->when($status !== 'open' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('title', 'like', "%{$t}%")->orWhere('ticket_no', 'like', "%{$t}%")->orWhere('location', 'like', "%{$t}%")))
            ->orderByRaw("case when priority = 'urgent' and status in ('pending','in_progress','external') then 0 else 1 end")
            ->latest('id')->paginate(30)->withQueryString();

        return view('repairs.index', [
            'repairs' => $repairs, 'manager' => $manager, 'status' => $status,
            'counts' => RepairRequest::when(! $manager, fn ($q) => $q->where('reporter_id', $user->id))->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function create(Request $request)
    {
        $asset = $request->query('asset') ? Asset::find($request->query('asset')) : null;

        return view('repairs.create', ['asset' => $asset, 'locations' => AssetController::locations()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'detail' => ['nullable', 'string', 'max:2000'],
            'location' => ['required_without:asset_id', 'nullable', 'string', 'max:100'],
            'asset_id' => ['nullable', 'exists:assets,id'],
            'priority' => ['required', Rule::in(array_keys(RepairRequest::PRIORITIES))],
            'photo' => ['nullable', 'image', 'max:8192'],
        ], [], ['title' => 'อาการ/สิ่งที่เสีย', 'location' => 'สถานที่']);
        $asset = isset($data['asset_id']) ? Asset::find($data['asset_id']) : null;
        $data['location'] = ($data['location'] ?? null) ?: $asset?->location;
        $data['photo'] = $request->hasFile('photo') ? $request->file('photo')->store('repairs', 'public') : null;

        $repair = DB::transaction(function () use ($data, $request) {
            $r = RepairRequest::create($data + ['ticket_no' => RepairRequest::nextTicket(), 'status' => 'pending', 'reporter_id' => $request->user()->id]);
            $r->updates()->create(['user_id' => $request->user()->id, 'status' => 'pending', 'note' => 'แจ้งซ่อม']);

            return $r;
        });

        $managers = User::where('is_active', true)->where(fn ($q) => $q->where('role', 'admin')->orWhereIn('id', User::facilityManagerIds()))->get();
        Notifier::users($managers->reject(fn ($u) => $u->id === $request->user()->id),
            ($repair->priority === 'urgent' ? '🚨 แจ้งซ่อมด่วน' : '🛠️ แจ้งซ่อม')." {$repair->ticket_no}: {$repair->title}".($repair->location ? " ({$repair->location})" : '')." โดย {$request->user()->name}",
            route('repairs.show', $repair));

        return redirect()->route('repairs.show', $repair)->with('success', "แจ้งซ่อมแล้ว เลขที่ {$repair->ticket_no} — จะแจ้งเตือนเมื่อมีความคืบหน้า");
    }

    public function show(Request $request, RepairRequest $repair)
    {
        $this->authorizeView($request, $repair);
        $repair->load(['asset', 'reporter', 'assignee', 'updates.user']);

        return view('repairs.show', [
            'repair' => $repair,
            'manager' => $request->user()->canManageFacilities(),
            'staff' => User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /** งานอาคารสถานที่อัปเดตสถานะ / ผู้รับผิดชอบ / ค่าใช้จ่าย */
    public function update(Request $request, RepairRequest $repair)
    {
        abort_unless($request->user()->canManageFacilities(), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(RepairRequest::STATUSES))],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'result_note' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $repair->fill(collect($data)->except('note')->all());
        $repair->finished_at = $repair->isOpen() ? null : ($repair->finished_at ?? now());
        $diff = Audit::diff($repair);
        $statusChanged = $repair->isDirty('status');
        $repair->save();

        if ($diff || filled($data['note'] ?? null)) {
            $repair->updates()->create(['user_id' => $request->user()->id, 'status' => $repair->status, 'note' => $data['note'] ?? null]);
        }
        if (isset($diff['cost'])) {
            Audit::log('repair.cost', $repair, "ค่าซ่อม {$repair->ticket_no}: ".number_format((float) $diff['cost'][0], 2).' → '.number_format((float) $repair->cost, 2).' บาท', $diff);
        }
        $this->syncAsset($repair);

        if ($statusChanged && $repair->reporter && $repair->reporter_id !== $request->user()->id) {
            Notifier::users([$repair->reporter], "🛠️ งานซ่อม {$repair->ticket_no} ({$repair->title}): {$repair->statusLabel()}".(filled($data['note'] ?? null) ? "\n{$data['note']}" : ''), route('repairs.show', $repair));
        }

        return back()->with('success', 'อัปเดตงานซ่อมแล้ว');
    }

    /** ผู้แจ้งยกเลิกเองได้ระหว่างที่ยังไม่มีคนรับเรื่อง */
    public function cancel(Request $request, RepairRequest $repair)
    {
        $user = $request->user();
        abort_unless($repair->status === 'pending' && ($repair->reporter_id === $user->id || $user->canManageFacilities()), 403);
        $repair->update(['status' => 'cancelled', 'finished_at' => now()]);
        $repair->updates()->create(['user_id' => $user->id, 'status' => 'cancelled', 'note' => 'ยกเลิกโดย '.$user->name]);

        return back()->with('success', 'ยกเลิกการแจ้งซ่อมแล้ว');
    }

    /** สรุปรายเดือน: จำนวนตามสถานะ / สถานที่ · ค่าใช้จ่าย · เวลาเฉลี่ยที่ใช้ซ่อม */
    public function report(Request $request)
    {
        abort_unless($request->user()->canManageFacilities(), 403);
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? $request->query('month') : now()->format('Y-m');
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $repairs = RepairRequest::with(['asset', 'assignee'])->whereBetween('created_at', [$start, $start->copy()->endOfMonth()])->orderBy('id')->get();
        $finished = $repairs->filter(fn ($r) => $r->finished_at && $r->status === 'done');

        return view('repairs.report', [
            'month' => $month, 'start' => $start, 'repairs' => $repairs,
            'byStatus' => $repairs->countBy('status'),
            'byLocation' => $repairs->groupBy(fn ($r) => $r->location ?: '-')->map->count()->sortDesc(),
            'cost' => $repairs->sum('cost'),
            'avgDays' => $finished->isEmpty() ? null : round($finished->avg(fn ($r) => $r->created_at->diffInHours($r->finished_at) / 24), 1),
        ]);
    }

    private function authorizeView(Request $request, RepairRequest $repair): void
    {
        $user = $request->user();
        abort_unless($user->canManageFacilities() || $repair->reporter_id === $user->id || $repair->assignee_id === $user->id, 403);
    }

    /** สถานะครุภัณฑ์ตามงานซ่อม: กำลังซ่อม → ซ่อมเสร็จกลับเป็นใช้งานได้ · ซ่อมไม่ได้ = ชำรุด */
    private function syncAsset(RepairRequest $repair): void
    {
        $asset = $repair->asset;
        if (! $asset || in_array($asset->status, ['disposed', 'pending_disposal', 'lost'], true)) {
            return;
        }
        $status = match ($repair->status) {
            'in_progress', 'external' => 'repairing',
            'done' => 'normal',
            'unrepairable' => 'broken',
            default => null,
        };
        if ($status && $asset->status !== $status) {
            $asset->update(['status' => $status]);
        }
    }
}
