(() => {
    const form = document.getElementById('cu-form');
    if (!form) return;
    const modal = document.getElementById('cu-modal');
    const info = document.getElementById('cu-info-modal');
    const data = document.getElementById('cu-data');
    const packages = JSON.parse(data.dataset.packages);
    const field = name => form.elements.namedItem(name);

    const routerWilayah = JSON.parse(data.dataset.routerWilayah || '{}');
    const enforced = data.dataset.enforced === '1';
    const routerOptions = Array.from(field('network_router_id').options)
        .filter(option => option.value)
        .map(option => ({ id: option.value, name: option.textContent }));

    function filterRouters(selected = field('network_router_id').value) {
        const area = field('wilayah_id').value;
        const select = field('network_router_id');
        select.replaceChildren(
            new Option(enforced && !area ? 'Pilih wilayah dahulu' : 'Pilih router', '')
        );

        routerOptions
            .filter(router => !enforced || (
                area && (routerWilayah[router.id] || []).map(String).includes(area)
            ))
            .forEach(router => select.add(new Option(router.name, router.id)));

        const wanted = String(selected ?? '');
        if (wanted && !Array.from(select.options).some(option => option.value === wanted)) {
            const current = routerOptions.find(router => router.id === wanted);
            if (current) select.add(new Option(current.name, current.id));
        }
        select.value = wanted;
    }
    let origin = null;
    let original = {};

    function filterPackages(selected = field('internet_package_id').value) {
        const type = field('service_type').value;
        const router = field('network_router_id').value;
        const select = field('internet_package_id');
        select.replaceChildren(new Option('Pilih paket', ''));

        packages.filter(p =>
            p.type === type &&
            (p.active || String(p.id) === String(original.internet_package_id)) &&
            (type === 'static' || p.routers.some(id => String(id) === router))
        ).forEach(p => {
            select.add(new Option(p.name + (p.active ? '' : ' · Nonaktif'), p.id));
        });
        select.value = selected;

        const pppoe = type === 'pppoe';
        document.getElementById('cu-pppoe').hidden = !pppoe;
        document.getElementById('cu-static').hidden = pppoe;
        field('pppoe_username').required = pppoe;
        field('pppoe_username').disabled = !pppoe;
        field('pppoe_password').disabled = !pppoe;
        field('static_ip').required = !pppoe;
        field('static_ip').disabled = pppoe;
        updatePricing();
    }

    function prepare(customer = {}, url = form.dataset.store) {
        form.reset();
        original = customer;
        form.action = url;
        field('_method').value = customer.id ? 'PUT' : 'POST';
        field('_customer_id').value = customer.id || '';

        for (const name of [
            'name', 'whatsapp', 'address', 'wilayah_id',
            'pppoe_username', 'static_ip', 'invoice_day', 'notes'
        ]) field(name).value = customer[name] ?? '';

        const areaSelect = field('wilayah_id');
        if (!areaSelect.value && areaSelect.options.length === 2) {
            areaSelect.selectedIndex = 1;
        }
        filterRouters(customer.network_router_id ?? '');

        field('service_type').value = customer.service_type || 'pppoe';
        field('service_status').value = customer.service_status || 'active';
        field('started_at').value = customer.started_at || data.dataset.today;
        field('pppoe_password').value = '';
        field('price_mode').value = customer.price_mode || 'package';
        field('custom_monthly_price').value = customer.custom_monthly_price ?? '';
        field('tax_enabled').value = String(
            customer.tax_enabled ?? document.getElementById('cu-pricing-config').dataset.defaultTax
        );
        filterPackages(customer.internet_package_id || '');

        document.getElementById('cu-title').textContent =
            customer.id ? 'Edit pelanggan' : 'Tambah pelanggan';

        const submit = form.querySelector('button[type="submit"]');
        submit.disabled = false;
        submit.textContent = 'Simpan pelanggan';
    }

    function open(dialog, trigger) {
        origin = trigger || document.activeElement;
        if (dialog === info) info.cuReturnFocus = origin;
        dialog.showModal();
        document.body.classList.add('cu-dialog-open');
    }

    [modal, info].forEach(dialog => {
        dialog.querySelectorAll('[data-cu-close]').forEach(button => {
            button.addEventListener('click', () => dialog.close());
        });
        dialog.addEventListener('close', () => {
            if (dialog === modal) field('pppoe_password').value = '';

            if (document.querySelector('dialog[open]')) {
                document.body.classList.add('cu-dialog-open');
                return;
            }

            document.body.classList.remove('cu-dialog-open');
            if (origin?.isConnected) origin.focus();
        });
    });

    function updatePricing() {
        const config = document.getElementById('cu-pricing-config');
        const custom = field('price_mode').value === 'custom';
        const input = field('custom_monthly_price');
        document.getElementById('cu-custom-price-wrap').hidden = !custom;
        input.disabled = !custom;
        input.required = custom;

        const selected = packages.find(
            p => String(p.id) === field('internet_package_id').value
        );
        const packagePrice = selected ? Number(selected.price) : 0;
        const raw = custom ? Number(input.value || 0) : packagePrice;
        const price = Number.isFinite(raw) && raw >= 0 ? Math.trunc(raw) : 0;
        const taxable = field('tax_enabled').value === '1';
        const rate = taxable ? Number(config.dataset.rate) : 0;
        const include = config.dataset.mode === 'include';
        const denominator = include ? 10000 + rate : 10000;
        const tax = Math.floor(
            (price * rate + Math.floor(denominator / 2)) / denominator
        );
        const net = include ? price - tax : price;
        const total = include ? price : price + tax;
        const rupiah = value => 'Rp' + new Intl.NumberFormat('id-ID').format(value);

        document.getElementById('cu-package-price').textContent =
            selected ? rupiah(packagePrice) : 'Pilih paket dahulu';
        document.getElementById('cu-net-price').textContent = rupiah(net);
        document.getElementById('cu-tax-label').textContent =
            config.dataset.name + (taxable
                ? ' ' + (rate / 100) + '% · ' + (include ? 'Include' : 'Exclude')
                : ' · Tidak dikenakan');
        document.getElementById('cu-tax-amount').textContent = rupiah(tax);
        document.getElementById('cu-total-price').textContent = rupiah(total);
    }

    ['price_mode', 'tax_enabled', 'internet_package_id'].forEach(name => {
        field(name).addEventListener('change', updatePricing);
    });
    field('custom_monthly_price').addEventListener('input', updatePricing);

    field('service_type').addEventListener('change', () => filterPackages(''));
    field('network_router_id').addEventListener('change', () => filterPackages(''));
    field('wilayah_id').addEventListener('change', () => {
        filterRouters('');
        filterPackages('');
    });

    document.getElementById('cu-add').addEventListener('click', event => {
        prepare();
        open(modal, event.currentTarget);
        field('name').focus();
    });

    document.addEventListener('click', event => {
        if (!(event.target instanceof Element)) return;

        const edit = event.target.closest('.cu-edit');

        if (edit && !edit.disabled) {
            const customer = JSON.parse(edit.dataset.customer);
            const trigger = info.open
                ? (info.cuReturnFocus || edit)
                : edit;

            if (info.open) info.close();

            prepare(customer, edit.dataset.url);
            open(modal, trigger);
            field('name').focus();
            return;
        }

        const button = event.target.closest('.cu-view');

        if (button && !button.disabled && !info.open) {
            const template = document.getElementById(button.dataset.template);
            if (!(template instanceof HTMLTemplateElement)) return;

            document.getElementById('cu-info-body').replaceChildren(
                template.content.cloneNode(true)
            );
            open(info, button);
        }
    });


    form.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        button.textContent = 'Menyimpan…';
    });

    const initial = JSON.parse(data.dataset.initial);
    if (initial.open) {
        const url = form.action;
        prepare(initial, url);
        open(modal);
    }
})();
