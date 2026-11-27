(() => {
    const dialog = document.getElementById('cu-bulk-prices');
    const openButton = document.getElementById('cu-bulk-prices-open');
    if (!dialog || !openButton) return;

    const form = document.getElementById('cbp-form');
    const tbody = document.getElementById('cbp-rows');
    const message = document.getElementById('cbp-message');
    const save = document.getElementById('cbp-save');
    const reload = document.getElementById('cbp-reload');
    const scope = document.getElementById('cbp-scope');
    const closes = [...dialog.querySelectorAll('[data-cbp-close]')];
    const formatter = new Intl.NumberFormat('id-ID');
    let rows = [];
    let busy = false;
    let uncertain = false;
    let returnUrl = window.location.href;

    function notify(text, error = false) {
        message.textContent = text;
        message.classList.toggle('is-error', error);
    }

    function parse(input) {
        const value = input.value.trim();
        if (value === '') return null;
        if (!/^(?:\d+|\d{1,3}(?:\.\d{3})+)$/.test(value)) return undefined;
        const number = Number(value.replaceAll('.', ''));
        return Number.isSafeInteger(number) && number <= 1000000000
            ? number : undefined;
    }

    function changes() {
        return rows.filter(row => {
            const value = parse(row.input);
            row.input.setCustomValidity(value === undefined
                ? 'Isi rupiah bulat, maksimal 1.000.000.000, atau kosongkan.' : '');
            const changed = !row.locked && value !== row.original;
            row.input.classList.toggle('is-changed', changed);
            return changed;
        });
    }

    function sync() {
        const count = changes().length;
        save.textContent = busy ? 'Memproses…' : `Simpan ${count} perubahan`;
        save.disabled = busy || uncertain || count === 0;
        closes.forEach(button => button.disabled = busy);
        rows.forEach(row => row.input.disabled = busy || row.locked || uncertain);
    }

    function cell(parent, text, tag = 'td') {
        const element = document.createElement(tag);
        element.textContent = text;
        parent.append(element);
        return element;
    }

    function close() {
        if (busy) return;
        if (uncertain) {
            window.location.assign(returnUrl);
            return;
        }
        if (changes().length && !window.confirm('Tutup tanpa menyimpan perubahan nominal?')) return;
        dialog.close();
        openButton.focus();
    }

    closes.forEach(button => button.addEventListener('click', close));
    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        close();
    });
    reload.addEventListener('click', () => window.location.assign(returnUrl));

    openButton.addEventListener('click', async () => {
        if (dialog.open) return;
        document.getElementById('cu-tools')?.removeAttribute('open');
        rows = [];
        tbody.replaceChildren();
        uncertain = false;
        reload.hidden = true;
        busy = true;
        notify('Memuat data…');
        scope.textContent = 'Semua pelanggan · urutan nama A–Z';
        dialog.showModal();
        sync();

        returnUrl = window.location.href;

        try {
            const response = await fetch(dialog.dataset.readUrl, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok) throw new Error('read_failed');
            const data = await response.json();

            data.rows.forEach(item => {
                const tr = document.createElement('tr');
                const name = cell(tr, item.name);
                cell(name, item.code, 'small');

                if (item.locked) cell(name, 'Terkunci: tindakan jaringan belum selesai', 'small');

                cell(tr, item.package_price === null
                    ? 'Paket tidak tersedia' : 'Rp' + formatter.format(item.package_price));

                const price = cell(tr, '');
                const input = document.createElement('input');
                input.type = 'text';
                input.inputMode = 'numeric';
                input.maxLength = 20;
                input.autocomplete = 'off';
                input.placeholder = 'Ikuti paket';
                input.setAttribute('aria-label', 'Harga khusus ' + item.name);

                const original = item.custom_price === null
                    ? null : Number(item.custom_price);

                input.value = original === null ? '' : formatter.format(original);
                price.append(input);
                tbody.append(tr);

                rows.push({ ...item, original, input });
                input.addEventListener('input', sync);
                input.addEventListener('blur', () => {
                    const value = parse(input);
                    if (value !== null && value !== undefined) {
                        input.value = formatter.format(value);
                    }
                    sync();
                });
            });

            scope.textContent = `${data.total} pelanggan · urutan nama A–Z`;
            notify(data.rows.length ? '' : 'Belum ada pelanggan.');
        } catch (_) {
            notify('Data belum dapat dimuat. Tutup popup lalu coba kembali.', true);
        } finally {
            busy = false;
            sync();
        }
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy || uncertain) return;
        const modified = changes();
        if (!modified.length || !form.reportValidity()) return;

        const payload = {
            items: modified.map(row => ({
                id: row.id,
                version: row.version,
                custom_monthly_price: parse(row.input),
            })),
        };

        busy = true;
        notify('Menyimpan perubahan…');
        sync();

        try {
            const response = await fetch(dialog.dataset.saveUrl, {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(payload),
            });

            if (response.status === 422) {
                const data = await response.json();
                notify(Object.values(data.errors || {}).flat().join('\n')
                    || 'Periksa nominal atau muat ulang data.', true);
                return;
            }

            if (!response.ok) throw new Error('save_unconfirmed');
            const data = await response.json();
            if (!data.saved) throw new Error('save_unconfirmed');
            window.location.assign(returnUrl);
        } catch (_) {
            uncertain = true;
            reload.hidden = false;
            notify('Hasil penyimpanan belum dapat dipastikan. Muat ulang halaman sebelum mengedit kembali.', true);
        } finally {
            busy = false;
            sync();
        }
    });
})();
