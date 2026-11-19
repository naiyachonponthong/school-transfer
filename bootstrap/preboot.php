<?php

/*
|--------------------------------------------------------------------------
| ระบบบริหารโรงเรียน : ตรวจเครื่องก่อนบูต Laravel (ตัวติดตั้งแบบ WordPress)
|--------------------------------------------------------------------------
| ไฟล์นี้ทำงานก่อน vendor/autoload.php จึงเขียนด้วย PHP ธรรมดา
| (ไม่ใช้ syntax ใหม่) เพื่อให้แสดงข้อความภาษาไทยได้แม้ PHP บนโฮสต์เก่ากว่าที่ระบบต้องการ
|
| 1. PHP ต่ำกว่าที่ vendor ต้องการ / ไม่มี vendor / ขาด extension หลัก  > หน้าแจ้งวิธีแก้
| 2. ยังไม่มี .env และยังไม่เคยติดตั้ง > สร้าง .env พร้อม APP_KEY ให้เอง แล้วไปหน้า /install
| 3. ติดตั้งไว้แล้วแต่ .env หาย > ไม่สร้างใหม่ (จะทำให้รหัสลับเปลี่ยน) แจ้งให้กู้คืนไฟล์
| 4. เปิดผ่าน .htaccess ชั้นบน (public_html หรือโฟลเดอร์ย่อย) > ปรับ SCRIPT_NAME ให้ Laravel หา path ถูก
*/

if (! function_exists('school_preboot_fail')) {
    function school_preboot_fail($title, $lines)
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            fwrite(STDERR, "ระบบบริหารโรงเรียน: $title\n - ".implode("\n - ", array_map('strip_tags', $lines))."\n");
            exit(1);
        }
        if (! headers_sent()) {
            header('HTTP/1.1 503 Service Unavailable');
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store');
        }
        $items = '';
        foreach ($lines as $l) {
            $items .= '<li>'.$l.'</li>';
        }
        echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>ติดตั้ง ระบบบริหารโรงเรียน</title><style>'
            .'body{margin:0;font-family:Sarabun,"Noto Sans Thai",Tahoma,sans-serif;background:#f4f5f7;color:#1f2937;display:grid;place-items:center;min-height:100vh;padding:16px;box-sizing:border-box}'
            .'.c{background:#fff;border-radius:20px;box-shadow:0 10px 30px rgba(16,24,40,.10);padding:28px 30px;max-width:620px;width:100%}'
            .'.i{width:52px;height:52px;border-radius:16px;background:#fef3ed;color:#F26522;display:grid;place-items:center;font-size:28px;font-weight:700;margin-bottom:12px}'
            .'h1{font-size:1.35rem;margin:0 0 .6rem}li{margin:.45rem 0;line-height:1.6}code{background:#f3f4f6;border-radius:6px;padding:.1rem .35rem;font-size:.9em}'
            .'.f{margin-top:18px;color:#6b7280;font-size:.85rem}</style></head><body><div class="c"><div class="i">!</div>'
            .'<h1>'.$title.'</h1><ul>'.$items.'</ul><div class="f">แก้แล้วกดรีเฟรชหน้านี้</div></div></body></html>';
        exit;
    }
}

$schoolBase = dirname(__DIR__);

/* ---------- 1) PHP / vendor / extension หลัก ---------- */
$schoolNeed = 80300;
$schoolCheck = $schoolBase.'/vendor/composer/platform_check.php';
if (is_file($schoolCheck) && preg_match('/PHP_VERSION_ID >= (\d+)/', (string) file_get_contents($schoolCheck), $schoolM)) {
    $schoolNeed = (int) $schoolM[1];
}
if (PHP_VERSION_ID < $schoolNeed) {
    $schoolNeedText = intval($schoolNeed / 10000).'.'.intval(($schoolNeed % 10000) / 100).'.'.($schoolNeed % 100);
    school_preboot_fail('เวอร์ชัน PHP บนโฮสต์ต่ำเกินไป', array(
        'ระบบต้องการ PHP <b>'.$schoolNeedText.'</b> ขึ้นไป ตอนนี้โฮสต์ใช้ <b>'.PHP_VERSION.'</b>',
        'cPanel: เมนู <b>Select PHP Version</b> หรือ <b>MultiPHP Manager</b> แล้วเลือกเวอร์ชันใหม่ให้โดเมนนี้',
        'DirectAdmin: เมนู <b>PHP Version Selector</b>',
        'หาไม่เจอ ให้แจ้งผู้ให้บริการโฮสต์เปลี่ยน PHP ให้',
    ));
}
if (! is_file($schoolBase.'/vendor/autoload.php')) {
    school_preboot_fail('อัปโหลดไฟล์ไม่ครบ', array(
        'ไม่พบโฟลเดอร์ <code>vendor</code> ซึ่งอยู่ในไฟล์ zip สำหรับติดตั้ง',
        'อัปโหลด zip ใหม่แล้วแตกไฟล์อีกครั้ง (อย่าแตกไฟล์บนเครื่องแล้วอัปทีละไฟล์ เพราะมักขาดหาย)',
    ));
}
$schoolMissing = array();
foreach (array('mbstring', 'openssl', 'pdo', 'tokenizer', 'ctype', 'json', 'fileinfo') as $schoolExt) {
    if (! extension_loaded($schoolExt)) {
        $schoolMissing[] = $schoolExt;
    }
}
if ($schoolMissing) {
    school_preboot_fail('PHP บนโฮสต์ยังขาด extension ที่จำเป็น', array(
        'ต้องเปิด: <code>'.implode('</code> <code>', $schoolMissing).'</code>',
        'cPanel: <b>Select PHP Version</b> แท็บ Extensions แล้วติ๊กให้ครบ',
    ));
}

/* ---------- 2) โฟลเดอร์ที่ต้องเขียนได้ (zip บางตัวไม่เก็บโฟลเดอร์ว่าง) ---------- */
foreach (array('storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'storage/fonts', 'bootstrap/cache') as $schoolDir) {
    $schoolDir = $schoolBase.'/'.$schoolDir;
    if (! is_dir($schoolDir)) {
        @mkdir($schoolDir, 0775, true);
    }
}
foreach (array('storage', 'storage/framework/sessions', 'storage/framework/views', 'bootstrap/cache') as $schoolDir) {
    if (! is_writable($schoolBase.'/'.$schoolDir)) {
        school_preboot_fail('ระบบเขียนไฟล์ลงโฟลเดอร์ไม่ได้', array(
            'โฟลเดอร์ <code>'.$schoolDir.'</code> ต้องเขียนได้',
            'ใน File Manager คลิกขวาที่โฟลเดอร์ <code>storage</code> และ <code>bootstrap/cache</code> > Change Permissions > <b>755</b> (ถ้ายังไม่ได้ใช้ <b>775</b>) ติ๊ก Recurse into subdirectories',
        ));
    }
}

/* ---------- 3) .env ---------- */
$schoolEnv = $schoolBase.'/.env';
$schoolLock = $schoolBase.'/storage/installed';
if (! is_file($schoolEnv)) {
    if (is_file($schoolLock)) {
        school_preboot_fail('ไม่พบไฟล์ตั้งค่า .env', array(
            'ระบบนี้ติดตั้งไว้แล้ว แต่ไฟล์ <code>.env</code> หายไป (มักเกิดจากอัปโหลดไฟล์ชุดใหม่ทับแล้วลบของเดิม)',
            'กู้คืนไฟล์ <code>.env</code> จากไฟล์สำรองของโฮสต์ หรือจากเครื่องที่เคยดาวน์โหลดไว้ แล้ววางไว้ที่โฟลเดอร์บนสุดของระบบ',
            'ห้ามติดตั้งใหม่ทับ เพราะรหัสลับในไฟล์นี้ใช้ถอดข้อมูลที่บันทึกไว้',
        ));
    }
    if (! is_writable($schoolBase)) {
        school_preboot_fail('สร้างไฟล์ตั้งค่า .env ไม่ได้', array(
            'โฟลเดอร์ <code>'.htmlspecialchars($schoolBase).'</code> ต้องเขียนได้ (Permissions 755)',
            'หรือคัดลอก <code>.env.example</code> เป็น <code>.env</code> เองใน File Manager แล้วรีเฟรช',
        ));
    }
    $schoolTpl = is_file($schoolBase.'/.env.example') ? (string) file_get_contents($schoolBase.'/.env.example') : "APP_NAME=\"ระบบบริหารโรงเรียน\"\nAPP_KEY=\n"; // ใส่ quote: ชื่อที่มีเว้นวรรคทำให้ .env อ่านไม่ได้
    $schoolScheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
    $schoolPath = isset($_SERVER['SCRIPT_NAME']) ? rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') : '';
    if (substr($schoolPath, -7) === '/public') {
        $schoolPath = substr($schoolPath, 0, -7);
    }
    $schoolSet = array(
        'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
        'APP_URL' => isset($_SERVER['HTTP_HOST']) ? $schoolScheme.'://'.$_SERVER['HTTP_HOST'].$schoolPath : 'http://localhost',
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        // production บังคับ https + คุกกี้ secure: เปิดผ่าน http อยู่ต้องปิดไว้ก่อน ไม่งั้น session หลุดทุกหน้า (ตัวติดตั้งตั้งค่าตามที่อยู่เว็บอีกครั้ง)
        'FORCE_HTTPS' => $schoolScheme === 'https' ? 'true' : 'false',
        // ก่อนติดตั้งยังไม่มีฐานข้อมูล ใช้ไฟล์ไปก่อน ตัวติดตั้งเปลี่ยนกลับเป็น database ตอนจบ
        'SESSION_DRIVER' => 'file',
        'CACHE_STORE' => 'file',
        'QUEUE_CONNECTION' => 'sync',
        // เปิดตัวติดตั้งได้เฉพาะ .env ที่สร้างตรงนี้
        'SCHOOL_INSTALL_PENDING' => 'true',
    );
    foreach ($schoolSet as $schoolK => $schoolV) {
        if (preg_match('/^'.$schoolK.'=.*$/m', $schoolTpl)) {
            $schoolTpl = preg_replace('/^'.$schoolK.'=.*$/m', $schoolK.'='.str_replace('$', '\\$', $schoolV), $schoolTpl);
        } else {
            $schoolTpl = rtrim($schoolTpl)."\n".$schoolK.'='.$schoolV."\n";
        }
    }
    if (@file_put_contents($schoolEnv, $schoolTpl, LOCK_EX) === false) {
        school_preboot_fail('สร้างไฟล์ตั้งค่า .env ไม่ได้', array('ตรวจสิทธิ์การเขียนของโฟลเดอร์ระบบ (755)'));
    }
    // ล้าง config ที่ cache ไว้จากเครื่องอื่น
    @unlink($schoolBase.'/bootstrap/cache/config.php');
}

/* ---------- 4) เปิดผ่าน .htaccess ชั้นบน: /sub/login > SCRIPT_NAME /sub/index.php ---------- */
if (isset($_SERVER['SCRIPT_NAME'], $_SERVER['REQUEST_URI']) && substr($_SERVER['SCRIPT_NAME'], -17) === '/public/index.php') {
    $schoolDirName = substr($_SERVER['SCRIPT_NAME'], 0, -10); // .../public
    if (strpos($_SERVER['REQUEST_URI'], $schoolDirName.'/') !== 0 && $_SERVER['REQUEST_URI'] !== $schoolDirName) {
        $_SERVER['SCRIPT_NAME'] = substr($_SERVER['SCRIPT_NAME'], 0, -17).'/index.php';
        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
    }
}

unset($schoolBase, $schoolNeed, $schoolCheck, $schoolM, $schoolMissing, $schoolExt, $schoolDir, $schoolEnv, $schoolLock, $schoolTpl, $schoolScheme, $schoolPath, $schoolSet, $schoolK, $schoolV, $schoolDirName, $schoolNeedText);
