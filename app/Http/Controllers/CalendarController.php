<?php

namespace App\Http\Controllers;

use App\Models\SchoolEvent;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $month = Carbon::parse(($request->query('month') ?: today()->format('Y-m')).'-01');
        $from = $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $to = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $events = SchoolEvent::visibleTo($request->user())
            ->overlapping($from->toDateString(), $to->toDateString())
            ->orderBy('start_date')->get();

        return view('calendar.index', [
            'month' => $month,
            'days' => collect(CarbonPeriod::create($from, $to)),
            'events' => $events,
            'upcoming' => SchoolEvent::visibleTo($request->user())->where('end_date', '>=', today()->toDateString())->orderBy('start_date')->limit(8)->get(),
        ]);
    }

    public function store(Request $request)
    {
        SchoolEvent::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return back()->with('success', 'เพิ่มกิจกรรมในปฏิทินแล้ว');
    }

    public function update(Request $request, SchoolEvent $event)
    {
        $event->update($this->validated($request));

        return back()->with('success', 'บันทึกแล้ว');
    }

    public function destroy(SchoolEvent $event)
    {
        $event->delete();

        return back()->with('success', 'ลบกิจกรรมแล้ว');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'type' => ['required', Rule::in(array_keys(SchoolEvent::TYPES))],
            'audience' => ['required', Rule::in(['all', 'staff'])],
        ]);
        $data['end_date'] ??= $data['start_date'];

        return $data;
    }
}
