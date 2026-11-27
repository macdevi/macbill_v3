(() => {
    const dialog = document.getElementById('cu-isolation-modal');
    const form = document.getElementById('cu-isolation-form');
    if (!dialog || !form) return;

    const title = document.getElementById('cu-isolation-title');
    const submit = document.getElementById('cu-iso-submit');
    const feedback = document.getElementById('cu-iso-feedback');
    const icon = document.getElementById('cu-iso-feedback-icon');
    const message = document.getElementById('cu-iso-feedback-message');
    const reference = document.getElementById('cu-iso-feedback-reference');
    const resultClose = document.getElementById('cu-iso-result-close');
    const field = name => form.elements.namedItem(name);

    let sending = false;
    let finished = false;
    let origin = null;

    function setCloseState(busy) {
        dialog.querySelectorAll('[data-iso-close]').forEach(node => {
            node.disabled = busy;
            node.hidden = busy;
        });
    }

    function showResult(state, heading, text, actionId = null) {
        sending = false;
        finished = true;
        dialog.removeAttribute('aria-busy');
        form.hidden = true;
        feedback.hidden = false;
        feedback.dataset.state = state;
        title.textContent = heading;
        message.textContent = text;
        reference.textContent = actionId
            ? 'Nomor tindakan #' + actionId
            : '';
        reference.hidden = !actionId;
        icon.className = '';
        icon.textContent = state === 'succeeded' ? '✓'
            : state === 'rejected' ? '×' : '!';
        setCloseState(false);
        resultClose.hidden = false;
        resultClose.textContent = state === 'succeeded'
            ? 'Selesai'
            : 'Tutup dan perbarui status';
        resultClose.focus();
    }

    function unknownResult() {
        showResult(
            'uncertain',
            'Hasil belum dapat diketahui',
            'Respons aplikasi tidak diterima dengan lengkap. Tindakan mungkin sudah diproses. Jangan kirim ulang. Tutup popup untuk memperbarui halaman, lalu periksa status pelanggan dan jurnal tindakan.'
        );
    }

    function close() {
        if (!sending) dialog.close();
    }

    document.addEventListener('click', event => {
        const target = event.target;
        if (!(target instanceof Element)) return;

        const button = target.closest('[data-iso-open]');

        if (button && !button.disabled && !dialog.open) {
            const details = button.closest('#cu-info-modal');
            origin = details
                ? (details.cuReturnFocus || button)
                : button;

            if (details?.open) details.close();

            sending = false;
            finished = false;
            form.reset();
            form.hidden = false;
            feedback.hidden = true;
            resultClose.hidden = true;
            dialog.removeAttribute('aria-busy');
            form.action = button.dataset.isoUrl;

            field('kind').value = button.dataset.isoKind;
            field('request_key').value = button.dataset.isoKey;
            field('version').value = button.dataset.isoVersion;
            field('pause_id').value = button.dataset.isoPause || '';

            for (const name of ['name', 'code', 'account', 'router']) {
                document.getElementById('cu-iso-' + name).textContent =
                    button.dataset[
                        'iso' + name[0].toUpperCase() + name.slice(1)
                    ] || '-';
            }

            const restore = button.dataset.isoKind === 'restore';

            title.textContent = restore
                ? 'Buka isolir pelanggan'
                : 'Isolir pelanggan';

            field('reason').value = restore
                ? 'Buka isolir manual dikonfirmasi melalui popup.'
                : 'Isolir manual dikonfirmasi melalui popup.';

            submit.disabled = false;
            submit.textContent = restore ? 'Buka isolir' : 'Jalankan isolir';
            submit.className = 'cu-button ' +
                (restore ? 'cu-iso-restore' : 'cu-iso-stop');

            setCloseState(false);
            resultClose.hidden = true;
            dialog.showModal();
            document.body.classList.add('cu-dialog-open');
            const cancelButton = form.querySelector('[data-iso-close]');
            if (cancelButton) cancelButton.focus();
            return;
        }

        if (target.closest('[data-iso-close]')) close();
    });

    dialog.addEventListener('cancel', event => {
        if (sending) event.preventDefault();
    });

    dialog.addEventListener('close', () => {
        document.body.classList.remove('cu-dialog-open');

        if (finished) {
            location.reload();
            return;
        }

        if (origin?.isConnected) origin.focus();
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (sending || finished || !form.reportValidity()) return;

        const body = new FormData(form);
        const restore = field('kind').value === 'restore';

        sending = true;
        submit.disabled = true;
        form.hidden = true;
        feedback.hidden = false;
        feedback.dataset.state = 'loading';
        title.textContent = restore
            ? 'Memproses buka isolir…'
            : 'Memproses isolir…';
        message.textContent =
            'Memverifikasi profil pelanggan di MikroTik. Tunggu hingga hasil muncul. Jangan tutup atau muat ulang halaman.';
        reference.textContent = '';
        reference.hidden = true;
        icon.textContent = '';
        icon.className = 'cu-iso-spinner';
        dialog.setAttribute('aria-busy', 'true');
        setCloseState(true);

        const abort = new AbortController();
        const timer = setTimeout(() => abort.abort(), 120000);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                redirect: 'error',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                signal: abort.signal
            });

            if (response.status === 429) {
                const retry = response.headers.get('Retry-After');
                const seconds = retry && /^\d+$/.test(retry) ? retry : null;

                showResult(
                    'rejected',
                    'Terlalu banyak permintaan',
                    'Permintaan ini ditolak oleh pembatas frekuensi.'
                    + (seconds
                        ? ' Tunggu sekitar ' + seconds + ' detik.'
                        : ' Tunggu beberapa saat.')
                    + ' Periksa status pelanggan sebelum melakukan tindakan baru.'
                );
                return;
            }

            if ([401, 403, 419].includes(response.status)) {
                showResult(
                    'rejected',
                    'Sesi atau izin perlu diperiksa',
                    'Permintaan ditolak karena sesi, token keamanan, atau izin akses. Tutup popup untuk memperbarui halaman dan masuk kembali bila diminta.'
                );
                return;
            }

            const type = response.headers.get('Content-Type') || '';

            if (!type.includes('application/json')) {
                unknownResult();
                return;
            }

            const data = await response.json();

            if (response.status === 422) {
                const errors = Object.values(data.errors || {})
                    .flat()
                    .filter(value => typeof value === 'string');

                showResult(
                    'rejected',
                    'Tindakan tidak dapat dilanjutkan',
                    errors.join('\n') ||
                    'Data atau kondisi pelanggan tidak memenuhi persyaratan tindakan.'
                );
                return;
            }

            if (
                response.ok &&
                data.isolation_result === true &&
                ['succeeded', 'rejected', 'uncertain'].includes(data.status)
            ) {
                showResult(
                    data.status,
                    data.title,
                    data.message,
                    data.action_id
                );
                return;
            }

            unknownResult();
        } catch (_) {
            unknownResult();
        } finally {
            clearTimeout(timer);
        }
    });

    window.addEventListener('beforeunload', event => {
        if (!sending) return;
        event.preventDefault();
        event.returnValue = '';
    });

    window.addEventListener('pageshow', event => {
        if (event.persisted) location.reload();
    });
})();
