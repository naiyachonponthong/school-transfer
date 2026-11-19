#!/usr/bin/env python3
"""สร้างไฟล์ zip สำหรับลูกค้า (มี vendor พร้อมใช้ ไม่ต้องรัน composer)
usage: make_release.py SRC OUT_DIR [--keep-stage]
  SRC        โฟลเดอร์โปรเจ็ค Laravel (รัน composer install --no-dev --optimize-autoloader มาก่อน)
  OUT_DIR    ที่วางไฟล์ zip (อยู่ในหรือนอกโปรเจ็คก็ได้)
  --keep-stage  เก็บโฟลเดอร์ stage ที่ใช้ประกอบ zip ไว้ดู
ใช้ได้ทั้ง Windows / Linux / macOS (Windows ใช้ python หรือ py แทน python3)
หลังสร้าง zip สคริปต์ตรวจเนื้อในเอง ถ้ามีไฟล์ต้องห้ามหรือขาดไฟล์จำเป็นจะเปลี่ยนชื่อเป็น *.rejected.zip และ exit 1
"""
import io, json, os, re, shutil, subprocess, sys, tarfile, zipfile

for _s in (sys.stdout, sys.stderr):
    try: _s.reconfigure(encoding='utf-8')  # Windows: กันข้อความไทยพังตอนแสดงผล
    except Exception: pass


def die(msg):
    sys.exit('make_release: ' + msg)


args = [x for x in sys.argv[1:] if not x.startswith('--')]
KEEP_STAGE = '--keep-stage' in sys.argv
if len(args) != 2:
    die('usage: make_release.py SRC OUT_DIR [--keep-stage]')
SRC = os.path.abspath(args[0])
OUT = os.path.abspath(args[1])
SLUG = 'school'
CONFIG = os.path.join(SRC, 'config', SLUG + '.php')
if not os.path.isfile(os.path.join(SRC, 'artisan')):
    die('SRC ต้องเป็นโฟลเดอร์โปรเจ็ค Laravel (ไม่พบ artisan): ' + SRC)
if not os.path.isfile(CONFIG):
    die('ไม่พบ config/' + SLUG + '.php (รวม config/app-config.snippet.php เข้าไฟล์นี้ก่อน)')
if not os.path.isfile(os.path.join(SRC, 'vendor', 'autoload.php')):
    die('ไม่พบ vendor/autoload.php รัน composer install --no-dev --optimize-autoloader ก่อน')
if os.path.normcase(OUT) == os.path.normcase(SRC):
    die('OUT_DIR ต้องไม่ใช่โฟลเดอร์เดียวกับ SRC')
m = re.search(r"'version'\s*=>\s*'([^']+)'", open(CONFIG, encoding='utf-8').read())
if not m:
    die("ไม่พบ 'version' => '...' ใน config/" + SLUG + '.php')
ver = m.group(1)
STAGE = os.path.join(OUT, 'stage')
REL = os.path.join(STAGE, SLUG)


def posix(path, base):
    return os.path.relpath(path, base).replace(os.sep, '/')  # Windows: เทียบ path ด้วย / เสมอ


# OUT_DIR อยู่ในโปรเจ็ค: ต้องไม่คัดลอกตัวเองลง stage
try:
    OUT_REL = posix(OUT, SRC) if os.path.commonpath([os.path.normcase(SRC), os.path.normcase(OUT)]) == os.path.normcase(SRC) else None
except ValueError:  # Windows: คนละไดรฟ์ (เช่นโปรเจ็คอยู่ D: แต่ OUT_DIR อยู่ C:) แปลว่าไม่ได้อยู่ในโปรเจ็คแน่นอน
    OUT_REL = None

EXCLUDE_TOP = {'.env', '.git', 'node_modules', '.runtime', 'start-local.bat', 'LOCAL-TH.md', 'composer.local.json',
               'composer.local.lock', 'CHANGELOG.md', '.phpunit.result.cache', 'tests', '.github', 'vendor', 'storage',
               'phpunit.xml', '.styleci.yml', 'README.md', '.npmrc', 'vite.config.js', 'package.json', 'package-lock.json',
               '.installer-backup', 'make_release.py', '.idea', '.vscode', '.claude', '.fleet', '.editorconfig',
               'docs'}  # docs/ = แผนพัฒนาภายใน ไม่ส่งลูกค้า (คู่มือลูกค้าคือ INSTALL-TH.md)
EXCLUDE_PATH = {'public/storage', 'public/build', 'public/hot'}
# ไฟล์สำรอง / ไฟล์ snippet ที่ยังไม่ได้ลบ / ขยะของระบบปฏิบัติการ ไม่ส่งให้ลูกค้า
EXCLUDE_SUFFIX = ('.bak', '.orig', '.rej', '.snippet.php', '.swp', '~')
EXCLUDE_NAME = {'.DS_Store', 'Thumbs.db', 'desktop.ini'}


def ignore(dirpath, names):
    rel = posix(dirpath, SRC)
    out = set()
    for n in names:
        p = n if rel == '.' else rel + '/' + n
        if rel == '.' and n in EXCLUDE_TOP:
            out.add(n)
        elif p in EXCLUDE_PATH or p == OUT_REL:
            out.add(n)
        elif rel == 'bootstrap/cache' and n.endswith('.php'):
            out.add(n)
        elif rel == 'database' and n.endswith(('.sqlite', '.sqlite-journal', '.sqlite-wal', '.sqlite-shm')):
            out.add(n)
        elif n in EXCLUDE_NAME or n.endswith(EXCLUDE_SUFFIX):
            out.add(n)
    return out


warnings = []
shutil.rmtree(STAGE, ignore_errors=True)
os.makedirs(STAGE)
shutil.copytree(SRC, REL, symlinks=True, ignore=ignore)

# storage: โครงโฟลเดอร์เปล่า
for d in ['storage/app/public', 'storage/app/private', 'storage/framework/cache/data', 'storage/framework/sessions',
          'storage/framework/views', 'storage/logs', 'storage/fonts']:
    os.makedirs(os.path.join(REL, d), exist_ok=True)
    with open(os.path.join(REL, d, '.gitignore'), 'wb') as fh:
        fh.write(b'*\n!.gitignore\n')
os.makedirs(os.path.join(REL, 'bootstrap', 'cache'), exist_ok=True)

# vendor: แตก symlink (path repo) เป็นไฟล์จริงแบบเดียวกับ composer dist
# package ที่เป็น git repo ของตัวเอง (path repo / --prefer-source) ใช้ git archive เพื่อเคารพ export-ignore
# ต้องเช็กว่ามี .git ใน package นั้นเอง: ถ้าใช้ git rev-parse โปรเจ็คที่เป็น git repo จะทำให้ทุก package
# ถูกมองเป็น repo แล้ว git archive คืน tar ว่าง (vendor ถูก ignore) ได้ vendor เปล่าทั้งชุด
V = os.path.join(SRC, 'vendor')
VR = os.path.join(REL, 'vendor')
os.makedirs(VR)
for top in sorted(os.listdir(V)):
    src = os.path.join(V, top)
    dst = os.path.join(VR, top)
    if os.path.isfile(src):
        shutil.copy2(src, dst)
        continue
    if top in ('composer', 'bin'):
        shutil.copytree(src, dst, symlinks=False)
        continue
    os.makedirs(dst)
    for pkg in sorted(os.listdir(src)):
        s = os.path.join(src, pkg)
        d = os.path.join(dst, pkg)
        if os.path.isfile(s):
            shutil.copy2(s, d)
            continue
        real = os.path.realpath(s)
        if os.path.exists(os.path.join(real, '.git')):
            os.makedirs(d)
            tar = subprocess.run(['git', '-C', real, 'archive', '--format=tar', 'HEAD'], capture_output=True, check=True).stdout
            with tarfile.open(fileobj=io.BytesIO(tar)) as t:
                t.extractall(d, filter='tar')
            if not os.listdir(d):
                die('git archive ของ vendor/' + top + '/' + pkg + ' ว่างเปล่า (ยังไม่ได้ commit ?)')
        else:
            shutil.copytree(real, d, ignore=shutil.ignore_patterns('.git'))

try:
    with open(os.path.join(V, 'composer', 'installed.json'), encoding='utf-8') as fh:
        if json.load(fh).get('dev') is True:
            warnings.append('vendor มี package สำหรับพัฒนา (phpunit ฯลฯ) รัน composer install --no-dev --optimize-autoloader แล้วสร้างใหม่')
except (OSError, ValueError):
    pass

# bootstrap/cache สร้างใหม่ให้ตรงกับ vendor ชุดนี้
try:
    r = subprocess.run(['php', 'artisan', 'package:discover'], cwd=REL, capture_output=True)
    tail = (r.stdout + r.stderr).decode('utf-8', 'replace').strip()[-400:]
    if r.returncode != 0:
        warnings.append('php artisan package:discover ไม่สำเร็จ (exit %d) Laravel จะสร้าง bootstrap/cache เองตอนเปิดเว็บครั้งแรก: %s' % (r.returncode, tail))
    elif tail:
        print(tail)
except FileNotFoundError:
    warnings.append('ไม่พบคำสั่ง php ใน PATH ข้าม package:discover (Laravel จะสร้าง bootstrap/cache เองตอนเปิดเว็บครั้งแรก)')
for f in ('storage/installed', 'storage/installing', '.env', 'bootstrap/cache/config.php', 'bootstrap/cache/events.php'):
    p = os.path.join(REL, f)
    if os.path.exists(p):
        os.remove(p)
for f in os.listdir(os.path.join(REL, 'bootstrap', 'cache')):
    if f.startswith('routes') and f.endswith('.php'):
        os.remove(os.path.join(REL, 'bootstrap', 'cache', f))
# log ที่อาจเกิดตอน package:discover
for f in os.listdir(os.path.join(REL, 'storage/logs')):
    if f.endswith('.log'):
        os.remove(os.path.join(REL, 'storage/logs', f))

# รายการไฟล์ของชุดติดตั้ง + ลายเซ็นจากร้าน (ระบบลูกค้าใช้ตรวจว่าไฟล์ถูกแก้หรือไม่)
# เซ็นไม่ผ่าน = ไม่ใส่รายการไฟล์ และไม่ออก zip (ใส่ --no-sign เพื่อออก zip ทดสอบที่ไม่มีลายเซ็น)
STORE = os.environ.get('DIGITALSTORE_PATH', r'D:\code\digitalstore')
manifest = os.path.join(REL, 'bootstrap', 'integrity.json')
signature = os.path.join(REL, 'bootstrap', 'integrity.sig')
if '--no-sign' in sys.argv:
    warnings.append('สร้างโดยไม่เซ็นรายการไฟล์ (--no-sign) ใช้ทดสอบเท่านั้น ห้ามส่งลูกค้า')
else:
    php = shutil.which('php') or 'php'
    r = subprocess.run([php, 'artisan', 'school:integrity', '--write', '--root=' + REL, '--out=' + manifest], cwd=SRC, capture_output=True)
    if r.returncode != 0 or not os.path.isfile(manifest):
        die('สร้างรายการไฟล์ไม่สำเร็จ: ' + (r.stdout + r.stderr).decode('utf-8', 'replace').strip()[-400:])
    if not os.path.isdir(STORE):
        os.remove(manifest)
        die('ไม่พบโปรเจกต์ร้านที่ ' + STORE + ' (ตั้ง DIGITALSTORE_PATH) จึงเซ็นรายการไฟล์ไม่ได้')
    r = subprocess.run([php, 'artisan', 'license:sign', manifest], cwd=STORE, capture_output=True, text=True, encoding='utf-8', errors='replace')
    sig = (r.stdout or '').strip().splitlines()
    if r.returncode != 0 or not sig:
        os.remove(manifest)
        die('เซ็นรายการไฟล์ไม่สำเร็จ (ร้านต้องมี release key และ PHP ต้องเปิด sodium): ' + (r.stderr or r.stdout or '').strip()[:300])
    with open(signature, 'w', encoding='ascii', newline='\n') as fh:
        fh.write(sig[-1].strip() + '\n')
    with open(manifest, encoding='utf-8') as fh:
        print('เซ็นรายการไฟล์แล้ว', len(json.load(fh)['files']), 'ไฟล์')


# zip: ไฟล์อยู่ชั้นบนสุด แตกใน public_html แล้วใช้ได้ทันที
os.makedirs(OUT, exist_ok=True)
zpath = os.path.join(OUT, f'{SLUG}-v{ver}-install.zip')
for old in (zpath, zpath[:-4] + '.rejected.zip'):
    if os.path.exists(old):
        os.remove(old)
n = 0
with zipfile.ZipFile(zpath, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for root, dirs, files in os.walk(REL):
        dirs.sort()
        rel_root = posix(root, REL)
        if rel_root != '.' and not files and not dirs:
            z.writestr(rel_root + '/', '')
        for f in sorted(files):
            full = os.path.join(root, f)
            zi = zipfile.ZipInfo.from_file(full, posix(full, REL))
            zi.compress_type = zipfile.ZIP_DEFLATED
            with open(full, 'rb') as fh:
                z.writestr(zi, fh.read(), compresslevel=9)
            n += 1

# ---------- ตรวจเนื้อใน zip ก่อนส่งลูกค้า ----------
FORBID = [
    (r'^\.env$', '.env'), (r'^storage/install(ed|ing)$', 'ไฟล์ล็อกตัวติดตั้ง'), (r'\.sqlite(-journal|-wal|-shm)?$', 'ฐานข้อมูล sqlite'),
    (r'\.log$', 'ไฟล์ log'), (r'\.(bak|orig|rej)$', 'ไฟล์สำรอง'), (r'\.snippet\.php$', 'ไฟล์ snippet'),
    (r'^(\.git|\.installer-backup|tests|node_modules|\.runtime)/', 'โฟลเดอร์ของนักพัฒนา'),
    (r'^bootstrap/cache/(config|events|routes[^/]*)\.php$', 'cache ของเครื่องพัฒนา (มีค่าจาก .env)'),
    (r'^public/(storage|build|hot)(/|$)', 'ไฟล์ใน public ที่ต้องไม่ส่ง'), (r'^(start-local\.bat|make_release\.py)$', 'ไฟล์เฉพาะเครื่องนักพัฒนา'),
]
REQUIRE = ['artisan', '.htaccess', 'public/index.php', 'bootstrap/preboot.php', 'bootstrap/app.php', 'config/' + SLUG + '.php',
           'app/Support/Installer.php', 'vendor/autoload.php', 'vendor/laravel/framework/src/Illuminate/Foundation/Application.php',
           'storage/framework/sessions/.gitignore', 'storage/framework/views/.gitignore', 'storage/logs/.gitignore']
if '--no-sign' not in sys.argv:
    REQUIRE += ['bootstrap/integrity.json', 'bootstrap/integrity.sig', 'app/Support/License/Guard.php']
problems = []
with zipfile.ZipFile(zpath) as z:
    names = z.namelist()
    have = set(names)
    for name in names:
        if name.startswith('vendor/'):
            continue
        for pat, why in FORBID:
            if re.search(pat, name):
                problems.append('มี ' + name + ' (' + why + ')')
                break
    for name in REQUIRE:
        if name not in have:
            problems.append('ขาด ' + name)
    if 'public/index.php' in have and b'bootstrap/preboot.php' not in z.read('public/index.php'):
        problems.append('public/index.php ยังไม่เรียก bootstrap/preboot.php (ลูกค้าจะไม่เจอตัวติดตั้ง)')
    if '.env.example' not in have:
        warnings.append('ไม่มี .env.example ใน zip (preboot จะสร้าง .env จากแม่แบบขั้นต่ำแทน)')
    php_need = None
    if 'vendor/composer/platform_check.php' in have:
        pm = re.search(rb'PHP_VERSION_ID >= (\d+)', z.read('vendor/composer/platform_check.php'))
        if pm:
            v = int(pm.group(1))
            php_need = '%d.%d.%d' % (v // 10000, v % 10000 // 100, v % 100)
    else:
        warnings.append('ไม่มี vendor/composer/platform_check.php (preboot จะใช้ PHP ขั้นต่ำค่าตั้งต้นแทนค่าจริงของ vendor)')

if not KEEP_STAGE:
    shutil.rmtree(STAGE, ignore_errors=True)
for w in warnings:
    print('คำเตือน: ' + w)
if problems:
    bad = zpath[:-4] + '.rejected.zip'
    os.replace(zpath, bad)
    print('ไม่ผ่านการตรวจ ห้ามส่งไฟล์นี้ให้ลูกค้า: ' + bad, file=sys.stderr)
    for p in problems:
        print('  - ' + p, file=sys.stderr)
    sys.exit(1)
print('files', n, 'zip', zpath, round(os.path.getsize(zpath) / 1048576, 1), 'MB',
      '| PHP ขั้นต่ำของ vendor: ' + (php_need or 'ไม่ทราบ'))
