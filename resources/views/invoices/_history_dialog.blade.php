<dialog id="iv-history-dialog" class="iv-dialog"
    aria-labelledby="iv-history-title">
    <div class="iv-dialog-head">
        <div>
            <h2 id="iv-history-title">Riwayat pembayaran</h2>
            <p class="iv-muted" data-hist-customer></p>
        </div>
        <button type="button" class="iv-dialog-x"
            data-iv-close aria-label="Tutup">&times;</button>
    </div>

    <div class="iv-dialog-body">
        <p class="iv-muted" role="status" data-hist-message></p>
        <button type="button" class="cu-button"
            data-hist-retry hidden>Coba lagi</button>
        <div data-hist-results aria-busy="false"></div>
    </div>

    <div class="iv-dialog-foot">
        <button type="button" class="cu-button" data-iv-close>Tutup</button>
    </div>
</dialog>

<script>
(function () {
    var dialog = document.getElementById('iv-history-dialog');
    var results = dialog.querySelector('[data-hist-results]');
    var message = dialog.querySelector('[data-hist-message]');
    var retry = dialog.querySelector('[data-hist-retry]');
    var endpoint = '';
    var requestNumber = 0;
    var pending = null;
    var currentPage = 1;

    function load(page) {
        if (!endpoint || !dialog.open) return;
        currentPage = page;
        var number = ++requestNumber;
        if (pending) pending.abort();
        pending = new AbortController();

        var url = new URL(endpoint, window.location.href);
        url.searchParams.set('page', String(page));

        results.textContent = '';
        results.setAttribute('aria-busy', 'true');
        message.textContent = 'Memuat riwayat...';
        retry.hidden = true;

        fetch(url.toString(), {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            cache: 'no-store',
            signal: pending.signal
        })
        .then(function (response) {
            if (!response.ok) throw new Error(String(response.status));
            return response.json();
        })
        .then(function (data) {
            if (number !== requestNumber || !dialog.open) return;
            results.innerHTML = data.html;
            dialog.querySelector('[data-hist-customer]').textContent =
                data.customer || '';
            message.textContent = '';
            results.setAttribute('aria-busy', 'false');
            dialog.querySelector('.iv-dialog-body').scrollTop = 0;
        })
        .catch(function (error) {
            if (number !== requestNumber || error.name === 'AbortError') return;
            message.textContent =
                'Riwayat tidak dapat dimuat. Coba lagi atau muat ulang halaman jika sesi masuk berakhir.';
            results.setAttribute('aria-busy', 'false');
            retry.hidden = false;
        });
    }

    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-iv-history]');
        if (opener) {
            endpoint = opener.dataset.ivHistory;
            dialog.querySelector('[data-hist-customer]').textContent = '';
            if (!dialog.open) dialog.showModal();
            load(1);
            return;
        }

        var page = event.target.closest('[data-iv-history-page]');
        if (page && dialog.contains(page) && !page.disabled) {
            load(Number(page.dataset.ivHistoryPage));
        }
    });

    retry.addEventListener('click', function () { load(currentPage); });

    dialog.addEventListener('close', function () {
        ++requestNumber;
        if (pending) pending.abort();
        results.setAttribute('aria-busy', 'false');
    });

    dialog.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.matches('form')) return;
        var button = form.querySelector('[type=submit]');
        if (form.dataset.histSubmitting === '1') {
            event.preventDefault();
            return;
        }
        form.dataset.histSubmitting = '1';
        if (button) button.disabled = true;
    });
})();
</script>
