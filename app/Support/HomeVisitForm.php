<?php

namespace App\Support;

/**
 * โครงของ "บันทึกการเยี่ยมบ้าน" ตามแบบ 4 หน้าของ สพฐ. (รวมการคัดกรองนักเรียนยากจน)
 * นิยามหัวข้อและตัวเลือกที่เดียว ใช้ทั้งหน้ากรอก การตรวจข้อมูล และการสรุปด้านความเสี่ยง
 * คำตอบเก็บใน home_visits.form (JSON) โดยใช้ key ของแต่ละช่องตามที่นิยามไว้ที่นี่
 */
class HomeVisitForm
{
    /** ช่องข้อความ => ความยาวสูงสุด */
    public const TEXT = [
        'guardian_first' => 100, 'guardian_last' => 100, 'guardian_phone' => 20, 'guardian_relation' => 60,
        'guardian_occupation' => 100, 'guardian_education' => 100, 'guardian_citizen_id' => 13,
        'relation_other_name' => 60, 'left_with_other' => 100, 'expense_from' => 100, 'student_job' => 100,
        'help_wanted_other' => 100, 'assistance_other' => 100, 'concerns' => 2000, 'transport_other' => 100,
        'duties_other' => 100, 'hobbies_other' => 100, 'game_other' => 100, 'visitor_position' => 100,
    ];

    public const NUMBER = [
        'member_count', 'hours_together', 'income_per_head', 'student_income', 'allowance',
        'distance_km', 'travel_hours', 'travel_minutes',
    ];

    public const BOOL = ['no_guardian', 'no_id_card', 'welfare_registered', 'student_works'];

    /** ตอบได้ข้อเดียว (○) */
    public const SINGLE = [
        'housing_type' => ['own' => 'บ้านของตนเอง', 'rent' => 'บ้านเช่า', 'with_others' => 'อาศัยอยู่กับผู้อื่น'],
        'vehicle_car' => ['yes' => 'มี', 'no' => 'ไม่มี'],
        'vehicle_pickup' => ['yes' => 'มี', 'no' => 'ไม่มี'],
        'vehicle_tractor' => ['yes' => 'มี', 'no' => 'ไม่มี'],
        'left_with' => ['relative' => 'ญาติ', 'neighbor' => 'เพื่อนบ้าน', 'alone' => 'นักเรียนอยู่บ้านด้วยตนเอง', 'other' => 'อื่น ๆ'],
        'transport' => [
            'parent' => 'ผู้ปกครองมาส่ง', 'bus' => 'รถโดยสารประจำทาง', 'motorcycle' => 'รถจักรยานยนต์', 'school_bus' => 'รถโรงเรียน',
            'car' => 'รถยนต์', 'bicycle' => 'รถจักรยาน', 'walk' => 'เดิน', 'other' => 'อื่น ๆ',
        ],
        'internet' => ['yes' => 'สามารถเข้าถึง Internet ได้จากที่บ้าน', 'no' => 'ไม่สามารถเข้าถึง Internet ได้จากที่บ้าน'],
        'informant' => [
            'father' => 'บิดา', 'mother' => 'มารดา', 'brother' => 'พี่ชาย', 'sister' => 'พี่สาว', 'na' => 'น้า', 'ar' => 'อา', 'pa' => 'ป้า', 'lung' => 'ลุง',
            'pu' => 'ปู่', 'ya' => 'ย่า', 'ta' => 'ตา', 'yai' => 'ยาย', 'great' => 'ทวด', 'stepfather' => 'พ่อเลี้ยง', 'stepmother' => 'แม่เลี้ยง',
        ],
        'photo_kind' => [
            'parents' => 'บ้านที่อาศัยอยู่กับพ่อแม่ (เป็นเจ้าของ/เช่า)',
            'relative' => 'บ้านของญาติ/ผู้ปกครองที่ไม่ใช่ญาติ',
            'institution' => 'บ้านหรือที่พักประเภท วัด มูลนิธิ หอพัก โรงงาน อยู่กับนายจ้าง',
            'student_only' => 'ภาพนักเรียนและป้ายชื่อโรงเรียน เนื่องจากถ่ายภาพบ้านไม่ได้ (บ้านอยู่ต่างอำเภอ/ต่างจังหวัด/ต่างประเทศ หรือไม่ได้รับอนุญาตให้ถ่ายภาพ)',
        ],
    ];

    /** ตอบได้มากกว่า 1 ข้อ (☐) */
    public const MULTI = [
        'dependents' => ['disabled' => 'มีคนพิการ', 'elderly' => 'มีผู้สูงอายุเกิน 60 ปี', 'single_parent' => 'เป็นพ่อ/แม่เลี้ยงเดี่ยว', 'unemployed' => 'มีคนอายุ 15–65 ปีว่างงาน (ที่ไม่ใช่นักเรียน/นักศึกษา)'],
        'house_condition' => ['dilapidated' => 'สภาพบ้านชำรุดทรุดโทรม หรือบ้านทำจากวัสดุพื้นบ้าน เช่น ไม้ไผ่ ใบจาก หรือวัสดุเหลือใช้', 'no_toilet' => 'ไม่มีห้องส้วมในที่อยู่อาศัยและบริเวณ'],
        'farmland' => ['under_one_rai' => 'ไม่เกิน 1 ไร่', 'no_own_land' => 'ไม่มีที่ดินเป็นของตนเอง'],
        'help_wanted' => ['learning' => 'ด้านการเรียน', 'behavior' => 'ด้านพฤติกรรม', 'economic' => 'ด้านเศรษฐกิจ (เช่น ขอรับทุน)', 'other' => 'อื่น ๆ'],
        'assistance' => ['elderly' => 'เบี้ยผู้สูงอายุ', 'disability' => 'เบี้ยพิการ', 'other' => 'อื่น ๆ'],
        'health' => [
            'weak' => 'ร่างกายไม่แข็งแรง', 'chronic' => 'มีโรคประจำตัวหรือเจ็บป่วยบ่อย', 'malnutrition' => 'มีภาวะทุพโภชนาการ',
            'serious' => 'ป่วยเป็นโรคร้ายแรง/เรื้อรัง', 'low_fitness' => 'สมรรถภาพทางร่างกายต่ำ',
        ],
        'safety' => [
            'parents_separated' => 'พ่อแม่แยกทางกัน หรือแต่งงานใหม่', 'slum' => 'ที่พักอาศัยอยู่ในชุมชนแออัดหรือใกล้แหล่งมั่วสุม/สถานเริงรมย์',
            'family_illness' => 'มีบุคคลในครอบครัวเจ็บป่วยด้วยโรคร้ายแรง/เรื้อรัง/ติดต่อ', 'family_drugs' => 'บุคคลในครอบครัวติดสารเสพติด',
            'family_gambling' => 'บุคคลในครอบครัวเล่นการพนัน', 'family_conflict' => 'มีความขัดแย้ง/ทะเลาะกันในครอบครัว', 'no_caretaker' => 'ไม่มีผู้ดูแล',
            'family_violence' => 'มีความขัดแย้งและมีการใช้ความรุนแรงในครอบครัว', 'abused' => 'ถูกทารุณ/ทำร้ายจากบุคคลในครอบครัว/เพื่อนบ้าน',
            'sexual_abuse' => 'ถูกล่วงละเมิดทางเพศ', 'gambling' => 'เล่นการพนัน',
        ],
        'duties' => ['housework' => 'ช่วยงานบ้าน', 'trading' => 'ช่วยค้าขายเล็ก ๆ น้อย ๆ', 'farm' => 'ช่วยงานในนาไร่', 'caregiving' => 'ช่วยดูแลคนเจ็บป่วย/พิการ', 'nearby_work' => 'ทำงานแถวบ้าน', 'other' => 'อื่น ๆ'],
        'hobbies' => [
            'tv_music' => 'ดูทีวี / ฟังเพลง', 'reading' => 'อ่านหนังสือ', 'racing' => 'แว้น / สก๊อย', 'park' => 'ไปสวนสาธารณะ', 'mall' => 'ไปเที่ยวห้าง / ดูหนัง',
            'friends' => 'ไปหาเพื่อน', 'games' => 'เล่นเกม คอม / มือถือ', 'snooker' => 'ไปร้านสนุกเกอร์', 'other' => 'อื่น ๆ',
        ],
        'substance' => [
            'friends_use' => 'คบเพื่อนในกลุ่มที่ใช้สารเสพติด', 'family_involved' => 'สมาชิกในครอบครัวข้องเกี่ยวกับยาเสพติด', 'environment' => 'อยู่ในสภาพแวดล้อมที่ใช้สารเสพติด',
            'involved' => 'ปัจจุบันเกี่ยวข้องกับสารเสพติด', 'addicted' => 'เป็นผู้ติดบุหรี่ สุรา หรือการใช้สารเสพติดอื่น ๆ',
        ],
        'violence' => ['fights' => 'มีการทะเลาะวิวาท', 'aggressive' => 'ก้าวร้าว เกเร', 'frequent_fights' => 'ทะเลาะวิวาทเป็นประจำ', 'hurts_others' => 'ทำร้ายร่างกายผู้อื่น', 'self_harm' => 'ทำร้ายร่างกายตนเอง'],
        'sexual' => [
            'service_group' => 'อยู่ในกลุ่มขายบริการ', 'media_long' => 'ใช้เครื่องมือสื่อสารที่เกี่ยวข้องกับด้านเพศเป็นเวลานานและบ่อยครั้ง', 'pregnant' => 'ตั้งครรภ์',
            'sells_service' => 'ขายบริการทางเพศ', 'obsessed_media' => 'หมกมุ่นในการใช้เครื่องมือสื่อสารที่เกี่ยวข้องทางเพศ', 'promiscuous' => 'มีการมั่วสุมทางเพศ',
        ],
        'game' => [
            'over_1h' => 'เล่นเกมเกินวันละ 1 ชั่วโมง', 'no_imagination' => 'ขาดจินตนาการและความคิดสร้างสรรค์', 'isolated' => 'เก็บตัว แยกตัวจากกลุ่มเพื่อน',
            'unusual_spending' => 'ใช้จ่ายเงินผิดปกติ', 'gamer_friends' => 'อยู่ในกลุ่มเพื่อนเล่นเกม', 'shop_nearby' => 'ร้านเกมอยู่ใกล้บ้านหรือโรงเรียน',
            'over_2h' => 'ใช้เวลาเล่นเกมเกิน 2 ชั่วโมง', 'obsessed' => 'หมกมุ่น จริงจังในการเล่นเกม', 'steals' => 'ใช้เงินสิ้นเปลือง โกหก ลักขโมยเงินเพื่อเล่นเกม', 'other' => 'อื่น ๆ',
        ],
        'devices' => [
            'phone_in_class' => 'เคยใช้โทรศัพท์มือถือในระหว่างการเรียน', 'social_1h' => 'เข้าใช้ LINE, Facebook, Twitter หรือ chat (เกินวันละ 1 ชั่วโมง)',
            'phone_often' => 'ใช้โทรศัพท์มือถือในระหว่างเรียน 2–3 ครั้ง/วัน', 'social_2h' => 'เข้าใช้ LINE, Facebook, Twitter หรือ chat (เกินวันละ 2 ชั่วโมง)',
        ],
    ];

    /** ตารางข้อ 3: สมาชิกในครัวเรือน สูงสุด 10 คน · รายได้ 5 ประเภท */
    public const MEMBER_ROWS = 10;

    public const INCOME = [
        'salary' => 'ค่าจ้าง เงินเดือน',
        'farm' => 'ประกอบอาชีพทางการเกษตร (หลังหักค่าใช้จ่าย)',
        'business' => 'ธุรกิจส่วนตัว (หลังหักค่าใช้จ่าย)',
        'welfare' => 'สวัสดิการจากรัฐ/เอกชน (บำนาญ เบี้ยผู้สูงอายุ อุดหนุนเด็กแรกเกิด อุดหนุนคนพิการ อื่น ๆ)',
        'other' => 'รายได้จากแหล่งอื่น (เงินโอน ค่าเช่า ดอกเบี้ย อื่น ๆ)',
    ];

    /** ตารางข้อ 5.2 */
    public const RELATIVES = ['father' => 'บิดา', 'mother' => 'มารดา', 'brother' => 'พี่ชาย/น้องชาย', 'sister' => 'พี่สาว/น้องสาว', 'grandparent' => 'ปู่/ย่า/ตา/ยาย', 'relative' => 'ญาติ', 'other' => 'อื่น ๆ'];

    public const CLOSENESS = ['close' => 'สนิทสนม', 'neutral' => 'เฉย ๆ', 'distant' => 'ห่างเหิน', 'conflict' => 'ขัดแย้ง', 'none' => 'ไม่มี'];

    /** เก็บเฉพาะช่องที่นิยามไว้ และแปลงชนิดข้อมูลให้ถูก (ค่าที่ไม่รู้จักถูกทิ้ง) */
    public static function normalize(array $in): array
    {
        $out = [];
        foreach (self::TEXT as $key => $max) {
            $v = trim((string) ($in[$key] ?? ''));
            if ($v !== '') {
                $out[$key] = mb_substr($v, 0, $max);
            }
        }
        foreach (self::NUMBER as $key) {
            if (is_numeric($in[$key] ?? null) && $in[$key] >= 0) {
                $out[$key] = $in[$key] + 0;
            }
        }
        foreach (self::BOOL as $key) {
            if (! empty($in[$key])) {
                $out[$key] = true;
            }
        }
        foreach (self::SINGLE as $key => $options) {
            if (isset($in[$key]) && is_string($in[$key]) && isset($options[$in[$key]])) {
                $out[$key] = $in[$key];
            }
        }
        foreach (self::MULTI as $key => $options) {
            $picked = array_values(array_intersect(array_keys($options), (array) ($in[$key] ?? [])));
            if ($picked) {
                $out[$key] = $picked;
            }
        }
        foreach (array_keys(self::RELATIVES) as $rel) {
            $v = $in['closeness'][$rel] ?? null;
            if (is_string($v) && isset(self::CLOSENESS[$v])) {
                $out['closeness'][$rel] = $v;
            }
        }

        $members = [];
        foreach (array_slice(array_values((array) ($in['members'] ?? [])), 0, self::MEMBER_ROWS) as $row) {
            $member = ['relation' => mb_substr(trim((string) ($row['relation'] ?? '')), 0, 60)];
            $member['age'] = is_numeric($row['age'] ?? null) ? (int) $row['age'] : null;
            $member['disabled'] = ! empty($row['disabled']);
            $total = 0.0;
            foreach (array_keys(self::INCOME) as $type) {
                $member[$type] = is_numeric($row[$type] ?? null) && $row[$type] >= 0 ? (float) $row[$type] : null;
                $total += (float) $member[$type];
            }
            if ($member['relation'] === '' && $member['age'] === null && $total <= 0 && ! $member['disabled']) {
                continue; // แถวว่าง
            }
            $members[] = $member + ['total' => round($total, 2)];
        }
        if ($members) {
            $out['members'] = $members;
            $out['household_income'] = round(array_sum(array_column($members, 'total')), 2);
            $count = (int) ($out['member_count'] ?? count($members));
            $out['household_income_per_head'] = $count > 0 ? round($out['household_income'] / $count, 2) : null;
        }

        return $out;
    }

    /** เกณฑ์คัดกรองนักเรียนยากจน: รายได้ครัวเรือนเฉลี่ยต่อคนไม่เกินนี้ (บาท/เดือน) */
    public const POOR_INCOME_PER_HEAD = 3000;

    /**
     * สรุป "ด้านที่พบความเสี่ยง" จากคำตอบ ใช้แสดงในตารางสรุปรายห้องและหน้ากรณีดูแลช่วยเหลือ
     *
     * @return list<string> key ของ HomeVisit::RISKS
     */
    public static function risks(array $form): array
    {
        $has = fn (string $key, string ...$values) => (bool) array_intersect($values ?: array_keys(self::MULTI[$key]), $form[$key] ?? []);
        $perHead = $form['household_income_per_head'] ?? $form['income_per_head'] ?? null;

        return array_keys(array_filter([
            'economic' => ($perHead !== null && $perHead <= self::POOR_INCOME_PER_HEAD) || $has('dependents') || $has('house_condition') || $has('farmland') || $has('help_wanted', 'economic'),
            'family' => $has('safety', 'parents_separated', 'family_illness', 'family_gambling', 'family_conflict', 'no_caretaker', 'family_violence') || ! empty($form['no_guardian'])
                || (bool) array_intersect(['distant', 'conflict'], array_values($form['closeness'] ?? [])),
            'health' => $has('health'),
            'safety' => $has('safety', 'slum', 'abused', 'sexual_abuse', 'gambling', 'family_violence') || $has('violence') || $has('sexual'),
            'substance' => $has('substance') || $has('safety', 'family_drugs'),
            'travel' => ($form['distance_km'] ?? 0) >= 10 || (($form['travel_hours'] ?? 0) * 60 + ($form['travel_minutes'] ?? 0)) >= 60,
        ]));
    }
}
