{{-- สคริปต์ตรวจข้อสอบ (public/assets/scangrade) ตามลำดับที่ส่งมา + เลขเวอร์ชันจากเวลาไฟล์ ให้มือถือโหลดตัวอ่านล่าสุด --}}
@foreach ($files as $f)
    <script src="{{ asset('assets/scangrade/'.$f) }}?v={{ filemtime(public_path('assets/scangrade/'.$f)) }}" defer></script>
@endforeach
