(() => {
    const panel = document.getElementById('router-live-panel');
    if (!panel) return;

    const badge = panel.querySelector('.mikrotik-api-status');
    let busy = false;
    let timer;
    let lastAt = null;

    const set = (key, value) => {
        const el = panel.querySelector(`[data-monitor="${key}"]`);
        if (el) el.textContent = value ?? '—';
    };

    const rate = value => typeof value === 'number' && Number.isFinite(value)
        ? value.toLocaleString('id-ID', { maximumFractionDigits: 2, minimumFractionDigits: 2 })
        : '—';

    const clock = iso => new Date(iso).toLocaleTimeString('id-ID', {
        hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
    });

    function unknown(label) {
        badge.dataset.state = 'unknown';
        set('status', label);
    }

    async function refresh() {
        clearTimeout(timer);
        if (busy || document.hidden) return;
        busy = true;

        const controller = new AbortController();
        const abortTimer = setTimeout(() => controller.abort(), 45000);

        try {
            const response = await fetch(panel.dataset.endpoint, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller.signal
            });

            if (!response.ok) throw new Error('request');
            const data = await response.json();

            if (data.state === 'unconfigured') {
                unknown('Belum ada router');
                set('note', 'Tambahkan konfigurasi router terlebih dahulu');
                return;
            }

            if (data.state === 'pending') {
                unknown('Memeriksa…');
                return;
            }

            set('router_name', data.router_name);
            set('wan', data.wan);

            if (data.data_at) {
                lastAt = data.data_at;
                set('identity', data.identity);
                set('cpu', data.cpu == null ? '—' : `${data.cpu}%`);
                set('uptime', data.uptime);
                set('rx', rate(data.rx));
                set('tx', rate(data.tx));
                panel.dispatchEvent(new CustomEvent('router-monitor:data', { detail: data }));
            }

            if (data.state === 'connected') {
                badge.dataset.state = 'connected';
                set('status', 'Terhubung');
                const trafficNote = data.traffic_state === 'failed'
                    ? ' · Trafik WAN gagal dibaca'
                    : data.traffic_state === 'unconfigured'
                        ? ' · Pilih interface WAN'
                        : '';
                set('note', `Diperbarui ${clock(data.data_at)} · Otomatis 3 dtk${trafficNote}`);
            } else {
                badge.dataset.state = 'disconnected';
                set('status', 'Terputus');
                set('note', lastAt
                    ? `Data lama ${clock(lastAt)} · Mencoba lagi otomatis`
                    : 'Belum ada data · Mencoba lagi otomatis');
            }
        } catch (_) {
            unknown('Pembaruan gagal');
            set('note', lastAt
                ? `Data lama ${clock(lastAt)} · Koneksi ke aplikasi bermasalah`
                : 'Gagal mengambil data dari aplikasi · Mencoba lagi otomatis');
        } finally {
            clearTimeout(abortTimer);
            busy = false;
            if (!document.hidden) timer = setTimeout(refresh, 3000);
        }
    }

    document.addEventListener('visibilitychange', () => {
        clearTimeout(timer);
        if (document.hidden) {
            unknown('Pembaruan dijeda');
        } else {
            refresh();
        }
    });

    refresh();
})();


/* Grafik trafik: skala waktu bergerak */
/* Grafik trafik: area gradasi */
(() => {
    const panel = document.getElementById('router-live-panel');
    if (!panel) return;

    const rxLine = panel.querySelector('#tf-rx-line');
    const txLine = panel.querySelector('#tf-tx-line');
    const rxArea = panel.querySelector('#tf-rx-area');
    const txArea = panel.querySelector('#tf-tx-area');
    if (!rxLine || !txLine || !rxArea || !txArea) return;

    const WINDOW_MS = 60000;
    const HOLD_MS = 25000;
    const FRAME_MS = 100;
    const points = [];
    let lastAt = null;
    let lastDraw = 0;

    panel.addEventListener('router-monitor:data', (event) => {
        const data = event.detail || {};
        if (!data.data_at || data.data_at === lastAt) return;
        if (typeof data.rx !== 'number' || typeof data.tx !== 'number') return;

        const time = Date.parse(data.data_at);
        if (!Number.isFinite(time)) return;

        lastAt = data.data_at;
        points.push({ t: time, rx: data.rx, tx: data.tx });
    });

    const build = (key, list, peak, now) => {
        let d = '';
        let firstX = 0;
        let lastX = 0;

        list.forEach((p, i) => {
            const x = ((p.t - (now - WINDOW_MS)) / WINDOW_MS) * 400;
            const y = 41 - (p[key] / peak) * 36;
            d += (i ? 'L' : 'M') + x.toFixed(1) + ' ' + y.toFixed(1);
            if (i === 0) firstX = x;
            lastX = x;
        });

        return {
            line: d,
            area: d + 'L' + lastX.toFixed(1) + ' 45L' + firstX.toFixed(1) + ' 45Z',
        };
    };

    const clear = () => {
        [rxLine, txLine, rxArea, txArea].forEach((el) => el.setAttribute('d', ''));
    };

    const frame = (stamp) => {
        requestAnimationFrame(frame);
        if (document.hidden || stamp - lastDraw < FRAME_MS) return;
        lastDraw = stamp;

        const now = Date.now();
        while (points.length > 2 && points[1].t < now - WINDOW_MS) points.shift();
        if (!points.length) return;

        const last = points[points.length - 1];
        const list = now - last.t <= HOLD_MS
            ? points.concat({ t: now, rx: last.rx, tx: last.tx })
            : points.slice();

        if (list.length < 2) {
            clear();
            return;
        }

        const peak = Math.max(1, ...list.map((p) => Math.max(p.rx, p.tx)));
        const rx = build('rx', list, peak, now);
        const tx = build('tx', list, peak, now);

        rxLine.setAttribute('d', rx.line);
        rxArea.setAttribute('d', rx.area);
        txLine.setAttribute('d', tx.line);
        txArea.setAttribute('d', tx.area);
    };

    requestAnimationFrame(frame);
})();


/* Trafik: angka halus dan penanda live */
(() => {
    const panel = document.getElementById('router-live-panel');
    if (!panel) return;

    const rxEl = panel.querySelector('[data-monitor="rx"]');
    const txEl = panel.querySelector('[data-monitor="tx"]');
    const live = document.getElementById('tf-live');
    const age = document.getElementById('tf-age');
    if (!rxEl || !txEl || !live || !age) return;

    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const TWEEN_MS = 1000;
    const STALE_S = 25;
    let previous = null;
    let lastTime = null;
    let frameId = 0;

    const format = (value) => value.toLocaleString('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    const tween = (from, to) => {
        cancelAnimationFrame(frameId);
        const begin = performance.now();

        const step = (now) => {
            const k = Math.min(1, (now - begin) / TWEEN_MS);
            const eased = 1 - Math.pow(1 - k, 3);
            rxEl.textContent = format(from.rx + (to.rx - from.rx) * eased);
            txEl.textContent = format(from.tx + (to.tx - from.tx) * eased);
            if (k < 1) frameId = requestAnimationFrame(step);
        };

        step(begin);
    };

    panel.addEventListener('router-monitor:data', (event) => {
        const data = event.detail || {};
        if (typeof data.rx !== 'number' || typeof data.tx !== 'number') return;

        const time = Date.parse(data.data_at);
        if (!Number.isFinite(time) || time === lastTime) return;

        lastTime = time;
        const target = { rx: data.rx, tx: data.tx };
        if (previous && !reduce) tween(previous, target);
        previous = target;
    });

    const tick = () => {
        if (lastTime === null) return;
        const seconds = Math.max(0, Math.round((Date.now() - lastTime) / 1000));

        if (seconds > STALE_S) {
            live.setAttribute('data-stale', '1');
            age.textContent = 'Data lama ' + seconds + ' dtk';
        } else {
            live.removeAttribute('data-stale');
            age.textContent = seconds + ' dtk lalu';
        }
    };

    setInterval(tick, 1000);
})();
