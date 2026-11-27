(() => {
    const root = document.documentElement;
    const key = 'billing-theme';
    const buttons = new Set();
    let theme = 'dark';

    try {
        const saved = localStorage.getItem(key);
        if (saved === 'light' || saved === 'dark') theme = saved;
    } catch (_) {}

    root.dataset.theme = theme;

    const sun = '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></svg>';
    const moon = '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.9 13A9 9 0 0 1 11 3.1 9 9 0 1 0 20.9 13Z"/></svg>';

    function render() {
        const light = root.dataset.theme === 'light';

        buttons.forEach(button => {
            button.innerHTML = light ? moon : sun;
            const label = light ? 'Aktifkan tema gelap' : 'Aktifkan tema terang';
            button.setAttribute('aria-label', label);
            button.title = label;
        });

        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) meta.content = light ? '#edf3f8' : '#0b1425';
    }

    function apply(value, persist = false) {
        if (value !== 'light' && value !== 'dark') return;
        root.dataset.theme = value;

        if (persist) {
            try { localStorage.setItem(key, value); } catch (_) {}
        }

        render();
        window.dispatchEvent(new CustomEvent('billing:theme-change', {
            detail: { theme: value }
        }));
    }

    function mount() {
        let target = document.querySelector('.topbar .account')
            || document.querySelector('.pkg-topbar')
            || document.querySelector('.router-topbar');

        if (!target && document.body.classList.contains('billing-login')) {
            target = document.createElement('div');
            target.id = 'login-theme-control';
            document.body.append(target);
        }

        if (!target || document.getElementById('theme-toggle')) return;

        const button = document.createElement('button');
        button.type = 'button';
        button.id = 'theme-toggle';
        button.className = 'theme-toggle';

        button.addEventListener('click', () => {
            apply(root.dataset.theme === 'light' ? 'dark' : 'light', true);
        });

        if (target.matches('.pkg-topbar')) {
            target.append(button);
        } else {
            target.prepend(button);
        }

        buttons.add(button);
        render();
    }

    window.addEventListener('storage', event => {
        if (event.key === key) {
            apply(event.newValue === 'light' ? 'light' : 'dark');
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mount, { once: true });
    } else {
        mount();
    }
})();
