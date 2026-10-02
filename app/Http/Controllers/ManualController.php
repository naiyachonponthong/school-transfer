<?php

namespace App\Http\Controllers;

use App\Support\Manual;
use Illuminate\Http\Request;

/** คู่มือการใช้งาน: สารบัญ + ค้นหา · อ่านทีละหัวข้อ · พิมพ์ทั้งเล่ม */
class ManualController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $topics = Manual::visible($user);
        $q = trim((string) $request->query('q'));
        $results = [];
        if (mb_strlen($q) >= 2) {
            foreach ($topics as $key => $t) {
                $text = self::plain($key);
                $inTitle = mb_stripos($t[0].' '.$t[4], $q) !== false;
                $hits = self::snippets($text, $q);
                if ($inTitle || $hits) {
                    $results[$key] = ['hits' => $hits, 'score' => ($inTitle ? 100 : 0) + count($hits)];
                }
            }
            uasort($results, fn ($a, $b) => $b['score'] <=> $a['score']);
        }

        return view('manual.index', [
            'topics' => $topics, 'q' => $q, 'results' => $results,
            'groups' => collect($topics)->groupBy(fn ($t) => $t[2], true)->sortBy(fn ($v, $g) => array_search($g, Manual::GROUPS)),
            'startHere' => array_values(array_filter(Manual::startHere($user), fn ($k) => isset($topics[$k]))),
            'role' => Manual::role($user),
        ]);
    }

    public function show(Request $request, string $topic)
    {
        $topics = Manual::visible($request->user());
        abort_unless(isset($topics[$topic]), 404);
        $keys = array_keys($topics);
        $i = array_search($topic, $keys, true);

        return view('manual.show', [
            'key' => $topic, 'topic' => $topics[$topic], 'topics' => $topics,
            'prev' => $keys[$i - 1] ?? null, 'next' => $keys[$i + 1] ?? null,
        ]);
    }

    /** พิมพ์ทั้งเล่ม (เฉพาะหัวข้อที่ผู้ใช้เห็น) หรือหัวข้อเดียว ?topic= */
    public function print(Request $request)
    {
        $topics = Manual::visible($request->user());
        if ($only = $request->query('topic')) {
            abort_unless(isset($topics[$only]), 404);
            $topics = [$only => $topics[$only]];
        }

        return view('manual.print', ['topics' => $topics, 'single' => (bool) $only]);
    }

    /** เนื้อหาเป็นข้อความล้วน (ใช้ค้นหา) */
    private static function plain(string $key): string
    {
        $html = view('manual.topics.'.$key)->render();
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html);
        $html = preg_replace('#<a [^>]*class="manual-anchor"[^>]*>.*?</a>#s', ' ', $html);
        $html = preg_replace('#<(/(h\d|li|p|div|tr|section)|br)\b#i', ' <$1', $html); // คั่นคำระหว่างบรรทัด/หัวข้อ

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html))));
    }

    /** ข้อความรอบคำที่ค้น (สูงสุด 3 จุด) */
    private static function snippets(string $text, string $q): array
    {
        $out = [];
        $offset = 0;
        $length = mb_strlen($text);
        while (count($out) < 3 && $offset < $length && ($pos = mb_stripos($text, $q, $offset)) !== false) {
            $start = max(0, $pos - 60);
            // เริ่มที่ช่องว่างถัดไป ไม่ตัดกลางคำ (สระ/วรรณยุกต์ไทยจะไม่หลุดมาต้นบรรทัด)
            $space = $start > 0 ? mb_strpos(mb_substr($text, $start, $pos - $start), ' ') : false;
            $start += $space === false ? 0 : $space + 1;
            $out[] = ($start > 0 ? '…' : '').mb_substr($text, $start, $pos - $start + mb_strlen($q) + 60).'…';
            $offset = $pos + mb_strlen($q) + 60;
        }

        return $out;
    }
}
