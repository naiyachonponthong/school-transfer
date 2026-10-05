<?php

namespace App\Http\Controllers;

use App\Models\BudgetSource;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Support\Audit;
use App\Support\CodeSeries;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * งบประมาณและโครงการ: แหล่งเงินของปีงบประมาณ โครงการ และงบของโครงการแยกแหล่งเงิน/หมวดรายจ่าย
 * ผู้มีสิทธิ์ budget.manage ตั้งและแก้ · ผู้รับผิดชอบโครงการเห็นโครงการของตัวเอง
 */
class BudgetController extends Controller
{
    private function year(Request $request): int
    {
        return (int) $request->query('year', CodeSeries::fiscalYear(now()));
    }

    /** ยอดผูกพันและรออนุมัติของหลายบรรทัดงบในครั้งเดียว: [project_budget_id][status] => ยอด */
    private function usage($budgetIds)
    {
        return PurchaseRequest::whereIn('project_budget_id', $budgetIds)->whereIn('status', ['approved', 'pending'])
            ->selectRaw('project_budget_id, status, sum(total) as total')->groupBy('project_budget_id', 'status')->get()
            ->groupBy('project_budget_id')->map(fn ($rows) => $rows->pluck('total', 'status')->map(fn ($v) => (float) $v));
    }

    /* ---------------- ภาพรวมปีงบประมาณ + แหล่งเงิน ---------------- */

    public function index(Request $request)
    {
        $year = $this->year($request);
        $sources = BudgetSource::where('fiscal_year', $year)->orderBy('name')->get();
        $lines = ProjectBudget::whereIn('budget_source_id', $sources->pluck('id'))->get();
        $usage = $this->usage($lines->pluck('id'));

        $rows = $sources->map(function (BudgetSource $s) use ($lines, $usage) {
            $mine = $lines->where('budget_source_id', $s->id);

            return ['source' => $s, 'allocated' => (float) $mine->sum('amount'),
                'committed' => $mine->sum(fn ($l) => $usage[$l->id]['approved'] ?? 0), 'pending' => $mine->sum(fn ($l) => $usage[$l->id]['pending'] ?? 0)];
        });

        return view('budget.index', [
            'year' => $year,
            'years' => BudgetSource::distinct()->pluck('fiscal_year')->merge(Project::distinct()->pluck('fiscal_year'))->push($year)->unique()->sortDesc()->values(),
            'rows' => $rows,
            'projectCount' => Project::where('fiscal_year', $year)->count(),
            'steps' => PurchaseRequest::configuredSteps(),
        ]);
    }

    public function storeSource(Request $request)
    {
        $source = BudgetSource::create($this->sourceData($request));
        Audit::log('finance.budget', $source, "เพิ่มแหล่งเงิน {$source->name} ปีงบประมาณ {$source->fiscal_year} ".baht($source->amount).' บาท');

        return back()->with('success', 'เพิ่มแหล่งเงินแล้ว');
    }

    public function updateSource(Request $request, BudgetSource $source)
    {
        $data = $this->sourceData($request);
        // ลดวงเงินต่ำกว่าที่จัดสรรให้โครงการไปแล้วไม่ได้
        if ((float) $data['amount'] + 0.001 < $source->allocated()) {
            return back()->with('warning', 'วงเงินใหม่น้อยกว่าที่จัดสรรให้โครงการไปแล้ว ('.baht($source->allocated()).' บาท)');
        }
        $source->fill($data);
        if ($source->isDirty('amount')) {
            Audit::log('finance.budget', $source, "แก้วงเงินของแหล่งเงิน {$source->name}", ['amount' => [$source->getOriginal('amount'), $source->amount]]);
        }
        $source->save();

        return back()->with('success', 'บันทึกแหล่งเงินแล้ว');
    }

    public function destroySource(BudgetSource $source)
    {
        if ($source->budgets()->exists()) {
            return back()->with('warning', 'แหล่งเงินนี้จัดสรรให้โครงการแล้ว ลบไม่ได้');
        }
        $source->delete();

        return back()->with('success', 'ลบแหล่งเงินแล้ว');
    }

    private function sourceData(Request $request): array
    {
        return $request->validate([
            'fiscal_year' => ['required', 'integer', 'min:2500', 'max:2700'],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['fiscal_year' => 'ปีงบประมาณ', 'name' => 'ชื่อแหล่งเงิน', 'amount' => 'วงเงิน']);
    }

    /** ขั้นอนุมัติของใบขอซื้อ/ขอจ้าง (มีผลกับใบที่ยื่นหลังจากนี้) */
    public function steps(Request $request)
    {
        $data = $request->validate(['steps' => ['required', 'array', 'min:1'], 'steps.*' => [Rule::in(array_keys(PurchaseRequest::STEPS))]],
            ['steps.required' => 'ต้องมีขั้นอนุมัติอย่างน้อย 1 ขั้น'], ['steps' => 'ขั้นอนุมัติ']);
        $steps = array_values(array_intersect(array_keys(PurchaseRequest::STEPS), $data['steps']));
        Settings::set(['purchase_steps' => implode(',', $steps)]);
        Audit::log('setting.update', null, 'ตั้งขั้นอนุมัติใบขอซื้อ/ขอจ้าง: '.implode(' → ', array_map(fn ($k) => PurchaseRequest::STEPS[$k][0], $steps)));

        return back()->with('success', 'บันทึกขั้นอนุมัติแล้ว มีผลกับใบที่ยื่นหลังจากนี้');
    }

    /* ---------------- โครงการ ---------------- */

    public function projects(Request $request)
    {
        $user = $request->user();
        $year = $this->year($request);
        $projects = Project::visibleTo($user)->with(['department', 'owner', 'budgets'])->where('fiscal_year', $year)->orderBy('code')->get();
        $usage = $this->usage($projects->flatMap->budgets->pluck('id'));

        return view('budget.projects', [
            'year' => $year,
            'years' => Project::distinct()->pluck('fiscal_year')->push($year)->unique()->sortDesc()->values(),
            'projects' => $projects->map(fn (Project $p) => ['project' => $p, 'budget' => (float) $p->budgets->sum('amount'),
                'committed' => $p->budgets->sum(fn ($l) => $usage[$l->id]['approved'] ?? 0), 'pending' => $p->budgets->sum(fn ($l) => $usage[$l->id]['pending'] ?? 0)]),
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
            'department_id' => ['nullable', 'exists:departments,id'],
            'owner_id' => ['required', Rule::exists('users', 'id')->whereIn('role', ['admin', 'teacher'])],
            'objective' => ['nullable', 'string', 'max:5000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ], [], ['name' => 'ชื่อโครงการ', 'fiscal_year' => 'ปีงบประมาณ', 'owner_id' => 'ผู้รับผิดชอบ', 'ends_on' => 'วันสิ้นสุด', 'starts_on' => 'วันเริ่ม']);
    }

    public function storeProject(Request $request)
    {
        $data = $this->projectData($request);
        $project = DB::transaction(fn () => Project::create($data + ['code' => Project::nextCode((int) $data['fiscal_year']), 'created_by' => $request->user()->id]));
        Audit::log('finance.budget', $project, "ตั้งโครงการ {$project->code} {$project->name}");

        return redirect()->route('projects.show', $project)->with('success', "ตั้งโครงการ {$project->code} แล้ว เพิ่มงบของโครงการได้เลย");
    }

    public function showProject(Request $request, Project $project)
    {
        $user = $request->user();
        abort_unless(Project::seesAll($user) || $project->owner_id === $user->id, 403);
        $project->load(['department', 'owner', 'budgets.source']);
        $usage = $this->usage($project->budgets->pluck('id'));

        return view('budget.project', [
            'project' => $project,
            'lines' => $project->budgets->sortBy(fn ($l) => [$l->source->name, array_search($l->category, array_keys(ProjectBudget::CATEGORIES))])->values()
                ->map(fn (ProjectBudget $l) => ['line' => $l, 'committed' => $usage[$l->id]['approved'] ?? 0, 'pending' => $usage[$l->id]['pending'] ?? 0]),
            'requests' => $project->requests()->with(['requester', 'budget.source'])->latest('purchase_requests.id')->get(),
            'canManage' => $user->hasPermission('budget.manage'),
            'sources' => BudgetSource::where('fiscal_year', $project->fiscal_year)->orderBy('name')->get(),
            'departments' => Department::orderBy('sort')->orderBy('name')->get(),
            'staff' => User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->orderBy('name')->get(['id', 'name', 'position']),
        ]);
    }

    public function updateProject(Request $request, Project $project)
    {
        $data = $this->projectData($request);
        // ย้ายปีงบประมาณไม่ได้เมื่อมีงบแล้ว เพราะงบผูกกับแหล่งเงินของปีเดิม
        if ((int) $data['fiscal_year'] !== $project->fiscal_year && $project->budgets()->exists()) {
            return back()->with('warning', 'โครงการนี้มีงบแล้ว เปลี่ยนปีงบประมาณไม่ได้');
        }
        $project->update($data);

        return back()->with('success', 'บันทึกโครงการแล้ว');
    }

    /** ปิด/เปิดโครงการ: ปิดแล้วขอซื้อเพิ่มไม่ได้ และต้องไม่มีใบที่รออนุมัติค้าง */
    public function closeProject(Request $request, Project $project)
    {
        if ($project->isClosed()) {
            $project->update(['status' => 'active']);
            Audit::log('finance.budget', $project, "เปิดโครงการ {$project->code} อีกครั้ง");

            return back()->with('success', 'เปิดโครงการอีกครั้งแล้ว');
        }
        $data = $request->validate(['summary' => ['required', 'string', 'max:5000']], [], ['summary' => 'สรุปผลโครงการ']);
        if ($project->requests()->where('purchase_requests.status', 'pending')->exists()) {
            return back()->with('warning', 'ยังมีใบขอซื้อ/ขอจ้างที่รออนุมัติ ให้พิจารณาหรือยกเลิกก่อนปิดโครงการ');
        }
        $project->update(['status' => 'closed', 'summary' => $data['summary']]);
        Audit::log('finance.budget', $project, "ปิดโครงการ {$project->code} {$project->name}");

        return back()->with('success', 'ปิดโครงการแล้ว');
    }

    /* ---------------- งบของโครงการ ---------------- */

    public function saveLine(Request $request, Project $project)
    {
        $data = $request->validate([
            'budget_source_id' => ['required', Rule::exists('budget_sources', 'id')->where('fiscal_year', $project->fiscal_year)],
            'category' => ['required', Rule::in(array_keys(ProjectBudget::CATEGORIES))],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],
        ], [], ['budget_source_id' => 'แหล่งเงิน', 'category' => 'หมวดรายจ่าย', 'amount' => 'จำนวนเงิน']);

        $problem = DB::transaction(function () use ($project, $data) {
            $source = BudgetSource::whereKey($data['budget_source_id'])->lockForUpdate()->firstOrFail();
            $line = $project->budgets()->where('budget_source_id', $source->id)->where('category', $data['category'])->first();
            $amount = (float) $data['amount'];
            if ($source->allocated($line?->id) + $amount > (float) $source->amount + 0.001) {
                return "เกินวงเงินของ{$source->name} (เหลือจัดสรรได้ ".baht(max(0, (float) $source->amount - $source->allocated($line?->id))).' บาท)';
            }
            if ($line && $amount + 0.001 < $line->committed() + $line->pending()) {
                return 'น้อยกว่ายอดที่ผูกพันและรออนุมัติของบรรทัดนี้แล้ว ('.baht($line->committed() + $line->pending()).' บาท)';
            }
            $before = $line?->amount;
            $line = $project->budgets()->updateOrCreate(['budget_source_id' => $source->id, 'category' => $data['category']], ['amount' => $amount]);
            Audit::log('finance.budget', $project, "ตั้งงบโครงการ {$project->code}: {$line->label()}", ['amount' => [$before, $line->amount]]);

            return null;
        });

        return back()->with($problem ? 'warning' : 'success', $problem ?? 'บันทึกงบของโครงการแล้ว');
    }

    public function destroyLine(ProjectBudget $line)
    {
        if ($line->requests()->exists()) {
            return back()->with('warning', 'บรรทัดงบนี้มีใบขอซื้อ/ขอจ้างแล้ว ลบไม่ได้');
        }
        Audit::log('finance.budget', $line->project, "ลบงบโครงการ {$line->project->code}: {$line->label()} ".baht($line->amount).' บาท');
        $line->delete();

        return back()->with('success', 'ลบบรรทัดงบแล้ว');
    }
}
