(() => {
    const modal = document.getElementById('pkg-sync-modal');
    if (!modal) return;

    const details = document.getElementById('pkg-sync-details');
    const result = document.getElementById('pkg-sync-result');
    const confirm = document.getElementById('pkg-sync-confirm');
    const push = document.getElementById('pkg-sync-push');
    const close = document.getElementById('pkg-sync-close');
    const cancel = document.getElementById('pkg-sync-cancel');
    let token = null;
    let pushUrl = '';
    let source = null;
    let pending = false;

    async function post(url, body = {}) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(body)
        });

        let data;
        try {
            data = await response.json();
        } catch (_) {
            throw new Error('Respons aplikasi tidak sesuai. Periksa sesi login dan lakukan preview ulang.');
        }

        if (!response.ok) throw new Error(data.message || 'Permintaan gagal.');
        return data;
    }

    const dismiss = () => { if (!pending) modal.close(); };
    close.addEventListener('click', dismiss);
    cancel.addEventListener('click', dismiss);
    modal.addEventListener('cancel', event => {
        if (pending) event.preventDefault();
    });

    modal.addEventListener('close', () => {
        token = null;
        document.body.classList.remove('pkg-dialog-open');
        source?.focus();
    });

    function busy(value) {
        pending = value;
        close.disabled = value;
        cancel.disabled = value;
        confirm.disabled = value;
        push.disabled = value || !token || !confirm.checked;
    }

    confirm.addEventListener('change', () => {
        push.disabled = pending || !token || !confirm.checked;
    });

    document.querySelectorAll('.pkg-sync-preview').forEach(button => {
        button.addEventListener('click', async () => {
            source = button;
            token = null;
            pushUrl = button.dataset.push;
            confirm.checked = false;
            result.textContent = '';
            details.textContent = 'Membaca profil dari MikroTik…';
            modal.showModal();
            document.body.classList.add('pkg-dialog-open');
            busy(true);

            try {
                const data = await post(button.dataset.url);
                token = data.token;
                details.textContent = [
                    `Paket: ${data.package}`,
                    `Router: ${data.router}`,
                    `Profil: ${data.profile}`,
                    '',
                    `rate-limit saat ini: ${data.before || '(kosong / mengikuti konfigurasi lain)'}`,
                    `rate-limit usulan: ${data.after}`,
                    '',
                    'Urutan rate-limit: upload/download pelanggan.',
                    data.same
                        ? 'Rate-limit sudah sama. Klik Push untuk memeriksa ulang tanpa mengubah konfigurasi.'
                        : 'Rate-limit berbeda. Push akan mengirim nilai usulan setelah persetujuan.',
                    ...data.reasons,
                    token ? 'Persetujuan preview berlaku 5 menit dan hanya sekali pakai.' : ''
                ].filter(line => line !== undefined).join('\n');

                button.closest('.pkg-router-sync')
                    .querySelector('.pkg-sync-status').textContent =
                        data.same ? 'Sama saat diperiksa' : 'Berbeda saat diperiksa';
            } catch (error) {
                details.textContent = error.message;
                button.closest('.pkg-router-sync')
                    .querySelector('.pkg-sync-status').textContent = 'Gagal diperiksa';
            } finally {
                busy(false);
            }
        });
    });

    push.addEventListener('click', async () => {
        if (!token || !confirm.checked || pending) return;
        const approvedToken = token;
        token = null;
        busy(true);
        result.textContent = 'Memeriksa konfigurasi dan memproses permintaan…';

        try {
            const data = await post(pushUrl, {
                token: approvedToken,
                confirmed: true
            });
            result.textContent = data.message;
            source.closest('.pkg-router-sync')
                .querySelector('.pkg-sync-status').textContent =
                    data.changed === false
                        ? 'Sudah sama · tanpa perubahan'
                        : data.verified
                            ? 'Push terverifikasi'
                            : 'Perlu diperiksa ulang';
        } catch (error) {
            result.textContent = error.message +
                ' Lakukan preview ulang untuk mengetahui keadaan terbaru.';
            source.closest('.pkg-router-sync')
                .querySelector('.pkg-sync-status').textContent = 'Perlu diperiksa ulang';
        } finally {
            busy(false);
        }
    });
})();
