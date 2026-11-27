(() => {
    document.addEventListener('click', async event => {
        const button = event.target.closest('.cu-network-check');
        if (!button || button.disabled) return;

        const panel = button.closest('.cu-details');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!panel || !csrf) return;

        const set = (selector, value) => {
            const target = panel.querySelector(selector);
            if (target) target.textContent = value;
        };

        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 35000);

        button.disabled = true;
        button.textContent = 'Memeriksa...';
        set('.cu-network-time', 'Sedang membaca router, tanpa perubahan konfigurasi.');
        set('.cu-network-note', '');

        try {
            const response = await fetch(button.dataset.url, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller.signal,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.redirected) throw new Error('Sesi login perlu diperiksa ulang.');

            const type = response.headers.get('content-type') || '';
            const result = type.includes('application/json')
                ? await response.json() : null;

            if (!response.ok) {
                let message = 'Pemeriksaan belum berhasil.';
                if (response.status === 429) {
                    message = 'Terlalu banyak pemeriksaan. Tunggu satu menit lalu coba kembali.';
                } else if ([401, 419].includes(response.status)) {
                    message = 'Sesi berakhir. Muat ulang halaman dan masuk kembali jika diminta.';
                } else if (response.status === 403) {
                    message = 'Anda tidak memiliki izin untuk pemeriksaan ini.';
                } else if (response.status === 502 || response.status === 422) {
                    message = result?.message || message;
                }
                throw new Error(message);
            }

            if (
                !result ||
                typeof result.access !== 'string' ||
                typeof result.connection !== 'string' ||
                typeof result.checked_at !== 'string'
            ) {
                throw new Error('Respons pemeriksaan tidak sesuai.');
            }

            if (!panel.isConnected) return;

            set('.cu-network-access', result.access);
            set('.cu-network-connection', result.connection);
            set('.cu-network-profile', result.profile);
            set('.cu-network-normal', result.normal_profile);
            set('.cu-network-isolation', result.isolation_profile);
            set('.cu-network-time', 'Diperiksa: ' + result.checked_at);
            set('.cu-network-note', result.note);
        } catch (error) {
            if (!panel.isConnected) return;

            set('.cu-network-access', 'Gagal diperiksa');
            set('.cu-network-connection', 'Gagal diperiksa');
            set('.cu-network-profile', 'Belum dapat dipastikan');
            set('.cu-network-normal', 'Belum dapat dipastikan');
            set('.cu-network-isolation', 'Belum dapat dipastikan');
            set('.cu-network-time', error.name === 'AbortError'
                ? 'Waktu pemeriksaan habis. Kondisi router belum dapat dipastikan.'
                : error instanceof TypeError
                    ? 'Koneksi ke aplikasi terganggu. Coba kembali.'
                    : error.message);
            set('.cu-network-note', 'Pemeriksaan gagal tidak berarti pelanggan terputus atau terisolir.');
        } finally {
            clearTimeout(timer);
            button.disabled = false;
            button.textContent = 'Periksa ulang';
        }
    });
})();
