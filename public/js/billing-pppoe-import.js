(() => {
    const LIMIT = 500;
    const picks = [...document.querySelectorAll('.pi-pick')];
    const master = document.getElementById('pi-pick-all');
    let submitting = false;
    let packedFields = [];

    const eligible = () => picks.filter(pick => !pick.disabled);
    const selected = () => eligible().filter(pick => pick.checked);

    function sync() {
        const available = eligible();
        const chosen = selected();
        const count = chosen.length;

        picks.forEach(pick => {
            const active = pick.checked && !pick.disabled;
            pick.closest('.pi-row').querySelectorAll('.pi-editable')
                .forEach(field => field.disabled = !active);
        });

        const counter = document.getElementById('pi-count');
        if (counter) counter.textContent = String(count);

        if (master) {
            master.disabled = submitting || available.length === 0;
            master.checked = available.length > 0 && count === available.length;
            master.indeterminate = count > 0 && count < available.length;
        }

        const submit = document.getElementById('pi-import');
        if (submit) {
            submit.disabled = submitting || count === 0 || count > LIMIT;
            submit.textContent = submitting
                ? 'Memproses…' : `Import ${count} pelanggan`;
        }

        return count;
    }

    picks.forEach(pick => pick.addEventListener('change', () => {
        if (selected().length > LIMIT) {
            pick.checked = false;
            window.alert('Maksimal 500 pelanggan sekali impor.');
        }
        sync();
    }));

    master?.addEventListener('change', () => {
        const available = eligible();
        const clear = selected().length >= Math.min(LIMIT, available.length);

        available.forEach((pick, index) => {
            pick.checked = !clear && index < LIMIT;
        });

        sync();
    });

    document.querySelectorAll('.pi-submit').forEach(form => {
        form.addEventListener('submit', event => {
            if (submitting) {
                event.preventDefault();
                return;
            }

            if (form.querySelector('#pi-import')) {
                const count = sync();

                if (count < 1 || count > LIMIT) {
                    event.preventDefault();
                    return;
                }

                const items = {};

                for (const pick of selected()) {
                    const match = pick.name.match(/^items\[(\d+)\]\[selected\]$/);
                    const row = pick.closest('.pi-row');
                    const name = row.querySelector('input.pi-editable');
                    const packageField = row.querySelector('select.pi-editable');

                    if (!match || !name || !packageField) {
                        event.preventDefault();
                        window.alert('Data baris belum lengkap. Muat ulang preview.');
                        return;
                    }

                    items[match[1]] = {
                        selected: '1',
                        name: name.value,
                        package_id: packageField.value,
                    };
                }

                let payload = form.querySelector('input[name="selection_payload"]');

                if (!payload) {
                    payload = document.createElement('input');
                    payload.type = 'hidden';
                    payload.name = 'selection_payload';
                    form.append(payload);
                }

                payload.value = JSON.stringify(items);

                packedFields = [...form.querySelectorAll('[name^="items["]')]
                    .map(field => [field, field.disabled]);

                packedFields.forEach(([field]) => field.disabled = true);
            }

            submitting = true;
            if (master) master.disabled = true;

            const button = form.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.textContent = 'Memproses…';
            }
        });
    });

    window.addEventListener('pageshow', () => {
        submitting = false;

        packedFields.forEach(([field, disabled]) => field.disabled = disabled);
        packedFields = [];

        document.querySelectorAll('.pi-submit button[type="submit"]')
            .forEach(button => button.disabled = false);

        sync();
    });

    sync();
})();
