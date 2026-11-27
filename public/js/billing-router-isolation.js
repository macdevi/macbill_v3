(() => {
    const form = document.getElementById('router-config-form');
    const section = document.getElementById('router-isolation-settings');
    if (!form || !section) return;

    const method = document.getElementById('router-isolation-method');
    const profile = document.getElementById('router-isolation-profile');
    const fields = document.getElementById('router-isolation-profile-fields');
    const button = document.getElementById('router-load-profiles');
    const message = document.getElementById('router-profile-message');

    let url = '';
    let generation = 0;
    let request = null;

    function toggle() {
        const enabled = method.value === 'ppp_profile';
        fields.hidden = !enabled;
        profile.required = enabled;
        profile.disabled = !enabled;
    }

    function setup(nextUrl, selectedMethod, selectedProfile) {
        generation++;
        request?.abort();
        request = null;
        url = nextUrl || '';
        method.value = selectedMethod || 'none';

        profile.replaceChildren(new Option('Pilih profil isolir', ''));
        if (selectedProfile) {
            profile.add(new Option(selectedProfile, selectedProfile, true, true));
        }

        button.disabled = !url;
        button.textContent = 'Ambil profil dari MikroTik';
        message.textContent = url
            ? 'Klik tombol untuk membaca daftar profil terbaru. Pilihan akan diverifikasi saat disimpan.'
            : 'Simpan router terlebih dahulu, lalu buka kembali melalui Edit.';
        toggle();
    }

    method.addEventListener('change', toggle);

    document.getElementById('router-add').addEventListener('click', () => {
        setup('', 'none', '');
    });

    document.querySelectorAll('.router-edit').forEach(edit => {
        edit.addEventListener('click', () => {
            const router = JSON.parse(edit.dataset.router);
            setup(router.profiles_url, router.isolation_method, router.isolation_profile);
        });
    });

    document.getElementById('router-modal').addEventListener('close', () => {
        generation++;
        request?.abort();
    });

    button.addEventListener('click', async () => {
        if (!url) return;

        const current = ++generation;
        const selected = profile.value;
        request?.abort();
        const controller = new AbortController();
        request = controller;
        const timeout = setTimeout(() => controller.abort(), 30000);

        button.disabled = true;
        button.textContent = 'Membaca profil…';
        message.textContent = 'Menghubungi router dengan konfigurasi yang sudah tersimpan…';

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller.signal
            });

            if (!response.ok) {
                throw new Error(response.status === 429
                    ? 'Terlalu banyak permintaan. Tunggu satu menit lalu coba lagi.'
                    : 'Gagal membaca profil. Periksa sesi login, koneksi API, dan hak baca akun.');
            }

            const data = await response.json();
            if (!Array.isArray(data.profiles) ||
                !data.profiles.every(name => typeof name === 'string')) {
                throw new Error('Respons profil tidak sesuai.');
            }

            if (current !== generation) return;

            profile.replaceChildren(new Option('Pilih profil isolir', ''));
            data.profiles.forEach(name => profile.add(new Option(name, name)));

            if (data.profiles.includes(selected)) profile.value = selected;

            message.textContent = data.profiles.length
                ? `${data.profiles.length} profil ditemukan. Pilih profil isolir, lalu simpan.`
                : 'Tidak ada profil PPP yang ditemukan.';

            if (selected && !data.profiles.includes(selected)) {
                message.textContent += ' Profil sebelumnya tidak ditemukan. Pilih ulang.';
            }
        } catch (error) {
            if (current !== generation) return;
            message.textContent = error.name === 'AbortError'
                ? 'Pembacaan melewati batas waktu. Coba lagi.'
                : error.message;
        } finally {
            clearTimeout(timeout);
            if (current === generation) {
                button.disabled = false;
                button.textContent = 'Ambil profil dari MikroTik';
            }
        }
    });

    setup(
        section.dataset.initialUrl,
        section.dataset.initialMethod,
        section.dataset.initialProfile
    );
})();
