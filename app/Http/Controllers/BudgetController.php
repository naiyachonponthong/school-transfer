<?php

namespace App\Http\Controllers;

use App\Models\ActivityBudget;
use App\Models\BudgetRequest;
use App\Models\BudgetRequestCut;
use App\Models\BudgetSource;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use App\Support\Audit;
use App\Support\CodeSeries;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * งบประมาณและโครงการ: ประเภทเงินของปีงบประมาณ → โครงการ → กิจกรรม → งบของกิจกรรมแยกประเภทเงิน
 * ผู้มีสิทธิ์ budget.manage ตั้งและแก้ · ผู้รับผิดชอบโครงการเห็นโครงการของตัวเอง
 */
class BudgetController extends Controller
{
    private function year(Request $request): int
    {
        return (int) $request->query('year', CodeSeries::fiscalYear(now()));
    }

    /** ยอดที่ตัดแล้วและรอพิจารณาของหลายกิจกรรมในครั้งเดียว: [activity_id] => [spent, pending] */
    private function usage($activityIds): array
    {
        $spent = BudgetRequestCut::join('budget_requests', 'budget_requests.id', '=', 'budget_request_cuts.budget_request_id')
            ->whereIn('budget_requests.project_activity_id', $activityIds)
            ->selectRaw('budget_requests.project_activity_id as aid, sum(budget_request_cuts.amount) as total')->groupBy('aid')->pluck('total', 'aid');
        $pending = BudgetRequest::whereIn('project_activity_id', $activityIds)->where('status', 'pending')
            ->selectRaw('project_activity_id as aid, sum(total) as total')->groupBy('aid')->pluck('total', 'aid');
        $out = [];
        foreach ($activityIds as $id) {
            $out[$id] = ['spent' => (float) ($spent[$id] ?? 0), 'pending' => (float) ($pending[$id] ?? 0)];
        }

        return $out;
    }

    /* ---------------- ภาพรวมปีงบประมาณ + ประเภทเงิน ---------------- */

    public function index(Request $request)
    {
        $year = $this->year($request);
        $sources = BudgetSource::where('fiscal_year', $year)->orderBy('id')->get();
        $allocated = ActivityBudget::whereIn('budget_source_id', $sources->pluck('id'))->selectRaw('budget_source_id, sum(amount) as total')->groupBy('budget_source_id')->pluck('total', 'budget_source_id');
        $spent = BudgetRequestCut::whereIn('budget_source_id', $sources->pluck('id'))->selectRaw('budget_source_id, sum(amount) as total')->groupBy('budget_source_id')->pluck('total', 'budget_source_id');

        return view('budget.index', [
            'year' => $year,
            'years' => BudgetSource::distinct()->pluck('fiscal_year')->merge(Project::distinct()->pluck('fiscal_year'))->push($year)->unique()->sortDesc()->values(),
            'rows' => $sources->map(fn (BudgetSource $s) => ['source' => $s, 'allocated' => (float) ($allocated[$s->id] ?? 0), 'spent' => (float) ($spent[$s->id] ?? 0)]),
            'pending' => (float) BudgetRequest::where('status', 'pending')->whereHas('activity.project', fn ($q) => $q->where('fiscal_year', $year))->sum('total'),
            'projectCount' => Project::where('fiscal_year', $year)->count(),
            'steps' => BudgetRequest::configuredSteps(),
        ]);
    }

    public function storeSource(Request $request)
    {
        $source = BudgetSource::create($this->sourceData($request));
        Audit::log('finance.budget', $source, "เพิ่มประเภทเงิน {$source->name} ปีงบประมาณ {$source->fiscal_year} ".baht($source->amount).' บาท');

        return back()->with('success', 'เพิ่มประเภทเงินแล้ว');
    }

    /** สร้างประเภทเงิน 4 ประเภทที่โรงเรียนใช้ทั่วไป (วงเงิน 0 ให้กรอกภายหลัง) ข้ามชื่อที่มีอยู่แล้ว */
    public function standardSources(Request $request)
    {
        $data = $request->validate(['fiscal_year' => ['required', 'integer', 'min:2500', 'max:2700']]);
        $made = 0;
        foreach (BudgetSource::STANDARD as $name) {
            $made += (int) BudgetSource::firstOrCreate(['fiscal_year' => $data['fiscal_year'], 'name' => $name], ['amount' => 0])->wasRecentlyCreated;
        }

        return back()->with('success', $made ? "สร้างประเภทเงิน {$made} ประเภทแล้ว ใส่วงเงินของแต่ละประเภทได้เลย" : 'มีประเภทเงินมาตรฐานครบแล้ว');
    }

    public function updateSource(Request $request, BudgetSource $source)
    {
        $data = $this->sourceData($request);
        // ลดวงเงินต่ำกว่าที่จัดสรรให้กิจกรรมไปแล้วไม่ได้
        if ((float) $data['amount'] + 0.001 < $source->allocated()) {
            return back()->with('warning', 'วงเงินใหม่น้อยกว่าที่จัดสรรให้กิจกรรมไปแล้ว ('.baht($source->allocated()).' บาท)');
        }
        $source->fill($data);
        if ($source->isDirty('amount')) {
            Audit::log('finance.budget', $source, "แก้วงเงินของ{$source->name}", ['amount' => [$source->getOriginal('amount'), $source->amount]]);
        }
        $source->save();

        return back()->with('success', 'บันทึกประเภทเงินแล้ว');
    }

    public function destroySource(BudgetSource $source)
    {
        if ($source->budgets()->exists()) {
            return back()->with('warning', 'ประเภทเงินนี้จัดสรรให้กิจกรรมแล้ว ลบไม่ได้');
        }
        $source->delete();

        return back()->with('success', 'ลบประเภทเงินแล้ว');
    }

    private function sourceData(Request $request): array
    {
        return $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2500', 'max:2700'],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['fiscal_year' => 'ปีงบประมาณ', 'name' => 'ชื่อประเภทเงิน', 'amount' => 'วงเงิน']);
    }

    /** ขั้นพิจารณาคำขอใช้งบ (ขั้นตัดงบมีเสมอ · มีผลกับใบที่ยื่นหลังจากนี้) */
    public function steps(Request $request)
    {
        $data = $request->validate(['steps' => ['nullable', 'array'], 'steps.*' => [Rule::in(BudgetRequest::OPTIONAL_STEPS)]]);
        $steps = array_values(array_intersect(BudgetRequest::OPTIONAL_STEPS, $data['steps'] ?? []));
        Settings::set(['budget_request_steps' => implode(',', $steps)]);
        Audit::log('setting.update', null, 'ตั้งขั้นพิจารณาคำขอใช้งบ: '.implode(' → ', array_map(fn ($k) => BudgetRequest::STEPS[$k][0], BudgetRequest::configuredSteps())));

        return back()->with('success', 'บันทึกขั้นพิจารณาแล้ว มีผลกับใบที่ยื่นหลังจากนี้');
    }

    /* ---------------- โครงการ ---------------- */

    public function projects(Request $request)
    {
        $user = $request->user();
        $year = $this->year($request);
        $projects = Project::visibleTo($user)->with(['department', 'owner', 'activities.budgets'])->where('fiscal_year', $year)->orderBy('code')->get();
        $usage = $this->usage($projects->flatMap->activities->pluck('id'));

        return view('budget.projects', [
            'year' => $year,
            'years' => Project::distinct()->pluck('fiscal_year')->push($year)->unique()->sortDesc()->values(),
            'projects' => $projects->map(fn (Project $p) => ['project' => $p, 'budget' => (float) $p->activities->flatMap->budgets->sum('amount'),
                'spent' => $p->activities->sum(fn ($a) => $usage[$a->id]['spent']), 'pending' => $p->activities->sum(fn ($a) => $usage[$a->id]['pending'])]),
            'canManage' => $user->hasPermission('budget.manage'),
            'departments' => Department::orderBy('sort')->orderBy('name')->get(),
            'staff' => User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->orderBy('name')->get(['id', 'name', 'position']),
        ]);
    }

    private function projectData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'fiscal_year' => ['required', 'integer', 'min:2500', 'max:2700'],
            'track' => ['required', Rule::in(array_keys(Project::TRACKS))],
            'department_id' => ['nullable', 'exists:departments,id'],
            'owner_id' => ['required', Rule::exists('users', 'id')->whereIn('role', ['admin', 'teacher'])],
            'objective' => ['nullable', 'string', 'max:5000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ], [], ['name' => 'ชื่อโครงการ', 'fiscal_year' => 'ปีงบประมาณ', 'owner_id' => 'ผู้รับผิดชอบ', 'ends_on' => 'วันสิ้นสุด', 'starts_on' => 'วันเริ่ม', 'track' => 'กลุ่ม']);
    }

    public function storeProject(Request $request)
    {
        $data = $this->projectData($request);
        $project = DB::transaction(fn () => Project::create($data + ['code' => Project::nextCode((int) $data['fiscal_year']), 'created_by' => $request->user()->id]));
        Audit::log('finance.budget', $project, "ตั้งโครงการ {$project->code} {$project->name}");

        return redirect()->route('projects.show', $project)->with('success', "ตั้งโครงการ {$project->code} แล้ว เพิ่มกิจกรรมและงบได้เลย");
    }

    public function showProject(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless(Project::seesAll($user) || $project->owner_id === $user->id, 403);
        $project->load(['department', 'owner', 'activities.budgets.source']);
        $usage = $this->usage($project->activities->pluck('id'));
        $sources = BudgetSource::where('fiscal_year', $project->fiscal_year)->orderBy('id')->get();
        $allocated = ActivityBudget::whereIn('budget_source_id', $sources->pluck('id'))->selectRaw('budget_source_id, sum(amount) as total')->groupBy('budget_source_id')->pluck('total', 'budget_source_id');

        return view('budget.project', [
            'project' => $project,
            'usage' => $usage,
            'requests' => $project->requests()->with(['requester', 'activity'])->latest('budget_requests.id')->get(),
            'canManage' => $user->hasPermission('budget.manage'),
            'sources' => $sources,
            'sourceLeft' => $sources->mapWithKeys(fn (BudgetSource $s) => [$s->id => round((float) $s->amount - (float) ($allocated[$s->id] ?? 0), 2)]),
            'departments' => Department::orderBy('sort')->orderBy('name')->get(),
            'staff' => User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->orderBy('name')->get(['id', 'name', 'position']),
        ]);
    }

    public function updateProject(Request $request, Project $project)
    {
        $data = $this->projectData($request);
        // ย้ายปีงบประมาณไม่ได้เมื่อมีงบแล้ว เพราะงบผูกกับประเภทเงินของปีเดิม
        if ((int) $data['fiscal_year'] !== $project->fiscal_year && ActivityBudget::whereIn('project_activity_id', $project->activities()->pluck('id'))->exists()) {
            return back()->with('warning', 'โครงการนี้มีงบแล้ว เปลี่ยนปีงบประมาณไม่ได้');
        }
        $project->update($data);

        return back()->with('success', 'บันทึกโครงการแล้ว');
    }

    /** ปิด/เปิดโครงการ: ปิดแล้วขอใช้งบเพิ่มไม่ได้ และต้องไม่มีคำขอที่รอพิจารณาค้าง */
    public function closeProject(Request $request, Project $project)
    {
        if ($project->isClosed()) {
            $project->update(['status' => 'active']);
            Audit::log('finance.budget', $project, "เปิดโครงการ {$project->code} อีกครั้ง");

            return back()->with('success', 'เปิดโครงการอีกครั้งแล้ว');
        }
        $data = $request->validate(['summary' => ['required', 'string', 'max:5000']], [], ['summary' => 'สรุปผลโครงการ']);
        if ($project->requests()->where('budget_requests.status', 'pending')->exists()) {
            return back()->with('warning', 'ยังมีคำขอใช้งบที่รอพิจารณา ให้พิจารณาหรือยกเลิกก่อนปิดโครงการ');
        }
        $project->update(['status' => 'closed', 'summary' => $data['summary']]);
        Audit::log('finance.budget', $project, "ปิดโครงการ {$project->code} {$project->name}");

        return back()->with('success', 'ปิดโครงการแล้ว');
    }

    /* ---------------- กิจกรรมและงบของกิจกรรม ---------------- */

    /** เพิ่มหรือแก้กิจกรรมพร้อมงบแยกประเภทเงินในฟอร์มเดียว (ช่องว่างหรือ 0 = ไม่ใช้เงินประเภทนั้น) */
    public function saveActivity(Request $request, Project $project, ?ProjectActivity $activity = null)
    {
        abort_if($activity && $activity->project_id !== $project->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'detail' => ['nullable', 'string', 'max:2000'],
            'budgets' => ['nullable', 'array'],
            'budgets.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ], [], ['name' => 'ชื่อกิจกรรม', 'budgets.*' => 'งบ']);

        $problem = DB::transaction(function () use ($project, $activity, $data) {
            $sources = BudgetSource::where('fiscal_year', $project->fiscal_year)->lockForUpdate()->get()->keyBy('id');
            $activity = $activity ?? new ProjectActivity(['project_id' => $project->id]);
            $spent = $activity->exists ? $activity->bySource() : collect();
            $amounts = [];
            foreach ($sources as $id => $source) {
                $amount = round((float) ($data['budgets'][$id] ?? 0), 2);
                $current = $activity->exists ? $activity->budgets()->where('budget_source_id', $id)->first() : null;
                if ($source->allocated($current?->id) + $amount > (float) $source->amount + 0.001) {
                    return "เกินวงเงินของ{$source->name} (เหลือจัดสรรได้ ".baht(max(0, (float) $source->amount - $source->allocated($current?->id))).' บาท)';
                }
                if ($amount + 0.001 < ($spent[$id]['spent'] ?? 0)) {
                    return "งบ{$source->name}น้อยกว่าที่ตัดไปแล้ว (".baht($spent[$id]['spent']).' บาท)';
                }
                $amounts[$id] = $amount;
            }
            // งบรวมใหม่ต้องไม่น้อยกว่าที่ตัดแล้วรวมกับที่รอพิจารณา
            if ($activity->exists && array_sum($amounts) + 0.001 < $activity->spent() + $activity->pending()) {
                return 'งบรวมของกิจกรรมน้อยกว่ายอดที่ตัดแล้วและรอพิจารณา ('.baht($activity->spent() + $activity->pending()).' บาท)';
            }
            $before = $activity->exists ? $activity->total() : null;
            $activity->fill(['name' => $data['name'], 'detail' => $data['detail'] ?? null])->save();
            foreach ($amounts as $id => $amount) {
                $amount > 0
                    ? $activity->budgets()->updateOrCreate(['budget_source_id' => $id], ['amount' => $amount])
                    : $activity->budgets()->where('budget_source_id', $id)->delete();
            }
            Audit::log('finance.budget', $project, "ตั้งงบกิจกรรม {$activity->name} ของโครงการ {$project->code}", ['total' => [$before, array_sum($amounts)]]);

            return null;
        });

        return back()->with($problem ? 'warning' : 'success', $problem ?? 'บันทึกกิจกรรมแล้ว');
    }

    public function destroyActivity(ProjectActivity $activity)
    {
        if ($activity->requests()->exists()) {
            return back()->with('warning', 'กิจกรรมนี้มีคำขอใช้งบแล้ว ลบไม่ได้');
        }
        Audit::log('finance.budget', $activity->project, "ลบกิจกรรม {$activity->name} ของโครงการ {$activity->project->code} งบ ".baht($activity->total()).' บาท');
        $activity->delete();

        return back()->with('success', 'ลบกิจกรรมแล้ว');
    }
}
