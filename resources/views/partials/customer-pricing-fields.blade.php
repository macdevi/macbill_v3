<section style="margin-top:20px;padding-top:5px;border-top:1px solid var(--ui-line)"
    aria-labelledby="cu-pricing-title">
    <h3 id="cu-pricing-title" style="font-size:14px;margin-bottom:0">Penagihan</h3>

    <div class="cu-form-grid">
        <div>
            <label for="cu-price-mode">Sumber harga</label>
            <select id="cu-price-mode" name="price_mode" required>
                <option value="package">Ikuti harga paket</option>
                <option value="custom">Harga khusus</option>
            </select>
        </div>
        <div>
            <label for="cu-tax-enabled">Pajak</label>
            <select id="cu-tax-enabled" name="tax_enabled" required>
                <option value="0">Tidak dikenakan</option>
                <option value="1">Ikuti pengaturan pajak</option>
            </select>
        </div>
    </div>

    <p class="cu-help">Harga paket: <span id="cu-package-price">Pilih paket dahulu</span></p>

    <div id="cu-custom-price-wrap" hidden>
        <label for="cu-custom-price">Harga khusus per bulan (Rp)</label>
        <input id="cu-custom-price" name="custom_monthly_price" type="number"
            min="0" max="1000000000" step="1" inputmode="numeric" disabled>
    </div>

    <div style="padding:12px 14px;margin-top:14px;border:1px solid var(--ui-line);border-radius:10px;background:var(--ui-surface-soft);font-size:12px;line-height:1.9"
        aria-live="polite" aria-atomic="true">
        <div style="display:flex;justify-content:space-between;gap:12px">
            <span>Sebelum pajak</span><span id="cu-net-price">Rp0</span>
        </div>
        <div style="display:flex;justify-content:space-between;gap:12px">
            <span id="cu-tax-label">Pajak</span><span id="cu-tax-amount">Rp0</span>
        </div>
        <div style="display:flex;justify-content:space-between;gap:12px;border-top:1px solid var(--ui-line);margin-top:7px;padding-top:7px">
            <span>Total bulanan</span><strong id="cu-total-price">Rp0</strong>
        </div>
    </div>
    <p class="cu-help">Estimasi bulanan. Harga khusus tidak mengubah paket atau profil MikroTik.</p>
</section>

<div id="cu-pricing-config" hidden
    data-name="{{ $settings->tax_name }}"
    data-rate="{{ $settings->tax_rate_bps }}"
    data-mode="{{ $settings->tax_mode }}"
    data-default-tax="{{ (int) $settings->default_tax_enabled }}"></div>
