<dialog id="cu-delete-dialog" class="cu-delete-dialog" aria-labelledby="cu-del-title">
    <form method="POST" action="#">
        @csrf
        @method('DELETE')
        <input type="hidden" name="request_key" value="">
        <h2 id="cu-del-title">Hapus pelanggan <span data-del-name></span></h2>
        <p class="cu-del-note">Pelanggan tidak ditagih lagi dan hilang dari semua daftar. Tagihan, kuitansi, dan saldo tetap tersimpan dengan tanda "Pelanggan dihapus".</p>
        <label class="cu-del-option">
            <input type="radio" name="mode" value="billing" checked>
            <span><strong>Hapus dari billing saja</strong><br>Akun di MikroTik tetap ada dan tetap bisa online.</span>
        </label>
        <label class="cu-del-option">
            <input type="radio" name="mode" value="billing_router">
            <span><strong>Hapus dari billing dan MikroTik</strong><br>Akun PPPoE dihapus dari router dan sesi aktif diputus.</span>
        </label>
        <div data-del-userwrap hidden>
            <label>Ketik username <strong data-del-username></strong> untuk melanjutkan
                <input type="text" name="confirm_username" autocomplete="off" autocapitalize="off" spellcheck="false">
            </label>
        </div>
        <div class="cu-del-actions">
            <button type="button" data-del-cancel>Batal</button>
            <button type="submit" data-del-submit class="cu-danger">Hapus</button>
        </div>
    </form>
</dialog>
<style>
    .cu-delete-dialog { max-width: 460px; width: calc(100% - 32px); border-radius: 14px; padding: 18px; border: 1px solid rgba(128,128,128,.4); background: #111b2e; color: #e5edf8; }
    .cu-delete-dialog::backdrop { background: rgba(0,0,0,.55); }
    .cu-delete-dialog h2 { margin: 0 0 8px; font-size: 1.1rem; }
    .cu-del-note { margin: 0 0 12px; font-size: .9rem; opacity: .85; }
    .cu-del-option { display: flex; gap: 10px; align-items: flex-start; padding: 10px; margin-bottom: 8px; border: 1px solid rgba(128,128,128,.4); border-radius: 10px; font-size: .9rem; }
    .cu-del-option input:disabled + span { opacity: .45; }
    .cu-delete-dialog input[type=text] { width: 100%; box-sizing: border-box; margin-top: 6px; padding: 10px 12px; min-height: 44px; border-radius: 10px; border: 1px solid rgba(128,128,128,.5); background: rgba(128,128,128,.15); color: inherit; font: inherit; }
    .cu-del-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 14px; }
    .cu-del-actions button { font: inherit; min-height: 44px; padding: 8px 16px; border-radius: 10px; border: 1px solid rgba(128,128,128,.5); background: rgba(128,128,128,.18); color: inherit; cursor: pointer; }
    .cu-del-actions button:disabled { opacity: .5; cursor: not-allowed; }
    .cu-danger { color: #fecaca; border-color: #dc2626 !important; }
    .cu-delete-dialog .cu-danger[type=submit] { background: #b91c1c; color: #fff; }
    .cu-delete-dialog [hidden] { display: none !important; }
</style>
<script>
(function () {
    var dlg = document.getElementById('cu-delete-dialog');
    if (!dlg) return;
    var form = dlg.querySelector('form');
    var nameEl = dlg.querySelector('[data-del-name]');
    var key = form.querySelector('[name=request_key]');
    var routerRadio = form.querySelector('[name=mode][value=billing_router]');
    var billingRadio = form.querySelector('[name=mode][value=billing]');
    var userWrap = dlg.querySelector('[data-del-userwrap]');
    var userInput = form.querySelector('[name=confirm_username]');
    var userLabel = dlg.querySelector('[data-del-username]');
    var submit = form.querySelector('[data-del-submit]');
    var expected = '';

    function uuid() {
        var b = new Uint8Array(16);
        crypto.getRandomValues(b);
        b[6] = (b[6] & 15) | 64;
        b[8] = (b[8] & 63) | 128;
        var h = Array.prototype.map.call(b, function (x) {
            return ('0' + x.toString(16)).slice(-2);
        }).join('');
        return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
    }

    function sync() {
        var viaRouter = routerRadio.checked;
        userWrap.hidden = !viaRouter;
        submit.disabled = viaRouter && userInput.value !== expected;
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('[data-cu-delete]') : null;
        if (!btn) return;
        expected = btn.dataset.username || '';
        form.action = btn.dataset.url;
        nameEl.textContent = btn.dataset.name;
        userLabel.textContent = expected;
        key.value = uuid();
        userInput.value = '';
        routerRadio.disabled = !(btn.dataset.type === 'pppoe' && expected !== '');
        billingRadio.checked = true;
        submit.textContent = 'Hapus';
        sync();
        dlg.showModal();
    });

    billingRadio.addEventListener('change', sync);
    routerRadio.addEventListener('change', sync);
    userInput.addEventListener('input', sync);
    dlg.querySelector('[data-del-cancel]').addEventListener('click', function () { dlg.close(); });
    form.addEventListener('submit', function () {
        submit.disabled = true;
        submit.textContent = 'Memproses...';
    });
})();
</script>
