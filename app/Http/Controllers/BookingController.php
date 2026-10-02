<?php

namespace App\Http\Controllers;

use App\Models\BookableResource;
use App\Models\Booking;
use App\Models\User;
use App\Services\Notifier;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** จองห้อง / รถ / อุปกรณ์ — กันจองซ้อนอัตโนมัติ · รายการที่ต้องอนุมัติส่งให้งานอาคารสถานที่ */
class BookingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $date = rescue(fn () => Carbon::parse($request->query('date', today()->toDateString()))->startOfDay(), today(), false);
        $resources = BookableResource::where('is_active', true)
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->orderBy('type')->orderBy('name')->get();
        $bookings = Booking::with('user')->whereIn('resource_id', $resources->pluck('id'))->whereIn('status', Booking::HOLDING)
            ->where('starts_at', '<', $date->copy()->addDay())->where('ends_at', '>', $date)
            ->orderBy('starts_at')->get()->groupBy('resource_id');

        return view('bookings.index', [
            'date' => $date, 'resources' => $resources, 'bookings' => $bookings,
            'mine' => Booking::with('resource')->where('user_id', $user->id)->where('ends_at', '>=', now())->whereIn('status', ['pending', 'approved'])->orderBy('starts_at')->take(10)->get(),
            'pending' => $user->canManageFacilities() ? Booking::with(['resource', 'user'])->where('status', 'pending')->orderBy('starts_at')->get() : collect(),
            'manager' => $user->canManageFacilities(),
        ]);
    }

    public function create(Request $request)
    {
        return view('bookings.create', [
            'resources' => BookableResource::where('is_active', true)->orderBy('type')->orderBy('name')->get(),
            'selected' => (int) $request->query('resource'),
            'date' => $request->query('date', today()->toDateString()),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'resource_id' => ['required', Rule::exists('bookable_resources', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_date' => ['nullable', 'date', 'after_or_equal:date'],
            'end_time' => ['required', 'date_format:H:i'],
            'attendees' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'destination' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], ['title' => 'วัตถุประสงค์', 'resource_id' => 'ห้อง/รถ/อุปกรณ์']);
        $start = Carbon::parse($data['date'].' '.$data['start_time']);
        $end = Carbon::parse(($data['end_date'] ?? $data['date']).' '.$data['end_time']);
        if ($end <= $start) {
            throw ValidationException::withMessages(['end_time' => 'เวลาสิ้นสุดต้องหลังเวลาเริ่ม']);
        }
        if ($start->lt(now()->subMinutes(10))) {
            throw ValidationException::withMessages(['start_time' => 'จองย้อนหลังไม่ได้']);
        }
        $resource = BookableResource::findOrFail($data['resource_id']);
        $user = $request->user();

        $booking = DB::transaction(function () use ($data, $start, $end, $resource, $user) {
            // ล็อกแถวของห้อง/รถนี้ไว้ระหว่างเช็คเวลาซ้อน กันสองคนกดจองพร้อมกัน
            BookableResource::whereKey($resource->id)->lockForUpdate()->first();
            $clash = Booking::with('user')->overlapping($resource->id, $start, $end)->first();
            if ($clash) {
                throw ValidationException::withMessages(['start_time' => "{$resource->name} ถูกจองแล้ว {$clash->starts_at->format('H:i')}–{$clash->ends_at->format('H:i')} น. ({$clash->title} · {$clash->user?->name})"]);
            }

            return Booking::create([
                'resource_id' => $resource->id, 'user_id' => $user->id, 'title' => $data['title'],
                'starts_at' => $start, 'ends_at' => $end, 'attendees' => $data['attendees'] ?? null,
                'destination' => $data['destination'] ?? null, 'note' => $data['note'] ?? null,
                'status' => $resource->requires_approval && ! $user->canManageFacilities() ? 'pending' : 'approved',
            ]);
        });

        if ($booking->status === 'pending') {
            Notifier::users(self::managers()->reject(fn ($u) => $u->id === $user->id),
                "📅 ขอจอง{$resource->typeLabel()} {$resource->name} ".thai_date($start)." {$booking->timeRange()} · {$booking->title} โดย {$user->name}", route('bookings.index', ['date' => $start->toDateString()]));
        }

        return redirect()->route('bookings.index', ['date' => $start->toDateString()])
            ->with('success', $booking->status === 'pending' ? 'ส่งคำขอจองแล้ว รออนุมัติ — จะแจ้งผลทาง LINE' : "จอง {$resource->name} เรียบร้อย");
    }

    public function cancel(Request $request, Booking $booking)
    {
        $user = $request->user();
        abort_unless($booking->user_id === $user->id || $user->canManageFacilities(), 403);
        abort_unless(in_array($booking->status, Booking::HOLDING, true) && $booking->ends_at->isFuture(), 422, 'ยกเลิกไม่ได้');
        $booking->update(['status' => 'cancelled']);
        if ($booking->user_id !== $user->id) {
            Audit::log('booking.cancel', $booking, "ยกเลิกการจอง {$booking->resource->name} {$booking->title} ของ {$booking->user?->name}");
            $booking->user && Notifier::users([$booking->user], "📅 การจอง {$booking->resource->name} ".thai_date($booking->starts_at)." {$booking->timeRange()} ถูกยกเลิกโดย {$user->name}");
        }

        return back()->with('success', 'ยกเลิกการจองแล้ว');
    }

    public function review(Request $request, Booking $booking)
    {
        abort_unless($request->user()->canManageFacilities(), 403);
        abort_unless($booking->status === 'pending', 422, 'รายการนี้พิจารณาแล้ว');
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])], 'review_note' => ['nullable', 'string', 'max:255']]);
        $booking->update(['status' => $data['decision'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['review_note'] ?? null]);
        Audit::log('booking.review', $booking, ($data['decision'] === 'approved' ? 'อนุมัติ' : 'ไม่อนุมัติ')." การจอง {$booking->resource->name} {$booking->title} ของ {$booking->user?->name}");
        $booking->user && Notifier::users([$booking->user], ($data['decision'] === 'approved' ? '✅ อนุมัติ' : '❌ ไม่อนุมัติ')
            ." การจอง {$booking->resource->name} ".thai_date($booking->starts_at)." {$booking->timeRange()}".($booking->review_note ? "\n{$booking->review_note}" : ''));

        return back()->with('success', 'บันทึกผลการพิจารณาแล้ว');
    }

    /** จัดการรายการห้อง/รถ/อุปกรณ์ (งานอาคารสถานที่) */
    public function resources(Request $request)
    {
        abort_unless($request->user()->canManageFacilities(), 403);

        return view('bookings.resources', ['resources' => BookableResource::orderBy('type')->orderBy('name')->get()]);
    }

    public function saveResource(Request $request, ?BookableResource $resource = null)
    {
        abort_unless($request->user()->canManageFacilities(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(BookableResource::TYPES))],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $data += ['requires_approval' => $request->boolean('requires_approval'), 'is_active' => $request->boolean('is_active', true)];
        $resource && $resource->exists ? $resource->update($data) : BookableResource::create($data);

        return back()->with('success', 'บันทึกแล้ว');
    }

    private static function managers()
    {
        return User::where('is_active', true)->where(fn ($q) => $q->where('role', 'admin')->orWhereIn('id', User::facilityManagerIds()))->get();
    }
}
