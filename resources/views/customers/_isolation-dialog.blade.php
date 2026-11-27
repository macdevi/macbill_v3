@if($manualIsolationAvailable ?? false)
<dialog class="cu-modal" id="cu-isolation-modal" aria-labelledby="cu-isolation-title">
    <div class="cu-modal-inner">
        <div class="cu-modal-heading">
            <h2 id="cu-isolation-title">Konfirmasi tindakan</h2>
            <button type="button" class="cu-close"
                data-iso-close aria-label="Tutup">&times;</button>
        </div>

        <section id="cu-iso-feedback" hidden
            role="status" aria-live="polite" aria-atomic="true">
            <div id="cu-iso-feedback-icon" aria-hidden="true"></div>
            <p id="cu-iso-feedback-message"></p>
            <p id="cu-iso-feedback-reference" class="cu-help"></p>
            <div class="cu-actions cu-modal-actions">
                <button type="button" class="cu-button"
                    id="cu-iso-result-close" data-iso-close hidden>
                    Selesai
                </button>
            </div>
        </section>

        <form method="POST" id="cu-isolation-form">
            @csrf
            <input type="hidden" name="kind">
            <input type="hidden" name="request_key">
            <input type="hidden" name="version">
            <input type="hidden" name="pause_id">

            <dl class="cu-iso-summary">
                <div><dt>Pelanggan</dt><dd id="cu-iso-name"></dd></div>
                <div><dt>Kode</dt><dd id="cu-iso-code"></dd></div>
                <div><dt>Akun PPPoE</dt><dd id="cu-iso-account"></dd></div>
                <div><dt>Router</dt><dd id="cu-iso-router"></dd></div>
            </dl>

            <input type="hidden" name="reason"
                value="Tindakan manual dikonfirmasi melalui popup.">
            <input type="hidden" name="confirmed" value="1">

            <div class="cu-actions cu-modal-actions">
                <button type="button" class="cu-button" data-iso-close>Batal</button>
                <button type="submit" class="cu-button" id="cu-iso-submit">
                    Konfirmasi
                </button>
            </div>
        </form>
    </div>
</dialog>
<script src="{{ asset('js/billing-customer-isolation.js') }}?v={{ filemtime(public_path('js/billing-customer-isolation.js')) }}" defer></script>
@endif
