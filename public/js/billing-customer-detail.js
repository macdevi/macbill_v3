(() => {
    const body = document.getElementById('cu-info-body');
    const dialog = document.getElementById('cu-info-modal');
    if (!body) return;

    function formatTimes() {
        body.querySelectorAll('.cu-compact-local-time[datetime]').forEach(node => {
            const date = new Date(node.dateTime);
            if (!Number.isFinite(date.getTime())) return;

            node.textContent = new Intl.DateTimeFormat('id-ID', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }).format(date);
        });
    }

    function setPasswordVisible(container, visible) {
        const button = container.querySelector('.cu-password-toggle');
        const mask = container.querySelector('.cu-password-mask');
        const plain = container.querySelector('.cu-password-plain');
        const eye = container.querySelector('.cu-password-eye');
        const eyeOff = container.querySelector('.cu-password-eye-off');

        if (!button || !mask || !plain || !eye || !eyeOff) return;

        mask.hidden = visible;
        plain.hidden = !visible;
        eye.hidden = visible;
        eyeOff.hidden = !visible;

        button.setAttribute('aria-pressed', String(visible));
        button.setAttribute(
            'aria-label',
            visible ? 'Sembunyikan password PPPoE' : 'Tampilkan password PPPoE'
        );
        button.title = visible ? 'Sembunyikan password' : 'Tampilkan password';
    }

    body.addEventListener('click', event => {
        const button = event.target.closest('.cu-password-toggle');
        if (!button) return;

        const container = button.closest('.cu-compact-password');
        if (!container) return;

        setPasswordVisible(
            container,
            button.getAttribute('aria-pressed') !== 'true'
        );
    });

    new MutationObserver(formatTimes).observe(body, { childList: true });
    formatTimes();

    dialog?.addEventListener('close', () => {
        body.querySelectorAll('.cu-compact-password').forEach(container => {
            setPasswordVisible(container, false);
        });
    });
})();
