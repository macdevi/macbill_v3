(() => {
    const modal = document.getElementById('router-modal');
    const form = document.getElementById('router-config-form');
    const deleteModal = document.getElementById('router-delete-modal');
    const deleteForm = document.getElementById('router-delete-form');

    if (!modal || !form || !deleteModal || !deleteForm) return;

    const field = name => form.elements.namedItem(name);
    const password = field('password');
    const tls = form.querySelector('input[type="checkbox"][name="use_tls"]');
    const title = document.getElementById('router-modal-title');
    const passwordHelp = document.getElementById('router-password-help');
    let returnFocus = null;

    function open(dialog, trigger) {
        returnFocus = trigger || document.activeElement;
        dialog.showModal();
        document.body.classList.add('router-dialog-open');
    }

    [modal, deleteModal].forEach(dialog => {
        dialog.querySelectorAll('[data-close-dialog]').forEach(button => {
            button.addEventListener('click', () => dialog.close());
        });

        dialog.addEventListener('click', event => {
            if (event.target !== dialog) return;
            const rect = dialog.getBoundingClientRect();
            if (
                event.clientX < rect.left || event.clientX > rect.right ||
                event.clientY < rect.top || event.clientY > rect.bottom
            ) dialog.close();
        });

        dialog.addEventListener('close', () => {
            document.body.classList.remove('router-dialog-open');
            password.value = '';
            if (returnFocus?.isConnected) returnFocus.focus();
        });
    });

    function prepare(router, url) {
        const editing = Boolean(router);
        form.reset();
        form.action = url;
        field('_method').value = editing ? 'PUT' : 'POST';
        field('_router_id').value = editing ? router.id : '';

        for (const name of ['name', 'host', 'port', 'username', 'wan_interface']) {
            field(name).value = router?.[name] ?? '';
        }

        tls.checked = Boolean(router?.use_tls);
        password.value = '';
        password.required = !editing;
        title.textContent = editing ? 'Edit router' : 'Tambah router';
        if (passwordHelp) {
            passwordHelp.textContent = editing ? '(kosongkan jika tidak diganti)' : '';
        }

        form.querySelectorAll('button[type="submit"]').forEach(button => {
            button.disabled = false;
            button.textContent = 'Simpan konfigurasi';
        });
    }

    document.getElementById('router-add').addEventListener('click', event => {
        prepare(null, form.dataset.storeUrl);
        open(modal, event.currentTarget);
        field('name').focus();
    });

    document.querySelectorAll('.router-edit').forEach(button => {
        button.addEventListener('click', () => {
            const router = JSON.parse(button.dataset.router);
            prepare(router, button.dataset.updateUrl);
            open(modal, button);
            field('name').focus();
        });
    });

    document.querySelectorAll('.router-delete').forEach(button => {
        button.addEventListener('click', () => {
            deleteForm.action = button.dataset.deleteUrl;
            document.getElementById('router-delete-name').textContent =
                button.dataset.routerName;
            const submit = deleteForm.querySelector('button[type="submit"]');
            submit.disabled = false;
            submit.textContent = 'Ya, hapus konfigurasi';
            open(deleteModal, button);
            deleteModal.querySelector('[data-close-dialog][autofocus]').focus();
        });
    });

    form.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        button.textContent = 'Menyimpan…';
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

    document.querySelectorAll('.router-test-form').forEach(testForm => {
        testForm.addEventListener('submit', () => {
            const button = testForm.querySelector('button');
            button.disabled = true;
            button.textContent = 'Memeriksa…';
            testForm.setAttribute('aria-busy', 'true');
        });
    });

    if (form.dataset.autoOpen === '1') open(modal);
})();
