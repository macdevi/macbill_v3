(() => {
    const panel = document.getElementById('dashboard-customer-connections');
    const source = document.getElementById('dc-context');
    if (!panel || !source) return;

    const context = JSON.parse(source.textContent);
    const online = document.getElementById('dc-online');
    const offline = document.getElementById('dc-offline');
    const note = document.getElementById('dc-note');
    const format = new Intl.NumberFormat('id-ID');
    const results = new Map();
    const controllers = new Set();
    let running = false;
    let timer = null;

    for (const router of context.routers) {
        results.set(router.id, {
            online: 0, offline: 0, unknown: router.total,
            available: false, checked_at: null,
        });
    }

    function paint(loading = false) {
        let on = 0;
        let off = 0;
        let unknown = context.unassigned;
        let available = false;
        const dates = [];

        for (const result of results.values()) {
            on += result.online;
            off += result.offline;
            unknown += result.unknown;
            available ||= result.available;

            const time = Date.parse(result.checked_at);
            if (Number.isFinite(time)) dates.push(time);
        }

        const hasPppoe = context.routers.length > 0 || context.unassigned > 0;

        online.textContent = available || !hasPppoe ? format.format(on) : '—';
        offline.textContent = available || !hasPppoe ? format.format(off) : '—';

        const parts = [];

        if (loading) {
            parts.push('Memeriksa koneksi…');
        } else if (dates.length) {
            const clock = new Intl.DateTimeFormat('id-ID', {
                timeZone: 'Asia/Jakarta',
                hour: '2-digit', minute: '2-digit', second: '2-digit',
                hourCycle: 'h23',
            }).format(new Date(Math.min(...dates)));

            parts.push(`Data sesi ${clock} WIB`);
        } else if (hasPppoe) {
            parts.push('Status koneksi belum tersedia');
        } else {
            parts.push('Belum ada pelanggan PPPoE yang dapat diperiksa');
        }

        if (unknown) parts.push(`${format.format(unknown)} belum diketahui`);
        if (context.unsupported) {
            parts.push(`${format.format(context.unsupported)} non-PPPoE belum didukung`);
        }

        note.textContent = parts.join(' · ');
        const detailRows = [];
        for (const result of results.values()) {
            if (!Array.isArray(result.customers)) continue;
            for (const row of result.customers) {
                if (row && ['online', 'offline'].includes(row.state)
                    && Number.isSafeInteger(row.id) && typeof row.name === 'string') {
                    detailRows.push(row);
                }
            }
        }
        panel.dcDetailSnapshot = {rows:detailRows, note:note.textContent, loading};
        panel.dispatchEvent(new CustomEvent('dc:detail-snapshot'));
    }

    async function readRouter(router) {
        const controller = new AbortController();
        controllers.add(controller);
        const timeout = setTimeout(() => controller.abort(), 35000);

        try {
            const url = new URL(panel.dataset.endpoint, window.location.origin);
            url.pathname = url.pathname.replace(/\/0$/, '/' + router.id);

            const response = await fetch(url, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });

            if (!response.ok || response.redirected) throw new Error('unavailable');

            const data = await response.json();
            const counts = ['online', 'offline', 'unknown', 'total'];

            if (
                data.router_id !== router.id
                || typeof data.available !== 'boolean'
                || !counts.every(key => Number.isSafeInteger(data[key]) && data[key] >= 0)
                || data.online + data.offline + data.unknown !== data.total
                || (!data.available && (data.online !== 0 || data.offline !== 0))
            ) {
                throw new Error('invalid');
            }

            results.set(router.id, data);
        } catch (_) {
            if (!document.hidden) {
                results.set(router.id, {
                    online: 0, offline: 0, unknown: router.total,
                    available: false, checked_at: null,
                });
            }
        } finally {
            clearTimeout(timeout);
            controllers.delete(controller);
            if (!document.hidden) paint(true);
        }
    }

    async function refresh() {
        clearTimeout(timer);
        if (running || document.hidden) return;

        running = true;
        paint(true);
        const queue = [...context.routers];

        try {
            async function worker() {
                while (queue.length && !document.hidden) {
                    await readRouter(queue.shift());
                }
            }

            await Promise.all([worker(), worker()]);
        } finally {
            running = false;

            if (!document.hidden) {
                paint(false);

                if (context.routers.length) {
                    timer = setTimeout(refresh, 30000);
                }
            }
        }
    }

    document.addEventListener('visibilitychange', () => {
        clearTimeout(timer);

        if (document.hidden) {
            controllers.forEach(controller => controller.abort());
            note.textContent = 'Pembaruan koneksi dijeda';
        } else {
            refresh();
        }
    });

    window.addEventListener('pagehide', () => {
        clearTimeout(timer);
        controllers.forEach(controller => controller.abort());
    });

    window.addEventListener('pageshow', event => {
        if (event.persisted) refresh();
    });

    refresh();
})();


// Detail mengikuti snapshot yang sama dengan angka card.
(() => {
    const panel = document.getElementById('dashboard-customer-connections');
    const dialog = document.getElementById('dc-detail-dialog');
    if (!panel || !dialog) return;
    const title = document.getElementById('dc-detail-title');
    const info = document.getElementById('dc-detail-info');
    const search = document.getElementById('dc-detail-search');
    const body = document.getElementById('dc-detail-body');
    const pageText = document.getElementById('dc-detail-page');
    const prev = document.getElementById('dc-detail-prev');
    const next = document.getElementById('dc-detail-next');
    const titles = {
        online:'Pelanggan Online', subscribed:'Pelanggan Berlangganan',
        offline:'Pelanggan Offline', isolated:'Pelanggan Isolir',
    };
    const format = new Intl.NumberFormat('id-ID');
    let category = '', page = 1, lastPage = 1;
    let request = null, sequence = 0, debounce = null, opener = null;
    const liveCategory = () => category === 'online' || category === 'offline';

    function message(text) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 3; td.textContent = text; tr.append(td); body.replaceChildren(tr);
    }
    function paintRows(rows, total, pages) {
        lastPage = Math.max(1, pages);
        if (!rows.length) message('Tidak ada pelanggan yang sesuai.');
        else {
            const fragment = document.createDocumentFragment();
            for (const row of rows) {
                const tr = document.createElement('tr');
                const name = document.createElement('td');
                const label = document.createElement('span');
                label.textContent = row.name || '—';
                const code = document.createElement('small');
                code.textContent = row.code || '—'; name.append(label, code);
                const service = document.createElement('td');
                service.textContent = String(row.service_type || '—').toUpperCase();
                const username = document.createElement('td');
                username.textContent = row.pppoe_username || '—';
                tr.append(name, service, username); fragment.append(tr);
            }
            body.replaceChildren(fragment);
        }
        pageText.textContent = `${format.format(total)} pelanggan · Halaman ${page}/${lastPage}`;
        prev.disabled = page <= 1; next.disabled = page >= lastPage;
    }
    function paintLive() {
        const snapshot = panel.dcDetailSnapshot;
        if (!snapshot) {
            info.textContent = 'Menunggu hasil pemeriksaan koneksi.';
            message('Data koneksi belum tersedia.');
            pageText.textContent = ''; prev.disabled = next.disabled = true; return;
        }
        const query = search.value.trim().toLocaleLowerCase('id-ID');
        const rows = snapshot.rows.filter(row => row.state === category)
            .filter(row => !query || [row.name, row.code, row.pppoe_username]
                .some(value => String(value || '').toLocaleLowerCase('id-ID').includes(query)))
            .sort((a, b) => a.name.localeCompare(b.name, 'id') || a.id - b.id);
        lastPage = Math.max(1, Math.ceil(rows.length / 25)); page = Math.min(page, lastPage);
        info.textContent = snapshot.note + ' · Daftar mengikuti snapshot angka card.';
        paintRows(rows.slice((page - 1) * 25, page * 25), rows.length, lastPage);
        if (!rows.length && !query) message(snapshot.loading
            ? 'Pemeriksaan berjalan; belum ada pelanggan terkonfirmasi pada kategori ini.'
            : 'Tidak ada pelanggan terkonfirmasi pada kategori ini. Status belum diketahui tidak dimasukkan.');
    }
    async function loadBilling() {
        request?.abort();
        const current = ++sequence;
        const controller = new AbortController(); request = controller;
        const timeout = setTimeout(() => controller.abort(), 20000);
        body.setAttribute('aria-busy', 'true'); info.textContent = 'Memuat daftar pelanggan…';
        message('Memuat…'); pageText.textContent = ''; prev.disabled = next.disabled = true;
        try {
            const url = new URL(dialog.dataset.endpoint, window.location.origin);
            url.searchParams.set('category', category);
            url.searchParams.set('q', search.value.trim()); url.searchParams.set('page', page);
            const response = await fetch(url, {
                credentials:'same-origin', cache:'no-store',
                headers:{Accept:'application/json'}, signal:controller.signal,
            });
            if (!response.ok || response.redirected) {
                const error = new Error('request'); error.status = response.status; throw error;
            }
            const data = await response.json();
            if (!Array.isArray(data.rows) || !Number.isSafeInteger(data.total)
                || !Number.isSafeInteger(data.page) || !Number.isSafeInteger(data.last_page)) throw new Error('invalid');
            if (current !== sequence || !dialog.open) return;
            if (data.page > data.last_page && data.last_page >= 1) {
                page = data.last_page; loadBilling(); return;
            }
            page = data.page;
            info.textContent = category === 'isolated'
                ? 'Jeda isolir billing terbuka, tanpa tindakan jaringan tertunda. Data saat daftar dimuat.'
                : 'Seluruh pelanggan billing, sesuai definisi card Berlangganan. Data saat daftar dimuat.';
            paintRows(data.rows, data.total, data.last_page);
        } catch (error) {
            if (current !== sequence || !dialog.open) return;
            info.textContent = 'Daftar belum dapat dimuat.';
            message(error.status === 401 || error.status === 403
                ? 'Sesi atau izin akses tidak tersedia. Muat ulang halaman untuk memeriksa akses.'
                : error.status === 429 ? 'Terlalu banyak permintaan. Tunggu sebentar lalu buka kembali.'
                : error.status === 503 ? 'Klasifikasi isolir billing belum tersedia.'
                : 'Gagal mengambil data. Tutup dan buka kembali untuk mencoba lagi.');
            pageText.textContent = '';
        } finally {
            clearTimeout(timeout);
            if (current === sequence) body.removeAttribute('aria-busy');
        }
    }
    function render() { if (liveCategory()) paintLive(); else loadBilling(); }
    function open(card) {
        if (dialog.open || !titles[card.dataset.dcDetail]) return;
        category = card.dataset.dcDetail; page = 1; lastPage = 1;
        search.value = ''; title.textContent = titles[category];
        opener = card; card.focus(); dialog.showModal(); render(); search.focus();
    }
    for (const card of panel.querySelectorAll('[data-dc-detail]')) {
        card.addEventListener('click', () => open(card));
        card.addEventListener('keydown', event => {
            if (event.target === card && (event.key === 'Enter' || event.key === ' ')) {
                event.preventDefault(); open(card);
            }
        });
    }
    document.getElementById('dc-detail-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const box = dialog.getBoundingClientRect();
        if (event.clientX < box.left || event.clientX > box.right
            || event.clientY < box.top || event.clientY > box.bottom) dialog.close();
    });
    dialog.addEventListener('close', () => {
        ++sequence; request?.abort(); clearTimeout(debounce);
        body.removeAttribute('aria-busy'); opener?.focus(); category = '';
    });
    search.addEventListener('input', () => {
        clearTimeout(debounce);
        if (!liveCategory()) { ++sequence; request?.abort(); }
        debounce = setTimeout(() => {
            if (!dialog.open) return; page = 1; render();
        }, 300);
    });
    prev.addEventListener('click', () => { if (page > 1) { --page; render(); } });
    next.addEventListener('click', () => { if (page < lastPage) { ++page; render(); } });
    panel.addEventListener('dc:detail-snapshot', () => {
        if (dialog.open && liveCategory()) paintLive();
    });
})();


// Warna tangan mengikuti keterbacaan teks dasar tema.
(() => {
    const panel = document.getElementById('dashboard-customer-connections');
    if (!panel) return;

    let frame = null;

    function updateHandColor() {
        frame = null;

        const textColor = getComputedStyle(panel).color;
        const channels = textColor.match(/[\d.]+/g);

        let ink = '#ffffff';

        if (textColor.startsWith('rgb') && channels && channels.length >= 3) {
            const [r, g, b] = channels.slice(0, 3).map(Number);
            const brightness = r * 0.299 + g * 0.587 + b * 0.114;

            // Teks dasar terang menandakan tema gelap, dan sebaliknya.
            ink = brightness >= 150 ? '#ffffff' : '#334155';
        }

        panel.style.setProperty('--dc-hand-ink', ink);
    }

    function scheduleUpdate() {
        if (frame === null) {
            frame = requestAnimationFrame(updateHandColor);
        }
    }

    const observer = new MutationObserver(scheduleUpdate);
    const options = {
        attributes:true,
        attributeFilter:[
            'class', 'style', 'data-theme',
            'data-bs-theme', 'data-color-scheme', 'color-scheme'
        ]
    };

    observer.observe(document.documentElement, options);
    if (document.body) observer.observe(document.body, options);

    const preference = window.matchMedia('(prefers-color-scheme: dark)');
    preference.addEventListener('change', scheduleUpdate);

    window.addEventListener('pageshow', scheduleUpdate);
    updateHandColor();
})();
