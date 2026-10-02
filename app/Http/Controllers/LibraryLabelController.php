<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookCopy;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * พิมพ์ป้ายติดเล่มบนกระดาษ A4: ป้ายสัน (เลขเรียก DDC) · บาร์โค้ดปกนอก · บาร์โค้ดปกใน · ครบชุด
 * เลือกได้: เล่มที่ยังไม่พิมพ์ · ช่วงเลขทะเบียน · หนังสือเรื่องเดียว · เล่มที่ระบุ · เว้นช่องแรกสำหรับสติกเกอร์ที่ใช้ไปแล้ว
 */
class LibraryLabelController extends Controller
{
    public const TYPES = [
        'spine' => ['ป้ายสันหนังสือ', 'เลขเรียกตามระบบ DDC · 2.5 × 4 ซม.', 42],
        'outer' => ['บาร์โค้ดปกนอก', 'ยิงยืม-คืน ติดปกหลัง/ปกหน้า · 5 × 2.5 ซม.', 30],
        'inner' => ['บาร์โค้ดปกใน', 'บาร์โค้ด + ชื่อเรื่อง เลขทะเบียน ราคา · 7 × 4 ซม.', 12],
        'all' => ['ครบชุด (3 แบบ)', 'ป้ายสัน + บาร์โค้ดปกนอก + ปกใน แยกหน้าให้', null],
    ];

    public function index(Request $request)
    {
        $source = $request->query('source', 'pending');
        $type = array_key_exists($request->query('type'), self::TYPES) ? $request->query('type') : 'all';
        $copies = $this->copies($request, $source);
        $skip = max(0, min(41, (int) $request->query('skip', 0)));
        $types = $type === 'all' ? ['spine', 'outer', 'inner'] : [$type];

        return view('library.labels', [
            'copies' => $copies, 'source' => $source, 'type' => $type, 'skip' => $skip,
            'band' => $request->query('band', '1') === '1', 'cut' => $request->query('cut', '1') === '1',
            // แบ่งหน้า: เว้น $skip ช่องแรกของหน้าแรก (สติกเกอร์ที่ใช้ไปแล้ว)
            'sheets' => collect($types)->mapWithKeys(fn ($t) => [$t => collect(array_fill(0, min($skip, self::TYPES[$t][2] - 1), null))
                ->concat($copies)->chunk(self::TYPES[$t][2])]),
            'book' => $source === 'book' ? Book::find($request->query('book')) : null,
            'pendingCount' => BookCopy::where('condition', '!=', 'withdrawn')->whereNull('label_printed_at')->count(),
        ]);
    }

    private function copies(Request $request, string $source): Collection
    {
        $q = BookCopy::with('book')->where('condition', '!=', 'withdrawn');
        match ($source) {
            'book' => $q->where('book_id', (int) $request->query('book')),
            'ids' => $q->whereIn('id', array_filter(array_map('intval', explode(',', (string) $request->query('ids'))))),
            'range' => $q->whereBetween('accession_no', [trim((string) $request->query('from')), trim((string) $request->query('to')) ?: trim((string) $request->query('from'))]),
            default => $q->whereNull('label_printed_at'),
        };

        // เรียงตามเลขทะเบียน (ตามลำดับรับเข้า) แล้วตามลำดับที่ลงระบบ
        return $q->orderByRaw('accession_no is null')->orderBy('accession_no')->orderBy('id')->limit(1000)->get();
    }

    /** บันทึกว่าพิมพ์และติดป้ายแล้ว (ออกจากคิว "ยังไม่พิมพ์") */
    public function printed(Request $request)
    {
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));
        $n = BookCopy::whereIn('id', $ids)->update(['label_printed_at' => now()]);
        Audit::log('library.labels', null, "บันทึกพิมพ์ป้ายแล้ว {$n} เล่ม");

        return redirect()->route('library.labels')->with('success', "บันทึกว่าพิมพ์ป้ายแล้ว {$n} เล่ม");
    }
}
