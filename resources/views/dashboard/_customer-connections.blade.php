<link rel="stylesheet"
    href="{{ asset('css/billing-dashboard-connections.css') }}?v={{ filemtime(public_path('css/billing-dashboard-connections.css')) }}">
<link rel="stylesheet"
    href="{{ asset('css/billing-dashboard-status-card.css') }}?v={{ filemtime(public_path('css/billing-dashboard-status-card.css')) }}">

<section class="sc-card"
    id="dashboard-customer-connections"
    aria-label="Ringkasan pelanggan dan koneksi"
    data-endpoint="{{ route('dashboard.customer-status', ['router' => 0]) }}">

    <div class="sc-top">
        <h2 class="sc-title">Status Pelanggan</h2>
    </div>

    <div class="sc-split">
        <div class="sc-total" data-dc-detail="subscribed" role="button" tabindex="0" aria-haspopup="dialog" aria-controls="dc-detail-dialog" aria-label="Lihat detail pelanggan Berlangganan">
            <strong title="Total pelanggan terdaftar di billing">
                {{ number_format($connectionContext['total'], 0, ',', '.') }}
            </strong>
            <span>Total Pelanggan</span>
        </div>

        <div class="sc-ring" id="dc-ring" aria-hidden="true">
            <div><b id="dc-ring-pct">—</b><small>ONLINE</small></div>
        </div>
    </div>

    <div class="sc-chips">
        <div class="sc-chip sc-online" data-dc-detail="online" role="button" tabindex="0" aria-haspopup="dialog" aria-controls="dc-detail-dialog" aria-label="Lihat detail pelanggan Online">
            <span class="sc-name"><span class="sc-icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M2 8.8a16 16 0 0 1 20 0"/><path d="M5 12a11 11 0 0 1 14 0"/><path d="M8.5 15.3a5.5 5.5 0 0 1 7 0"/><circle cx="12" cy="19" r="1" fill="currentColor" stroke="none"/></svg></span>Online</span>
            <strong id="dc-online">—</strong>
            <em>Terhubung sekarang</em>
        </div>

        <div class="sc-chip sc-offline" data-dc-detail="offline" role="button" tabindex="0" aria-haspopup="dialog" aria-controls="dc-detail-dialog" aria-label="Lihat detail pelanggan Offline">
            <span class="sc-name"><span class="sc-icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="m3 3 18 18"/><path d="M8.6 5.4A16 16 0 0 1 22 8.8"/><path d="M2 8.8a16 16 0 0 1 3.2-2"/><path d="M12 9.5a11 11 0 0 1 7 2.5"/><path d="M5 12a11 11 0 0 1 2.2-1.3"/><path d="M8.5 15.3a5.5 5.5 0 0 1 7 0"/><circle cx="12" cy="19" r="1" fill="currentColor" stroke="none"/></svg></span>Offline</span>
            <strong id="dc-offline">—</strong>
            <em>Tidak terhubung</em>
        </div>

        <div class="sc-chip sc-isolir" data-dc-detail="isolated" role="button" tabindex="0" aria-haspopup="dialog" aria-controls="dc-detail-dialog" aria-label="Lihat detail pelanggan Isolir">
            <span class="sc-name"><span class="sc-icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M7 11V7a5 5 0 0 1 10 0v4"/><rect x="4" y="11" width="16" height="11" rx="3"/><circle cx="12" cy="16" r="1"/><path d="M12 17v2"/></svg></span>Isolir</span>
            <strong title="Jeda isolir billing terbuka, tanpa tindakan jaringan yang belum pasti">
                {{ $summary['customers']['isolated'] === null
                    ? '—'
                    : number_format($summary['customers']['isolated'], 0, ',', '.') }}
            </strong>
            <em>Layanan dibatasi</em>
        </div>
    </div>

    <p id="dc-note" class="sc-note" role="status" aria-live="polite">Memeriksa koneksi…</p>
</section>

<dialog id="dc-detail-dialog" class="dc-detail-modal"
    aria-labelledby="dc-detail-title" data-endpoint="{{ route('dashboard.customer-details') }}">
    <header class="dc-detail-header">
        <div>
            <h2 id="dc-detail-title">Detail Pelanggan</h2>
            <p id="dc-detail-info" role="status" aria-live="polite"></p>
        </div>
        <button type="button" id="dc-detail-close" aria-label="Tutup detail pelanggan">Tutup ×</button>
    </header>
    <div class="dc-detail-tools">
        <label for="dc-detail-search">Cari pelanggan</label>
        <input id="dc-detail-search" type="search" maxlength="100"
            placeholder="Nama, kode PLG, atau username PPPoE" autocomplete="off">
    </div>
    <div class="dc-detail-table-wrap" tabindex="0" role="region" aria-label="Daftar pelanggan">
        <table class="dc-detail-table">
            <caption class="dc-detail-sr">Daftar pelanggan sesuai kategori yang dipilih</caption>
            <thead><tr><th scope="col">Pelanggan</th><th scope="col">Layanan</th><th scope="col">Username PPPoE</th></tr></thead>
            <tbody id="dc-detail-body"></tbody>
        </table>
    </div>
    <footer class="dc-detail-footer">
        <span id="dc-detail-page" aria-live="polite"></span>
        <div><button type="button" id="dc-detail-prev">Sebelumnya</button>
        <button type="button" id="dc-detail-next">Berikutnya</button></div>
    </footer>
</dialog>

<script type="application/json" id="dc-context">@json($connectionContext)</script>
<script src="{{ asset('js/billing-dashboard-connections.js') }}?v={{ filemtime(public_path('js/billing-dashboard-connections.js')) }}" defer></script>
<script src="{{ asset('js/billing-dashboard-connections-ring.js') }}?v={{ filemtime(public_path('js/billing-dashboard-connections-ring.js')) }}" defer></script>
