<?php

namespace App\Http\Controllers;

use App\Models\OfficeDocument;
use App\Models\User;
use App\Services\Notifier;
use App\Support\Audit;
use App\Support\Sequence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** งานสารบรรณ: ลงทะเบียนหนังสือ ออกเลขที่ เวียนให้บุคลากร และติดตามการรับทราบ */
class OfficeDocumentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $type = array_key_exists($request->query('type'), OfficeDocument::TYPES) ? $request->query('type') : null;

        $docs = OfficeDocument::visibleTo($user)->withCount(['recipients', 'recipients as acknowledged_count' => fn ($q) => $q->whereNotNull('office_document_user.acknowledged_at')])
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('subject', 'like', "%{$term}%")->orWhere('party', 'like', "%{$term}%")->orWhere('ref_no', 'like', "%{$term}%")))
            ->orderByDesc('doc_date')->orderByDesc('id')->paginate(40)->withQueryString();

        return view('office.index', [
            'docs' => $docs,
            'type' => $type,
            'canManage' => $user->hasPermission('office.manage'),
            'staff' => User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            // ฉบับที่เวียนถึงฉันและยังไม่ได้กดรับทราบ
            'unread' => DB::table('office_document_user')->where('user_id', $user->id)->whereNull('acknowledged_at')->pluck('office_document_id')->flip(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(OfficeDocument::TYPES))],
            'doc_date' => ['required', 'date'],
            'subject' => ['required', 'string', 'max:255'],
            'ref_no' => ['nullable', 'string', 'max:60'],
            'party' => ['nullable', 'string', 'max:255'],
            'urgency' => ['required', Rule::in(array_keys(OfficeDocument::URGENCY))],
            'note' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:20480'],
            'recipient_ids' => ['array'],
            'recipient_ids.*' => [Rule::exists('users', 'id')->whereIn('role', ['admin', 'teacher'])],
            'all_staff' => ['nullable', 'boolean'],
        ], [], ['subject' => 'เรื่อง', 'doc_date' => 'ลงวันที่']);

        $year = (int) date('Y', strtotime($data['doc_date'])) + 543;
        $doc = DB::transaction(function () use ($data, $year, $request) {
            $seq = Sequence::next('DOC-'.$data['type'], (string) $year, fn () => (int) OfficeDocument::where('type', $data['type'])->where('year', $year)->max('seq'));

            return OfficeDocument::create([
                'year' => $year, 'seq' => $seq, 'created_by' => $request->user()->id,
                'file' => $request->hasFile('file') ? $request->file('file')->store('office', 'local') : null,
            ] + collect($data)->only(['type', 'doc_date', 'subject', 'ref_no', 'party', 'urgency', 'note'])->all());
        });

        Audit::log('document.office', $doc, "ลงทะเบียน{$doc->typeLabel()} {$doc->number()}: {$doc->subject}");

        $ids = $request->boolean('all_staff')
            ? User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->pluck('id')->all()
            : ($data['recipient_ids'] ?? []);
        $this->circulate($doc, $ids);

        return redirect()->route('office.show', $doc)->with('success', "ลงทะเบียน{$doc->typeLabel()} เลขที่ {$doc->number()} แล้ว");
    }

    public function show(Request $request, OfficeDocument $document)
    {
        abort_unless($document->canBeViewedBy($request->user()), 403);

        return view('office.show', [
            'doc' => $document->load(['recipients' => fn ($q) => $q->orderBy('name'), 'creator']),
            'canManage' => $request->user()->hasPermission('office.manage'),
            'mine' => $document->recipients()->where('users.id', $request->user()->id)->first(),
            'staff' => User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** เวียนเพิ่มภายหลัง */
    public function addRecipients(Request $request, OfficeDocument $document)
    {
        $data = $request->validate(['recipient_ids' => ['required', 'array', 'min:1'], 'recipient_ids.*' => [Rule::exists('users', 'id')->whereIn('role', ['admin', 'teacher'])]]);
        $this->circulate($document, $data['recipient_ids']);

        return back()->with('success', 'เวียนหนังสือเพิ่มแล้ว');
    }

    public function acknowledge(Request $request, OfficeDocument $document)
    {
        $updated = DB::table('office_document_user')->where('office_document_id', $document->id)->where('user_id', $request->user()->id)
            ->whereNull('acknowledged_at')->update(['acknowledged_at' => now()]);
        abort_unless($updated || $document->canBeViewedBy($request->user()), 403);

        return back()->with('success', 'บันทึกการรับทราบแล้ว');
    }

    private function circulate(OfficeDocument $doc, array $userIds): void
    {
        $new = collect($userIds)->map(fn ($id) => (int) $id)->diff($doc->recipients()->pluck('users.id'))->values();
        if ($new->isEmpty()) {
            return;
        }
        $doc->recipients()->attach($new->all());
        Notifier::users(User::whereIn('id', $new)->get(),
            '📄 '.(OfficeDocument::URGENCY[$doc->urgency][0] !== 'ปกติ' ? '['.OfficeDocument::URGENCY[$doc->urgency][0].'] ' : '')
            ."{$doc->typeLabel()} {$doc->number()}: {$doc->subject} กรุณาเปิดอ่านและกดรับทราบ", route('office.show', $doc));
    }
}
