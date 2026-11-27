(() => {
    const form = document.getElementById('cu-live-search');
    const input = form?.querySelector('input[name="q"]');
    const tools = document.getElementById('cu-tools');
    const status = document.getElementById('cu-search-status');
    if (!form || !input || !status) return;

    let timer = null;
    let controller = null;
    let version = 0;
    let composing = false;

    function invalidate() {
        clearTimeout(timer);
        controller?.abort();
        version++;
    }

    function searchUrl() {
        const url = new URL(form.action, location.href);
        const q = input.value.trim();
        if (q) url.searchParams.set('q', q);
        return url;
    }

    async function navigate(url) {
        invalidate();
        const ownVersion = version;
        const active = new AbortController();
        controller = active;
        let timedOut = false;

        const timeout = setTimeout(() => {
            timedOut = true;
            active.abort();
        }, 20000);

        document.querySelector('.cu-list')?.setAttribute('aria-busy', 'true');
        status.textContent = 'Mencari pelanggan...';

        try {
            const response = await fetch(url.href, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                signal: active.signal,
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok || response.redirected) {
                throw new Error('Search unavailable');
            }

            const html = await response.text();
            if (ownVersion !== version) return;

            const page = new DOMParser().parseFromString(html, 'text/html');
            const newList = page.querySelector('.cu-list');
            const newPagination = page.querySelector('.cu-pagination');
            const newCount = page.getElementById('cu-customer-count');

            const oldList = document.querySelector('.cu-list');
            const oldPagination = document.querySelector('.cu-pagination');
            const oldCount = document.getElementById('cu-customer-count');

            if (
                !newList || !newPagination || !newCount ||
                !oldList || !oldPagination || !oldCount
            ) {
                throw new Error('Unexpected search response');
            }

            oldList.replaceWith(document.importNode(newList, true));
            oldPagination.replaceWith(document.importNode(newPagination, true));
            oldCount.textContent = newCount.textContent;

            const exportLink = document.querySelector('[data-cu-export]');
            const nextExport = page.querySelector('[data-cu-export]');
            if (exportLink && nextExport) exportLink.href = nextExport.href;

            history.replaceState(null, '', url.href);
            document.dispatchEvent(new CustomEvent('customers:updated'));
            status.textContent = '';
        } catch (error) {
            if (ownVersion !== version) return;

            if (error.name !== 'AbortError' || timedOut) {
                status.textContent =
                    'Pencarian belum berhasil. Coba ketik ulang atau muat ulang halaman.';
            }
        } finally {
            clearTimeout(timeout);
            if (ownVersion === version) {
                document.querySelector('.cu-list')?.removeAttribute('aria-busy');
            }
        }
    }

    function queueSearch() {
        invalidate();
        status.textContent = 'Menyiapkan pencarian...';
        timer = setTimeout(() => navigate(searchUrl()), 350);
    }

    input.addEventListener('compositionstart', () => {
        composing = true;
        invalidate();
    });

    input.addEventListener('compositionend', () => {
        composing = false;
        queueSearch();
    });

    input.addEventListener('input', () => {
        if (!composing) queueSearch();
    });

    form.addEventListener('submit', event => {
        event.preventDefault();
        navigate(searchUrl());
    });

    document.addEventListener('click', event => {
        if (tools && !tools.contains(event.target)) tools.open = false;

        const pageLink = event.target.closest('.cu-pagination a');
        if (
            pageLink && !event.ctrlKey && !event.metaKey &&
            !event.shiftKey && !event.altKey && event.button === 0
        ) {
            const url = new URL(pageLink.href, location.href);
            if (url.origin === location.origin) {
                event.preventDefault();
                navigate(url);
            }
        }

        if (tools && event.target.closest('.cu-tools-panel .cu-button')) {
            tools.open = false;
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && tools?.open) {
            tools.open = false;
            tools.querySelector('summary')?.focus();
        }
    });

    window.addEventListener('pagehide', invalidate);
})();
