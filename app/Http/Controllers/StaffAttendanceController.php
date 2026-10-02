<?php

namespace App\Http\Controllers;

use App\Models\StaffAttendance;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StaffAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $today = StaffAttendance::where('user_id', $user->id)->where('date', today()->toDateString())->first();
        $history = StaffAttendance::where('user_id', $user->id)
            ->where('date', '>=', today()->subDays(30)->toDateString())
            ->orderByDesc('date')->get();

        return view('checkin.index', compact('today', 'history'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $action = $request->input('action', 'in');
        $record = StaffAttendance::firstOrNew(['user_id' => $user->id, 'date' => today()->toDateString()]);

        // ตรวจพิกัด GPS ว่าอยู่ในรัศมีโรงเรียน (เฉพาะลงเวลาเข้า/ออก)
        if (in_array($action, ['in', 'out'], true) && ($geo = $this->checkGeo($request)) !== true) {
            return back()->withErrors(['gps' => $geo]);
        }
        if ($request->filled('lat')) {
            $record->fill(['lat' => $request->float('lat'), 'lng' => $request->float('lng'), 'distance_m' => $this->distance($request)]);
        }

        if ($action === 'in' && ! $record->check_in) {
            $record->check_in = now()->format('H:i:s');
            $record->status = now()->format('H:i') > Settings::get('staff_late_time', '08:00') ? 'late' : 'present';
            $msg = 'ลงเวลาเข้างาน '.now()->format('H:i').' น. แล้ว';
        } elseif ($action === 'out') {
            $record->check_in ??= now()->format('H:i:s');
            $record->check_out = now()->format('H:i:s');
            $msg = 'ลงเวลากลับ '.now()->format('H:i').' น. แล้ว';
        } elseif (in_array($action, ['leave', 'duty'], true)) {
            $record->status = $action;
            $record->note = $request->input('note');
            $msg = 'บันทึกสถานะแล้ว';
        } else {
            $msg = 'ลงเวลาไปแล้ววันนี้';
        }
        $record->save();

        return back()->with('success', $msg);
    }

    /** @return true|string ข้อความผิดพลาดถ้าไม่ผ่าน */
    private function checkGeo(Request $request): bool|string
    {
        if (! Settings::get('gps_required') || ! is_numeric(Settings::get('school_lat')) || ! is_numeric(Settings::get('school_lng'))) {
            return true;
        }
        if (! $request->filled('lat') || ! $request->filled('lng')) {
            return 'ต้องเปิดตำแหน่ง (GPS) ของเครื่องก่อนลงเวลา';
        }
        $d = $this->distance($request);
        $radius = (int) Settings::get('gps_radius', 300);

        return $d <= $radius ? true : "คุณอยู่ห่างจากโรงเรียน ".number_format($d)." เมตร (ต้องไม่เกิน {$radius} เมตร)";
    }

    /** ระยะทางจากโรงเรียน (เมตร) ด้วยสูตร Haversine */
    private function distance(Request $request): ?int
    {
        $lat0 = Settings::get('school_lat');
        $lng0 = Settings::get('school_lng');
        if (! is_numeric($lat0) || ! is_numeric($lng0) || ! $request->filled('lat')) {
            return null;
        }
        [$la1, $lo1, $la2, $lo2] = array_map('deg2rad', [(float) $lat0, (float) $lng0, $request->float('lat'), $request->float('lng')]);
        $a = sin(($la2 - $la1) / 2) ** 2 + cos($la1) * cos($la2) * sin(($lo2 - $lo1) / 2) ** 2;

        return (int) round(6371000 * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    public function report(Request $request)
    {
        $date = Carbon::parse($request->query('date', today()->toDateString()));
        $staff = User::whereIn('role', ['teacher', 'admin'])->where('is_active', true)->orderBy('name')->get();
        $records = StaffAttendance::where('date', $date->toDateString())->get()->keyBy('user_id');

        return view('checkin.report', compact('date', 'staff', 'records'));
    }
}
