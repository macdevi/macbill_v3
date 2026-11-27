(() => {
    const form = document.getElementById('pkg-form');
    if (!form) return;

    const raw = form.elements.namedItem('rate_limit');
    const download = form.elements.namedItem('download_mbps');
    const upload = form.elements.namedItem('upload_mbps');
    const advanced = document.getElementById('pkg-advanced-values');
    const source = document.getElementById('pkg-import-source');
    const info = document.getElementById('pkg-info-modal');
    const chooser = document.getElementById('pkg-router-choice');
    let generation = 0;
    let controller = null;
    let origin = null;

    function tokenPair(token) {
        if (!token) return null;
        const values = token.split('/');
        return [values[0], values[1] ?? values[0]];
    }

    function mbps(value) {
        const match = /^(\d+(?:\.\d+)?)([kKmMgG]?)$/.exec(value);
        if (!match) throw new Error('Format kecepatan tidak valid.');
        const factor = { '': 1, K: 1000, M: 1000000, G: 1000000000 }[match[2].toUpperCase()];
        return Number(match[1]) * factor / 1000000;
    }

    function number(value) {
        return String(Number(Number(value).toFixed(3)));
    }

    function details(rate) {
        if (!rate?.trim()) return [
            ['Rate-limit lengkap', 'Belum disimpan; memakai download/upload dasar saat push.']
        ];

        const t = rate.trim().split(/\s+/);
        const pairText = (token, fallback) => {
            const p = tokenPair(token);
            return p ? `Upload ${p[0]} / Download ${p[1]}` : fallback;
        };

        return [
            ['Burst limit', pairText(t[1], 'Tidak ditentukan')],
            ['Burst threshold', pairText(t[2], t[1] ? 'Mengikuti rate dasar' : 'Tidak ditentukan')],
            ['Burst-time (detik)', pairText(t[3], t[1] ? 'Default MikroTik' : 'Tidak ditentukan')],
            ['Priority', t[4] || 'Default MikroTik'],
            ['Minimum bandwidth', pairText(t[5], 'Tidak ditentukan; mengikuti default MikroTik')],
            ['Rate-limit lengkap', rate]
        ];
    }

    function renderRows(container, rows) {
        container.replaceChildren();
        rows.forEach(([label, value]) => {
            const row = document.createElement('div');
            row.className = 'pkg-detail-row';
            const key = document.createElement('span');
            key.textContent = label;
            const text = document.createElement('strong');
            text.textContent = value;
            row.append(key, text);
            container.append(row);
        });
    }

    function renderAdvanced() {
        renderRows(advanced, details(raw.value));
    }

    function stopImport() {
        generation++;
        controller?.abort();
        controller = null;
    }

    form.addEventListener('package:prepared', () => {
        stopImport();
        source.textContent = '';
        renderAdvanced();
    });

    document.getElementById('pkg-modal').addEventListener('close', stopImport);

    raw.addEventListener('input', () => {
        stopImport();
        source.textContent = 'Perubahan manual. Belum disimpan atau dikirim ke MikroTik.';
        try {
            const pair = tokenPair(raw.value.trim().split(/\s+/)[0]);
            if (pair && pair[0]) {
                const up = mbps(pair[0]);
                const down = mbps(pair[1]);
                upload.value = number(up);
                download.value = number(down);
            }
        } catch (_) {
            source.textContent = 'Format rate dasar belum valid. Periksa rate-limit sebelum menyimpan.';
        }
        renderAdvanced();
    });

    [download, upload].forEach(input => {
        input.addEventListener('input', () => {
            stopImport();

            if (download.value === '' || upload.value === '') return;

            const t = raw.value.trim() ? raw.value.trim().split(/\s+/) : [];
            t[0] = `${upload.value}M/${download.value}M`;
            raw.value = t.join(' ');
            source.textContent = 'Rate dasar diubah. Parameter lanjutan dipertahankan; periksa kembali threshold dan minimum bandwidth.';
            renderAdvanced();
        });
    });

    document.querySelectorAll('.pkg-router-row').forEach(row => {
        const select = row.querySelector('.pkg-profile');

        select.addEventListener('change', async () => {
            stopImport();
            const current = generation;

            if (!select.value) {
                source.textContent = 'Profil belum dipilih. Nilai paket sebelumnya tidak diubah.';
                return;
            }

            controller = new AbortController();
            const localController = controller;
            const timeout = setTimeout(() => localController.abort(), 30000);
            const url = new URL(row.dataset.detailUrl, location.origin);
            url.searchParams.set('profile', select.value);

            source.textContent = 'Membaca bandwidth dan parameter lanjutan dari profil…';

            try {
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                    signal: localController.signal
                });

                let data;
                try {
                    data = await response.json();
                } catch (_) {
                    throw new Error('Respons aplikasi tidak sesuai. Periksa sesi login.');
                }

                if (!response.ok) throw new Error(data.message || 'Profil gagal dibaca.');
                if (current !== generation) return;

                raw.value = data.rate_limit;
                download.value = data.download_mbps;
                upload.value = data.upload_mbps;
                renderAdvanced();

                source.textContent =
                    `Diambil dari ${data.router} / ${data.profile}. ` +
                    'Isian paket di form diperbarui, belum disimpan. ' +
                    'Jika memilih profil router lain, pilihan terakhir menjadi sumber nilai paket.';
            } catch (error) {
                if (current !== generation) return;
                source.textContent = error.name === 'AbortError'
                    ? 'Pembacaan terlalu lama. Isian paket tidak diubah.'
                    : `${error.message} Isian paket tidak diubah.`;
            } finally {
                clearTimeout(timeout);
            }
        });
    });

    function open(dialog, trigger) {
        origin = trigger;
        dialog.showModal();
        document.body.classList.add('pkg-dialog-open');
    }

    [info, chooser].forEach(dialog => {
        dialog.addEventListener('close', () => {
            document.body.classList.remove('pkg-dialog-open');
            origin?.focus();
        });
    });

    document.querySelectorAll('[data-info-close]').forEach(button => {
        button.addEventListener('click', () => info.close());
    });

    document.getElementById('pkg-router-choice-cancel').addEventListener('click', () => chooser.close());

    document.querySelectorAll('.pkg-view').forEach(button => {
        button.addEventListener('click', () => {
            const data = JSON.parse(button.dataset.package);
            const rows = [
                ['Nama paket', data.name],
                ['Harga bulanan', new Intl.NumberFormat('id-ID', {
                    style: 'currency', currency: 'IDR', maximumFractionDigits: 0
                }).format(data.monthly_price)],
                ['Jenis layanan', data.service_type === 'pppoe' ? 'PPPoE' : 'IP statis'],
                ['Status paket', data.is_active ? 'Aktif' : 'Nonaktif'],
                ['Download', data.download_mbps == null ? 'Belum diatur' :
                    Number(data.download_mbps) === 0 ? 'Tanpa batas' : `${Number(data.download_mbps)} Mbps`],
                ['Upload', data.upload_mbps == null ? 'Belum diatur' :
                    Number(data.upload_mbps) === 0 ? 'Tanpa batas' : `${Number(data.upload_mbps)} Mbps`],
                ...details(data.rate_limit)
            ];

            Object.values(data.mappings || {}).forEach(mapping => {
                rows.push([`Router: ${mapping.router_name}`, mapping.profile]);
            });

            rows.push(['Keterangan', 'Informasi tersimpan di aplikasi, bukan pemeriksaan langsung ke router.']);
            renderRows(document.getElementById('pkg-info-body'), rows);
            open(info, button);
        });
    });

    document.querySelectorAll('.pkg-push-card').forEach(button => {
        button.addEventListener('click', () => {
            const card = button.closest('.pkg-card');
            const targets = [...card.querySelectorAll('.pkg-sync-preview')];

            if (targets.length === 1) {
                targets[0].click();
                return;
            }

            const data = JSON.parse(card.querySelector('.pkg-edit').dataset.package);
            const mappings = Object.values(data.mappings || {});
            const holder = document.getElementById('pkg-router-choice-buttons');
            holder.replaceChildren();

            targets.forEach((target, index) => {
                const choice = document.createElement('button');
                choice.type = 'button';
                choice.className = 'pkg-button';
                choice.textContent = `${mappings[index]?.router_name || 'Router'} / ${mappings[index]?.profile || ''}`;
                choice.addEventListener('click', () => {
                    chooser.close();
                    target.click();
                });
                holder.append(choice);
            });

            if (!targets.length) {
                const message = document.createElement('p');
                message.textContent = 'Paket belum memiliki pemetaan router.';
                holder.append(message);
            }

            open(chooser, button);
        });
    });

    renderAdvanced();
})();
