(() => {
    const dialog = document.getElementById('cu-pppoe-dialog');
    const frame = document.getElementById('cu-pppoe-frame');
    if (!dialog || !frame) return;

    function close() {
        if (!window.confirm('Tutup popup import? Pilihan yang belum diimpor tidak disimpan.')) return;
        window.location.reload();
    }

    document.getElementById('cpi-close').addEventListener('click', close);

    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        close();
    });

    document.addEventListener('click', event => {
        const link = event.target.closest('[data-cu-pppoe-open]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        if (dialog.open) return;

        document.getElementById('cu-tools')?.removeAttribute('open');
        frame.src = link.href;
        dialog.showModal();
    });

    frame.addEventListener('load', () => {
        try {
            const current = new URL(frame.contentWindow.location.href);
            const customers = new URL(dialog.dataset.indexUrl, window.location.origin);

            if (dialog.open
                && current.origin === customers.origin
                && current.pathname === customers.pathname) {
                window.location.reload();
            }
        } catch (_) {}
    });
})();
