<link rel="stylesheet"
    href="{{ asset('css/billing-customer-bulk-prices.css') }}?v={{ filemtime(public_path('css/billing-customer-bulk-prices.css')) }}">

<dialog id="cu-bulk-prices"
    class="cbp-dialog"
    aria-labelledby="cbp-title"
    data-read-url="{{ route('customers.bulk-prices.index') }}"
    data-save-url="{{ route('customers.bulk-prices.update') }}">
    <form id="cbp-form">
        <header class="cbp-header">
            <div>
                <h2 id="cbp-title">Edit nominal massal</h2>
                <p id="cbp-scope">Semua pelanggan · urutan nama A–Z</p>
            </div>
            <button type="button" class="cu-button" data-cbp-close aria-label="Tutup">Tutup</button>
        </header>

        <p class="cbp-help">
            Harga khusus kosong = mengikuti harga paket. Isi 0 berarti Rp0.
            Pajak tetap mengikuti pengaturan include/exclude yang berlaku.
            Invoice yang sudah terbit tidak berubah.
        </p>

        <div id="cbp-message" class="cbp-message" role="status" aria-live="polite"></div>

        <div class="cbp-table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Nama pelanggan</th>
                        <th scope="col">Harga paket / bulan</th>
                        <th scope="col">Harga khusus / bulan</th>
                    </tr>
                </thead>
                <tbody id="cbp-rows"></tbody>
            </table>
        </div>

        <footer class="cbp-footer">
            <span>Hanya baris yang berubah yang disimpan.</span>
            <div>
                <button type="button" class="cu-button" id="cbp-reload" hidden>Muat ulang halaman</button>
                <button type="button" class="cu-button" data-cbp-close>Batal</button>
                <button type="submit" class="cu-button cu-primary" id="cbp-save" disabled>Simpan 0 perubahan</button>
            </div>
        </footer>
    </form>
</dialog>

<script src="{{ asset('js/billing-customer-bulk-prices.js') }}?v={{ filemtime(public_path('js/billing-customer-bulk-prices.js')) }}" defer></script>
