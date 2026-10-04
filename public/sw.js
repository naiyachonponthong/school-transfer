/* Service worker ของระบบ: รับแจ้งเตือนบนอุปกรณ์ (Web Push) เท่านั้น ไม่เก็บหน้าเว็บไว้ในเครื่อง
   สัญญาณที่ได้รับไม่มีเนื้อหา จึงมาดึงข้อความที่รออยู่จากระบบเอง (ต้องยังเข้าสู่ระบบอยู่) */
const base = self.registration.scope;

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    event.waitUntil((async () => {
        let title = 'ระบบโรงเรียน';
        let messages = [];
        try {
            const res = await fetch(new URL('push/pending', base), { credentials: 'include', headers: { Accept: 'application/json' } });
            if (res.ok && (res.headers.get('content-type') || '').includes('json')) {
                const data = await res.json();
                title = data.title || title;
                messages = data.messages || [];
            }
        } catch (e) { /* ออฟไลน์/หมดเวลาเข้าระบบ: แสดงข้อความทั่วไปแทน */ }

        if (!messages.length) {
            messages = [{ id: 'new', text: 'มีการแจ้งเตือนใหม่ แตะเพื่อเปิดดู', url: new URL('notifications', base).href }];
        }
        const icon = new URL('favicon.ico', base).href;
        await Promise.all(messages.map((m) => self.registration.showNotification(title, {
            body: m.text, tag: 'school-' + m.id, icon: icon, data: { url: m.url },
        })));
    })());
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || base;
    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const w of windows) {
            if (w.url.startsWith(base) && 'focus' in w) {
                await w.focus();
                return w.navigate ? w.navigate(url) : undefined;
            }
        }
        return self.clients.openWindow(url);
    })());
});
