(() => {
    const picks = [...document.querySelectorAll('.ei-pick:not(:disabled)')];
    const all = document.getElementById('ei-all');
    const save = document.getElementById('ei-save');
    const counter = document.getElementById('ei-count');

    function sync() {
        const count = picks.filter(pick => pick.checked).length;
        if (counter) counter.textContent = String(count);
        if (save) save.disabled = count === 0;
        if (all) {
            all.disabled = picks.length === 0;
            all.checked = picks.length > 0 && count === picks.length;
            all.indeterminate = count > 0 && count < picks.length;
        }
    }

    all?.addEventListener('change', () => {
        picks.forEach(pick => pick.checked = all.checked);
        sync();
    });
    picks.forEach(pick => pick.addEventListener('change', sync));
    sync();

    document.querySelectorAll('.ei-submit').forEach(form => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.textContent = 'Memproses...';
            }
        });
    });
})();
