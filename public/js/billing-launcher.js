(() => {
    const dialog = document.getElementById('billing-launcher');
    if (!dialog) return;

    const triggers = [...document.querySelectorAll('[data-launcher-open]')];
    const closeButton = dialog.querySelector('[data-launcher-close]');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    let opener = null;
    let closing = false;
    let motion = null;

    function expanded(value) {
        triggers.forEach(button => button.setAttribute('aria-expanded', String(value)));
    }

    function open(event) {
        if (dialog.open || closing || document.querySelector('dialog[open]')) return;

        opener = event.currentTarget;
        dialog.showModal();
        document.body.classList.add('billing-launcher-open');
        expanded(true);

        if (!reduced.matches) {
            motion = dialog.animate([
                { opacity: 0, transform: 'scale(.96)' },
                { opacity: 1, transform: 'scale(1)' }
            ], {
                duration: 320,
                easing: 'cubic-bezier(.2,.75,.25,1)'
            });
        }

        requestAnimationFrame(() => {
            if (dialog.open && !closing) dialog.classList.add('is-visible');
        });

        closeButton.focus({ preventScroll: true });
    }

    async function close(destination = null) {
        if (!dialog.open || closing) return;
        closing = true;
        motion?.cancel();
        dialog.classList.remove('is-visible');

        if (!reduced.matches) {
            motion = dialog.animate([
                { opacity: 1, transform: 'scale(1)' },
                { opacity: 0, transform: 'scale(.96)' }
            ], {
                duration: 260,
                easing: 'cubic-bezier(.4,0,.6,1)',
                fill: 'forwards'
            });

            try {
                await motion.finished;
            } catch (_) {}
        }

        dialog.close();
        motion?.cancel();
        closing = false;

        if (destination) window.location.assign(destination);
    }

    triggers.forEach(button => button.addEventListener('click', open));
    closeButton.addEventListener('click', () => close());

    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        close();
    });

    dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const box = dialog.getBoundingClientRect();
        if (
            event.clientX < box.left || event.clientX > box.right ||
            event.clientY < box.top || event.clientY > box.bottom
        ) close();
    });

    dialog.querySelectorAll('a[href]').forEach(link => {
        link.addEventListener('click', event => {
            if (
                event.button !== 0 || event.ctrlKey || event.metaKey ||
                event.shiftKey || event.altKey
            ) return;

            event.preventDefault();
            close(link.href);
        });
    });

    dialog.addEventListener('close', () => {
        dialog.classList.remove('is-visible');
        document.body.classList.remove('billing-launcher-open');
        expanded(false);
        if (opener?.isConnected) opener.focus({ preventScroll: true });
    });
})();
