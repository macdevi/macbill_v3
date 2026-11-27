<link rel="stylesheet"
    href="{{ asset('css/billing-pppoe-import.css') }}?v={{ filemtime(public_path('css/billing-pppoe-import.css')) }}">

<dialog id="cu-pppoe-dialog" class="cpi-dialog"
    aria-labelledby="cpi-title"
    data-index-url="{{ route('customers.index') }}">
    <div class="cpi-shell">
        <header class="cpi-heading">
            <h2 id="cpi-title">Import pelanggan dari MikroTik</h2>
            <button type="button" class="cu-button" id="cpi-close">Tutup</button>
        </header>
        <iframe id="cu-pppoe-frame" title="Form import pelanggan dari MikroTik"></iframe>
    </div>
</dialog>

<script src="{{ asset('js/billing-pppoe-import-dialog.js') }}?v={{ filemtime(public_path('js/billing-pppoe-import-dialog.js')) }}" defer></script>
