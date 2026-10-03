<?php

namespace App\Http\Controllers;

use App\Models\Term;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TermController extends Controller
{
    public function index()
    {
        return view('terms.index', ['terms' => Term::withCount('courses')->orderByDesc('year')->orderByDesc('term')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2500', 'max:2700'],
            'term' => ['required', 'integer', 'in:1,2,3', Rule::unique('terms')->where('year', $request->year)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
        ], ['term.unique' => 'มีภาคเรียนนี้แล้ว']);

        $term = Term::create($data);
        if ($request->boolean('make_current') || Term::count() === 1) {
            $term->makeCurrent();
            Audit::log('setting.term', $term, 'ตั้ง'.$term->label().'เป็นภาคเรียนปัจจุบัน');
        }

        return back()->with('success', 'เพิ่ม'.$term->label().'แล้ว');
    }

    public function update(Request $request, Term $term)
    {
        $term->update($request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'results_announce_on' => ['nullable', 'date'],
        ]));

        return back()->with('success', 'บันทึกแล้ว');
    }

    public function makeCurrent(Term $term)
    {
        $term->makeCurrent();
        Audit::log('setting.term', $term, 'ตั้ง'.$term->label().'เป็นภาคเรียนปัจจุบัน');

        return back()->with('success', 'ตั้ง'.$term->label().'เป็นภาคเรียนปัจจุบันแล้ว');
    }
}
