(() => {
    const root = document.getElementById('dashboard-customer-connections');
    if (!root) return;

    const ring = document.getElementById('dc-ring');
    const label = document.getElementById('dc-ring-pct');
    const online = document.getElementById('dc-online');
    const offline = document.getElementById('dc-offline');
    const total = root.querySelector('[data-dc-detail="subscribed"] strong');

    if (!ring || !label || !online || !offline || !total) return;

    const readNumber = (element) => {
        const digits = element.textContent.replace(/[^\d]/g, '');
        return digits === '' ? null : parseInt(digits, 10);
    };

    const percent = (value, whole) =>
        Math.min(100, Math.round((value / whole) * 1000) / 10);

    const update = () => {
        const on = readNumber(online);
        const off = readNumber(offline);
        const all = readNumber(total);

        if (on === null || all === null || all <= 0) {
            ring.style.setProperty('--dc-on', '0');
            ring.style.setProperty('--dc-off-end', '0');
            label.textContent = '—';
            return;
        }

        const onPct = percent(on, all);
        const offPct = off === null ? 0 : percent(off, all);

        ring.style.setProperty('--dc-on', String(onPct));
        ring.style.setProperty('--dc-off-end', String(Math.min(100, onPct + offPct)));
        label.textContent = onPct.toString().replace('.', ',') + '%';
    };

    const observer = new MutationObserver(update);
    const options = { childList: true, characterData: true, subtree: true };

    [online, offline, total].forEach((element) =>
        observer.observe(element, options)
    );
    update();
})();
