(() => {
    const modal = document.getElementById('pkg-modal');
    const form = document.getElementById('pkg-form');
    const deleteModal = document.getElementById('pkg-delete-modal');
    const deleteForm = document.getElementById('pkg-delete-form');
    const service = document.getElementById('pkg-service');
    const rows = [...document.querySelectorAll('.pkg-router-row')];
    const field = name => form.elements.namedItem(name);
    let returnFocus = null;
    let generation = 0;
    const requests = new Set();

    function cancelReads() {
        generation++;
        requests.forEach(controller => controller.abort());
        requests.clear();
    }

    function open(dialog, trigger) {
        returnFocus = trigger || document.activeElement;
        dialog.showModal();
        document.body.classList.add('pkg-dialog-open');
    }

    [modal, deleteModal].forEach(dialog => {
        dialog.querySelectorAll('[data-close]').forEach(button => {
            button.addEventListener('click', () => dialog.close());
        });
        dialog.addEventListener('close', () => {
            if (dialog === modal) cancelReads();
            document.body.classList.remove('pkg-dialog-open');
            if (returnFocus?.isConnected) returnFocus.focus();
        });
    });

    function toggleRow(row) {
        const checked = row.querySelector('.pkg-router-enabled').checked;
        const enabled = checked && service.value === 'pppoe';
        row.querySelector('.pkg-router-fields').hidden = !enabled;
        row.querySelector('.pkg-router-id').disabled = !enabled;
        const select = row.querySelector('.pkg-profile');
        select.disabled = !enabled;
        select.required = enabled;
    }

    function toggleService() {
        const pppoe = service.value === 'pppoe';
        document.getElementById('pkg-mapping-section').hidden = !pppoe;
        document.getElementById('pkg-static-note').hidden = pppoe;
        field('download_mbps').required = pppoe;
        field('upload_mbps').required = pppoe;
        rows.forEach(toggleRow);
    }

    function prepare(data = {}, url = form.dataset.store) {
        cancelReads();
        form.reset();
        form.action = url;
        field('_method').value = data.id ? 'PUT' : 'POST';
        field('_package_id').value = data.id || '';
        field('name').value = data.name ?? '';
        field('monthly_price').value = data.monthly_price ?? '';
        field('download_mbps').value = data.download_mbps ?? '';
        field('upload_mbps').value = data.upload_mbps ?? '';
        field('rate_limit').value = data.rate_limit ?? '';
        form.dispatchEvent(new CustomEvent('package:prepared'));
        service.value = data.service_type || 'pppoe';
        field('is_active').value =
            data.is_active === false || String(data.is_active) === '0' ? '0' : '1';

        document.getElementById('pkg-title').textContent = data.id ? 'Edit paket' : 'Tambah paket';

        const mappings = Object.values(data.mappings || {});
        rows.forEach(row => {
            const mapping = mappings.find(item =>
                String(item.router_id) === row.dataset.routerId);
            row.querySelector('.pkg-router-enabled').checked = Boolean(mapping);
            const select = row.querySelector('.pkg-profile');
            select.replaceChildren(new Option('Pilih profil normal', ''));
            if (mapping?.profile) {
                select.add(new Option(mapping.profile, mapping.profile, true, true));
            }
            row.querySelector('.pkg-fetch-message').textContent =
                mapping ? 'Pilihan tersimpan. Ambil profil untuk memperbarui daftar.' : '';
            const fetchButton = row.querySelector('.pkg-fetch');
            fetchButton.disabled = false;
            fetchButton.textContent = 'Ambil profil';
        });

        const save = form.querySelector('button[type="submit"]');
        save.disabled = false;
        save.textContent = 'Simpan paket';
        toggleService();
    }

    service.addEventListener('change', toggleService);

    rows.forEach(row => {
        row.querySelector('.pkg-router-enabled').addEventListener('change', () => toggleRow(row));
        row.querySelector('.pkg-fetch').addEventListener('click', async () => {
            const version = generation;
            const button = row.querySelector('.pkg-fetch');
            const message = row.querySelector('.pkg-fetch-message');
            const select = row.querySelector('.pkg-profile');
            const selected = select.value;
            const controller = new AbortController();
            requests.add(controller);
            const timeout = setTimeout(() => controller.abort(), 30000);
            button.disabled = true;
            button.textContent = 'Membaca…';
            message.textContent = 'Menghubungi router…';

            try {
                const response = await fetch(row.dataset.url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                    signal: controller.signal
                });
                if (!response.ok) throw new Error(response.status === 429
                    ? 'Terlalu banyak permintaan. Tunggu satu menit.'
                    : 'Gagal membaca profil. Periksa login, koneksi, dan hak baca API.');
                const data = await response.json();
                if (!Array.isArray(data.profiles) ||
                    !data.profiles.every(name => typeof name === 'string')) {
                    throw new Error('Respons profil tidak sesuai.');
                }
                if (version !== generation) return;
                select.replaceChildren(new Option('Pilih profil normal', ''));
                data.profiles.forEach(name => select.add(new Option(name, name)));
                if (data.profiles.includes(selected)) select.value = selected;
                message.textContent = `${data.profiles.length} profil ditemukan. Pilih profil normal.`;
                if (selected && !data.profiles.includes(selected)) {
                    message.textContent += ' Profil sebelumnya tidak ditemukan.';
                }
            } catch (error) {
                if (version === generation) {
                    message.textContent = error.name === 'AbortError'
                        ? 'Pembacaan melewati batas waktu. Coba lagi.' : error.message;
                }
            } finally {
                clearTimeout(timeout);
                requests.delete(controller);
                if (version === generation) {
                    button.disabled = false;
                    button.textContent = 'Ambil profil';
                }
            }
        });
    });

    document.getElementById('pkg-add').addEventListener('click', event => {
        prepare();
        open(modal, event.currentTarget);
        field('name').focus();
    });

    document.querySelectorAll('.pkg-edit').forEach(button => {
        button.addEventListener('click', () => {
            prepare(JSON.parse(button.dataset.package), button.dataset.url);
            open(modal, button);
            field('name').focus();
        });
    });

    document.querySelectorAll('.pkg-delete').forEach(button => {
        button.addEventListener('click', () => {
            deleteForm.action = button.dataset.url;
            document.getElementById('pkg-delete-name').textContent = button.dataset.name;
            const submit = deleteForm.querySelector('button[type="submit"]');
            submit.disabled = false;
            submit.textContent = 'Ya, hapus paket';
            open(deleteModal, button);
            deleteModal.querySelector('[autofocus]').focus();
        });
    });

    form.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        button.textContent = 'Memverifikasi dan menyimpan…';
    });

    deleteForm.addEventListener('submit', event => {
        if (!deleteForm.getAttribute('action')) {
            event.preventDefault();
            return;
        }
        const button = deleteForm.querySelector('button[type="submit"]');
        button.disabled = true;
        button.textContent = 'Menghapus…';
    });

    const initial = JSON.parse(document.getElementById('pkg-initial').dataset.value);
    if (initial.open) {
        const url = form.action;
        prepare(initial, url);
        open(modal);
    } else {
        prepare();
    }
})();
