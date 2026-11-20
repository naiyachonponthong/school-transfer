<?php

namespace App\Http\Controllers;

use App\Models\ItaEvidence;
use App\Models\ItaItem;
use App\Models\ItaYear;
use App\Support\Audit;
use App\Support\ItaTemplate;
use App\Support\Website;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** จัดการ ITA / OIT (แท็บในหน้าจัดการเว็บไซต์ สิทธิ์ website.manage) */
class ItaAdminController extends Controller
{
    private function back(?ItaYear $year)
    {
        return redirect()->route('website.admin', array_filter(['tab' => 'ita', 'year' => $year?->year]));
    }

    public function createYear(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2560,2700', Rule::unique('ita_years', 'year')],
            'from' => ['required', 'in:copy,template'],
        ], ['year.unique' => 'มีปีงบประมาณนี้แล้ว'], ['year' => 'ปีงบประมาณ']);

        $prev = $data['from'] === 'copy' ? ItaYear::where('year', '<', $data['year'])->orderByDesc('year')->first() : null;
        $year = ItaTemplate::createYear((int) $data['year'], $prev?->load('items'));
        Audit::log('website.ita_year', null, 'สร้าง ITA ปีงบประมาณ '.$year->year.($prev ? ' (คัดลอกหัวข้อจากปี '.$prev->year.')' : ' (รายการตั้งต้น)'));

        return $this->back($year)->with('success', 'สร้างปีงบประมาณ '.$year->year.' แล้ว '.$year->items()->count().' หัวข้อ');
    }

    public function updateYear(Request $request, ItaYear $year)
    {
        $year->update(['is_published' => $request->boolean('is_published'), 'note' => $request->string('note')->limit(250)->value() ?: null]);

        return $this->back($year)->with('success', 'บันทึกแล้ว');
    }

    public function destroyYear(ItaYear $year)
    {
        foreach ($year->items()->with('evidences')->get() as $item) {
            foreach ($item->evidences as $e) {
                Website::deleteFile($e->path);
            }
        }
        Audit::log('website.ita_year_delete', null, 'ลบ ITA ปีงบประมาณ '.$year->year);
        $year->delete();

        return $this->back(null)->with('success', 'ลบปีงบประมาณแล้ว');
    }

    public function saveItem(Request $request, ItaYear $year, ?ItaItem $item = null)
    {
        $item = $item?->exists ? $item : new ItaItem(['ita_year_id' => $year->id]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('ita_items', 'code')->where('ita_year_id', $year->id)->ignore($item->id)],
            'group' => ['nullable', 'string', 'max:150'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], ['code.unique' => 'มีหัวข้อรหัสนี้แล้วในปีนี้'], ['code' => 'รหัสข้อ', 'title' => 'หัวข้อ']);
        $item->fill($data + ['sort' => (int) ($data['sort'] ?? ($year->items()->max('sort') + 1))])->save();

        return $this->back($year)->with('success', 'บันทึกหัวข้อ '.$item->code.' แล้ว');
    }

    public function toggleItem(ItaItem $item)
    {
        $item->update(['done' => ! $item->done]);

        return $this->back($item->year);
    }

    public function destroyItem(ItaItem $item)
    {
        foreach ($item->evidences as $e) {
            Website::deleteFile($e->path);
        }
        $year = $item->year;
        $item->delete();

        return $this->back($year)->with('success', 'ลบหัวข้อแล้ว');
    }

    public function saveEvidence(Request $request, ItaItem $item)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(ItaEvidence::KINDS))],
            'title' => ['nullable', 'string', 'max:255'],
            'file' => ['exclude_unless:kind,file', 'required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,zip', 'max:20480'],
            'url' => ['exclude_unless:kind,link', 'required', 'url', 'max:1000'],
            'web_post_id' => ['exclude_unless:kind,post', 'required', 'exists:web_posts,id'],
        ], ['file.max' => 'ไฟล์ต้องไม่เกิน 20 MB'], ['file' => 'ไฟล์', 'url' => 'ลิงก์', 'web_post_id' => 'ข่าว']);

        $e = new ItaEvidence(['ita_item_id' => $item->id, 'kind' => $data['kind'], 'sort' => $item->evidences()->count() + 1]);
        if ($data['kind'] === 'file') {
            $f = Website::storeFile($request->file('file'));
            $e->path = $f['path'];
            $e->title = ($data['title'] ?? null) ?: $f['name'];
        } elseif ($data['kind'] === 'link') {
            $e->url = $data['url'];
            $e->title = ($data['title'] ?? null) ?: $data['url'];
        } else {
            $e->web_post_id = $data['web_post_id'];
            $e->title = ($data['title'] ?? null) ?: \App\Models\WebPost::find($data['web_post_id'])->title;
        }
        $e->save();
        // มีหลักฐานแล้ว ถือว่าครบ (ปลดได้เองถ้ายังไม่ครบ)
        if (! $item->done) {
            $item->update(['done' => true]);
        }

        return $this->back($item->year)->with('success', 'เพิ่มหลักฐานใน '.$item->code.' แล้ว');
    }

    public function destroyEvidence(ItaEvidence $evidence)
    {
        $item = $evidence->item;
        Website::deleteFile($evidence->path);
        $evidence->delete();

        return $this->back($item->year)->with('success', 'ลบหลักฐานแล้ว');
    }
}
