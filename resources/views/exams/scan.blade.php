<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#151b24">
<title>สแกน · {{ $exam->title }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="{{ asset('assets/scangrade/scanner.css') }}?v={{ filemtime(public_path('assets/scangrade/scanner.css')) }}">
<style>:root{ {!! \App\Support\Theme::css() !!} }</style>
</head>
<body>

<div id="app" data-roster-url="{{ route('exams.roster', $exam) }}" data-submit-url="{{ route('exams.submit', $exam) }}"
     data-results-url="{{ route('exams.results', $exam) }}" data-exam-id="{{ $exam->id }}">

{{-- หน้าเริ่ม --}}
<section class="screen" id="scrStart">
    <div class="topline">
        <a class="iconbtn" href="{{ route('exams.show', $exam) }}" aria-label="กลับ"><i class="bi bi-chevron-left"></i></a>
        <div class="grow">
            <h1>{{ $exam->title }}</h1>
            <div class="sub">{{ $exam->subject->name }} · {{ $exam->n_items }} ข้อ · {{ count($roster['students']) }} คน</div>
        </div>
    </div>

    @unless ($roster['exam']['key_ready'])
        <div class="note warn"><i class="bi bi-exclamation-triangle"></i> ยังใส่เฉลยไม่ครบ — สแกนได้ตามปกติ ระบบคำนวณคะแนนใหม่ให้เองเมื่อบันทึกเฉลย</div>
    @endunless
    <div class="note bad hidden" id="noCamera"><i class="bi bi-camera-video-off"></i> เบราว์เซอร์นี้เปิดกล้องไม่ได้ (ต้องเปิดผ่าน https) — ใช้ "สแกนจากรูป" แทนได้</div>

    <div class="stats" id="stats"></div>

    <button class="btn btn-primary btn-block big" id="btnCamera"><i class="bi bi-camera"></i> เปิดกล้องสแกน</button>
    <label class="btn btn-block big" id="btnPhotos"><i class="bi bi-images"></i> สแกนจากรูป <small>(เลือกได้หลายรูป / ไฟล์จากเครื่องสแกนเอกสาร)</small>
        <input type="file" id="photoInput" accept="image/*" multiple hidden>
    </label>

    <div class="howto">
        <b>วิธีสแกน</b>
        <ol>
            <li>วางกระดาษบนพื้นเรียบ ให้เห็น <b>สี่เหลี่ยมดำครบ 4 มุม</b></li>
            <li>กรอบเป็นสีเขียว → ถือนิ่ง ระบบถ่ายเอง (หรือกดปุ่มกลม)</li>
            <li>แผ่นปกติบันทึกเองใน 1.6 วินาที วางแผ่นถัดไปได้เลย · แผ่นที่ต้องดูจะหยุดรอ</li>
            <li>ไม่มีเน็ตก็สแกนได้ — ผลเก็บในเครื่องแล้วส่งเองเมื่อมีเน็ต</li>
        </ol>
    </div>

    <label for="sens" class="lbl">ความไวในการอ่าน</label>
    <select id="sens" class="select">
        <option value="0.30">ปกติ (ดินสอ 2B)</option>
        <option value="0.24">ไวขึ้น — ดินสอจาง/แสงน้อย</option>
        <option value="0.36">ไวน้อยลง — มีรอยลบเยอะ</option>
    </select>

    <div class="section-title">รายการที่สแกนในเครื่องนี้ <button class="linkbtn" id="btnSync">ส่งตอนนี้</button></div>
    <div class="list" id="queue"></div>
    <a class="btn btn-block" href="{{ route('exams.results', $exam) }}"><i class="bi bi-list-check"></i> ไปหน้าผลตรวจ / ตรวจทาน</a>
</section>

{{-- กล้อง --}}
<section class="screen hidden" id="scrScan">
    <video id="video" playsinline muted autoplay></video>
    <canvas id="overlay"></canvas>
    <div class="flash" id="flash"></div>
    <div class="scan-top">
        <button class="darkbtn" id="scBack" aria-label="กลับ"><i class="bi bi-chevron-left"></i></button>
        <div class="grow"><b>{{ $exam->title }}</b><small id="scSub"></small></div>
        <button class="darkbtn hidden" id="scTorch" aria-label="ไฟฉาย"><i class="bi bi-lightning-charge"></i></button>
    </div>
    <div class="hint" id="scHint">วางกระดาษให้เห็นสี่เหลี่ยมดำครบ 4 มุม</div>
    <div class="scan-bottom">
        <div class="counter"><b id="scCount">0</b>แผ่นในรอบนี้</div>
        <button class="shutter" id="scShutter" aria-label="ถ่ายเอง"></button>
        <div class="counter right"><b id="scPending">0</b>รอส่ง</div>
    </div>
    <div class="result hidden" id="result"></div>
</section>

{{-- ผลจากรูป --}}
<section class="screen hidden" id="scrPhotos">
    <div class="topline">
        <button class="iconbtn" id="phBack" aria-label="กลับ"><i class="bi bi-chevron-left"></i></button>
        <div class="grow"><h1>สแกนจากรูป</h1><div class="sub" id="phSub"></div></div>
    </div>
    <div class="list" id="phList"></div>
    <button class="btn btn-primary btn-block big" id="phSave" disabled><i class="bi bi-save"></i> บันทึก</button>
</section>

<canvas id="work" hidden></canvas>
<canvas id="small" hidden></canvas>
</div>

<script id="rosterData" type="application/json">@json($roster)</script>
@include('exams._scripts', ['files' => ['common.js', 'sheet-layout.js', 'omr.js', 'scanner.js']])
</body>
</html>
