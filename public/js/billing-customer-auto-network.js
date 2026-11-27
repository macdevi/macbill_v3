(() => {
    const endpoint = document.querySelector('meta[name="cu-network-status-url"]')?.content;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!endpoint || !csrf) return;

    const states = new Map();
    const controllers = new Set();
    let running = false;
    let pending = false;
    let timer = null;

    const labels = {
        online: 'Online',
        offline: 'Offline',
        unknown: 'Tidak diketahui',
        unsupported: 'Belum didukung',
        loading: 'Memeriksa...'
    };

    function timeLabel(value, state) {
        if (state === 'unsupported') return 'Pemeriksaan IP statis belum tersedia.';
        if (!value) {
            return state === 'unknown'
                ? 'Pemeriksaan belum berhasil.'
                : 'Menunggu pemeriksaan otomatis.';
        }

        const date = new Date(value);
        if (!Number.isFinite(date.getTime())) return 'Waktu pemeriksaan tidak tersedia.';

        const formatted = new Intl.DateTimeFormat('id-ID', {
            dateStyle: 'short',
            timeStyle: 'medium'
        }).format(date);

        return (state === 'unknown' ? 'Pemeriksaan gagal: ' : 'Diperbarui: ') + formatted;
    }

    function paint() {
        document.querySelectorAll('[data-cu-auto-id]').forEach(node => {
            const result = states.get(node.dataset.cuAutoId);
            if (!result) return;

            node.textContent = labels[result.state] || labels.unknown;
            node.dataset.state = result.state;
            node.title = timeLabel(result.checked_at, result.state);
        });

        document.querySelectorAll('[data-cu-time-id]').forEach(node => {
            const result = states.get(node.dataset.cuTimeId);
            if (result) node.textContent = timeLabel(result.checked_at, result.state);
        });
    }

    function groups() {
        const routers = new Map();

        document.querySelectorAll('.cu-card[data-cu-network-id]').forEach(card => {
            const id = card.dataset.cuNetworkId;

            if (card.dataset.cuNetworkType !== 'pppoe') {
                states.set(id, { state: 'unsupported', checked_at: null });
                return;
            }

            const router = card.dataset.cuNetworkRouter;
            if (!routers.has(router)) routers.set(router, []);
            routers.get(router).push(id);
        });

        return [...routers.values()];
    }

    async function fetchGroup(ids) {
        const controller = new AbortController();
        controllers.add(controller);
        const timeout = setTimeout(() => controller.abort(), 35000);

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller.signal,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ customer_ids: ids })
            });

            if (!response.ok || response.redirected) throw new Error('Unavailable');

            const result = await response.json();
            if (!Array.isArray(result.customers)) throw new Error('Unexpected response');

            const received = new Map();

            for (const row of result.customers) {
                const id = String(row.id);
                if (
                    !ids.includes(id) ||
                    !['online', 'offline', 'unknown', 'unsupported'].includes(row.state)
                ) {
                    throw new Error('Invalid status');
                }
                received.set(id, row);
            }

            if (received.size !== ids.length) throw new Error('Incomplete response');

            for (const id of ids) states.set(id, received.get(id));
        } catch (error) {
            if (!document.hidden) {
                ids.forEach(id => states.set(id, {
                    state: 'unknown',
                    checked_at: null
                }));
            }
        } finally {
            clearTimeout(timeout);
            controllers.delete(controller);
            paint();
        }
    }

    async function refresh() {
        clearTimeout(timer);

        if (document.hidden) return;
        if (running) {
            pending = true;
            return;
        }

        running = true;
        pending = false;
        const queue = groups();
        paint();

        try {
            async function worker() {
                while (queue.length && !document.hidden) {
                    const ids = queue.shift();
                    if (ids) await fetchGroup(ids);
                }
            }

            await Promise.all([worker(), worker()]);
        } finally {
            running = false;

            if (!document.hidden) {
                if (pending) {
                    pending = false;
                    timer = setTimeout(refresh, 0);
                } else {
                    timer = setTimeout(refresh, 30000);
                }
            }
        }
    }

    document.addEventListener('customers:updated', () => {
        paint();
        refresh();
    });

    const infoBody = document.getElementById('cu-info-body');
    if (infoBody) {
        new MutationObserver(paint).observe(infoBody, { childList: true });
    }

    document.addEventListener('visibilitychange', () => {
        clearTimeout(timer);

        if (document.hidden) {
            controllers.forEach(controller => controller.abort());
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
