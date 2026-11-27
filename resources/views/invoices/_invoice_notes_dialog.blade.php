<dialog id="iv-invoice-notes-dialog" class="iv-dialog"
    aria-labelledby="iv-invoice-notes-title">
    <div class="iv-dialog-head">
        <div>
            <h2 id="iv-invoice-notes-title">Nota pembayaran</h2>
            <p class="iv-muted" data-notes-title></p>
        </div>
        <button type="button" class="iv-dialog-x"
            data-iv-close aria-label="Tutup">&times;</button>
    </div>

    <div class="iv-dialog-body">
        <p class="iv-muted" role="status" data-notes-message></p>
        <button type="button" class="cu-button"
            data-notes-retry hidden>Coba lagi</button>
        <div data-notes-results aria-busy="false"></div>
    </div>

    <div class="iv-dialog-foot">
        <button type="button" class="cu-button" data-iv-close>Tutup</button>
    </div>
</dialog>

<script>
(function () {
    var dialog = document.getElementById('iv-invoice-notes-dialog');
    var results = dialog.querySelector('[data-notes-results]');
    var message = dialog.querySelector('[data-notes-message]');
    var retry = dialog.querySelector('[data-notes-retry]');
    var endpoint = '';
    var pending = null;
    var requestNumber = 0;
    var currentPage = 1;

    function openExistingNota(id) {
        var button = document.createElement('button');
        button.type = 'button';
        button.hidden = true;
        button.setAttribute('data-iv-nota', String(id));
        document.body.appendChild(button);
        button.click();
        button.remove();
    }

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
        message.textContent = 'Memuat nota...';
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
            dialog.querySelector('[data-notes-title]').textContent =
                data.title || '';
            results.innerHTML = data.html;
            results.setAttribute('aria-busy', 'false');
            message.textContent = '';

            if (data.receipt_id) {
                dialog.close();
                openExistingNota(data.receipt_id);
            }
        })
        .catch(function (error) {
            if (number !== requestNumber || error.name === 'AbortError') return;
            results.setAttribute('aria-busy', 'false');
            message.textContent =
                'Daftar nota tidak dapat dimuat. Coba lagi atau muat ulang halaman.';
            retry.hidden = false;
        });
    }

    document.addEventListener('click', function (event) {
        var opener = event.target.closest('[data-iv-notes]');
        if (opener) {
            endpoint = opener.dataset.ivNotes;
            dialog.querySelector('[data-notes-title]').textContent = '';
            if (!dialog.open) dialog.showModal();
            load(1);
            return;
        }

        var page = event.target.closest('[data-iv-notes-page]');
        if (page && dialog.contains(page) && !page.disabled) {
            load(Number(page.dataset.ivNotesPage));
        }
    });

    retry.addEventListener('click', function () { load(currentPage); });

    dialog.addEventListener('close', function () {
        ++requestNumber;
        if (pending) pending.abort();
        results.setAttribute('aria-busy', 'false');
    });
})();
</script>
