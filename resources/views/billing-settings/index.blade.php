<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1425">
    <title>Pengaturan | Tagihan dan Management Jaringan</title>

    @foreach([
        'billing-settings',
        'billing-theme',
        'billing-page-header',
        'billing-launcher',
        'billing-network-header',
    ] as $stylesheet)
        <link rel="stylesheet"
            href="{{ asset('css/'.$stylesheet.'.css') }}?v={{ filemtime(public_path('css/'.$stylesheet.'.css')) }}">
    @endforeach

    <script src="{{ asset('js/billing-theme.js') }}?v={{ filemtime(public_path('js/billing-theme.js')) }}"></script>
</head>
<body>
    @include('partials.billing-page-header', ['headerTitle' => 'Pengaturan'])

    <main class="settings-page">
        <div class="settings-heading">
            <span class="settings-eyebrow">PREFERENSI BILLING</span>
            <h1>Pengaturan</h1>
            <p>Atur jatuh tempo bawaan dan kebijakan isolir layanan.</p>
        </div>

        @if(session('status'))
            <div class="settings-notice" role="status">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="settings-error" role="alert">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('billing-settings.update') }}">
            @csrf
            @method('PUT')

            <section class="settings-section" aria-labelledby="billing-heading">
                <div class="settings-section-heading">
                    <span class="settings-number" aria-hidden="true">01</span>
                    <div>
                        <h2 id="billing-heading">Penagihan</h2>
                        <p>Nilai bawaan untuk pelanggan yang mengikuti pengaturan bersama.</p>
                    </div>
                </div>

                <div class="settings-field" style="margin-top:18px">
                    <label for="default-invoice-day">Tanggal tagihan bawaan</label>
                    <select id="default-invoice-day" name="default_invoice_day" required>
                        <option value="">Pilih tanggal</option>
                        @for($day = 1; $day <= 31; $day++)
                            <option value="{{ $day }}"
                                @selected((string) old('default_invoice_day', $settings->default_invoice_day) === (string) $day)>
                                Tanggal {{ $day }}
                            </option>
                        @endfor
                    </select>
                    <p class="settings-help">Tanggal penerbitan invoice setiap bulan. Invoice menagih pemakaian periode sebelumnya.</p>
                </div>

                <div class="settings-field" style="margin-top:18px">
                    <label for="due-grace-days">Tenggang jatuh tempo (hari)</label>
                    <input id="due-grace-days" name="due_grace_days" type="number"
                        min="0" max="60" step="1" required
                        value="{{ old('due_grace_days', $settings->due_grace_days ?? 0) }}">
                    <p class="settings-help">0 berarti jatuh tempo sama dengan tanggal terbit. Berlaku untuk invoice yang diterbitkan setelah ini.</p>
                </div>

            </section>

            <section class="settings-section" aria-labelledby="isolation-heading">
                <div class="settings-section-heading">
                    <span class="settings-number" aria-hidden="true">02</span>
                    <div>
                        <h2 id="isolation-heading">Isolir layanan</h2>
                        <p>Pengaturan ini tidak langsung menjalankan tindakan pada router.</p>
                    </div>
                </div>

                <div class="settings-mode">
                    <div>
                        <h3>Manual</h3>
                        <p>
                            Isolir dan pemulihan layanan nantinya dilakukan
                            melalui tindakan pelanggan dengan konfirmasi.
                        </p>
                    </div>
                    <span class="settings-mode-badge">Dipilih</span>
                </div>

                <input type="hidden" name="isolation_mode" value="manual">

                <div class="settings-mode settings-mode-unavailable">
                    <div>
                        <h3>Otomatis</h3>
                        <p>
                            Belum tersedia. Akan dibahas kembali setelah modul
                            tagihan dan tindakan isolir manual selesai diuji.
                        </p>
                    </div>
                    <button type="button" class="settings-switch" disabled
                        aria-label="Isolir otomatis belum tersedia">
                        <span aria-hidden="true"></span>
                    </button>
                </div>

                <p class="settings-help">
                    Masa tenggang belum diaktifkan karena mode otomatis belum tersedia.
                    Menyimpan pengaturan tidak mengisolir atau memulihkan pelanggan.
                </p>
            </section>

            <section class="settings-section" aria-labelledby="tax-heading">
                <div class="settings-section-heading">
                    <span class="settings-number" aria-hidden="true">03</span>
                    <div>
                        <h2 id="tax-heading">Pajak</h2>
                        <p>Digunakan untuk pelanggan yang mengikuti pengaturan pajak.</p>
                    </div>
                </div>

                <div class="settings-field">
                    <label for="tax-name">Nama pajak</label>
                    <input id="tax-name" name="tax_name" required maxlength="50"
                        value="{{ old('tax_name', $settings->tax_name) }}">
                </div>

                <div class="settings-field" style="margin-top:18px">
                    <label for="tax-rate">Tarif pajak (%)</label>
                    <input id="tax-rate" name="tax_rate" type="number"
                        min="0" max="100" step="0.01" required
                        value="{{ old('tax_rate', number_format($settings->tax_rate_bps / 100, 2, '.', '')) }}">
                </div>

                <div class="settings-field" style="margin-top:18px">
                    <label for="tax-mode">Metode perhitungan</label>
                    <select id="tax-mode" name="tax_mode" required>
                        <option value="include" @selected(old('tax_mode', $settings->tax_mode) === 'include')>Include · Termasuk harga</option>
                        <option value="exclude" @selected(old('tax_mode', $settings->tax_mode) === 'exclude')>Exclude · Ditambahkan ke harga</option>
                    </select>
                </div>

                <div class="settings-field" style="margin-top:18px">
                    <label for="default-tax">Default pajak pelanggan baru</label>
                    <select id="default-tax" name="default_tax_enabled" required>
                        <option value="0" @selected((string) old('default_tax_enabled', (int) $settings->default_tax_enabled) === '0')>Tidak dikenakan</option>
                        <option value="1" @selected((string) old('default_tax_enabled', (int) $settings->default_tax_enabled) === '1')>Ikuti pengaturan pajak</option>
                    </select>
                </div>
                <p class="settings-help">Perubahan default tidak mengubah pilihan pajak pelanggan lama.</p>
            </section>

            <section class="settings-section" aria-labelledby="wa-heading">
                <div class="settings-section-heading">
                    <span class="settings-number" aria-hidden="true">04</span>
                    <div>
                        <h2 id="wa-heading">Pesan WhatsApp</h2>
                        <p>Template pesan informasi tagihan untuk tombol WhatsApp.</p>
                    </div>
                </div>

                <div class="settings-field">
                    <label for="wa-template">Template pesan</label>
                    <textarea id="wa-template" name="wa_template" rows="11" maxlength="1500">{{ old('wa_template', $settings->wa_template ?: \App\Services\WhatsAppMessage::DEFAULT_TEMPLATE) }}</textarea>
                    <p class="settings-help">
                        Penanda yang tersedia:
                        @foreach(\App\Services\WhatsAppMessage::PLACEHOLDERS as $marker)
                            <code>{{ $marker }}</code>
                        @endforeach
                        . Kosongkan kolom ini lalu simpan untuk memakai template bawaan.
                        Jangan menuliskan password atau data rahasia di template.
                    </p>
                </div>
            </section>

            <section class="settings-section" aria-labelledby="generate-heading">
                <div class="settings-section-heading">
                    <span class="settings-number" aria-hidden="true">05</span>
                    <div>
                        <h2 id="generate-heading">Penerbitan tagihan</h2>
                        <p>Terbitkan invoice manual per bulan untuk pelanggan yang dipilih. Penerbitan otomatis belum dijadwalkan.</p>
                    </div>
                </div>

                <div class="settings-field" style="margin-top:18px">
                    <a class="settings-save" href="{{ route('invoices.create') }}"
                        style="display:inline-flex;align-items:center;justify-content:center;text-decoration:none">Generate tagihan</a>
                    <p class="settings-help">Halaman ini hanya membuka daftar pelanggan yang bisa ditagih. Invoice baru terbit setelah Anda memilih dan mengonfirmasi.</p>
                </div>
            </section>

            <section class="settings-section" aria-labelledby="receipt-heading">
                <div class="settings-section-heading">
                    <span class="settings-number" aria-hidden="true">06</span>
                    <div>
                        <h2 id="receipt-heading">Template nota</h2>
                        <p>Isi nota pembayaran untuk tampilan, WhatsApp, dan cetak.</p>
                    </div>
                </div>

                <div class="settings-field">
                    <label for="receipt-template">Template nota</label>
                    <textarea id="receipt-template" name="receipt_template" rows="16" maxlength="2000">{{ old('receipt_template', $settings->receipt_template ?: \App\Services\ReceiptText::DEFAULT_TEMPLATE) }}</textarea>
                    <p class="settings-help">
                        Penanda yang tersedia:
                        @foreach(\App\Services\ReceiptText::PLACEHOLDERS as $marker)
                            <code>{{ $marker }}</code>
                        @endforeach
                        . Tambahkan nama usaha, alamat, atau rekening langsung di teks template.
                        Baris yang berisi {sisa_tagihan}, {titip_saldo}, atau {saldo_titip} otomatis hilang jika nilainya nol. Kosongkan lalu simpan untuk memakai template bawaan.
                    </p>
                </div>
            </section>

            <div class="settings-save-row">
                <p>
                    @if($settings->default_due_day === null)
                        Jatuh tempo bawaan belum diatur.
                    @else
                        Tersimpan: tanggal {{ $settings->default_due_day }} setiap bulan.
                    @endif
                </p>
                <button class="settings-save" type="submit">Simpan pengaturan</button>
            </div>

            <p class="settings-footnote">
                Tahap ini menyimpan kebijakan sebagai fondasi modul Pelanggan dan Tagihan.
                Penerbitan tagihan serta eksekusi isolir belum dijalankan.
            </p>
        </form>

        <section class="settings-section" aria-labelledby="reset-heading">
            <div class="settings-section-heading">
                <span class="settings-number" aria-hidden="true">07</span>
                <div>
                    <h2 id="reset-heading">Reset data billing</h2>
                    <p>Kosongkan router, paket, pelanggan, tagihan, pembayaran, dan pengeluaran. Pengguna, wilayah, dan pengaturan di atas tidak diubah.</p>
                </div>
            </div>

            <table style="width:100%;margin-top:18px;border-collapse:collapse">
                <tbody>
                    @foreach(\App\Services\BillingReset::counts() as $table => $count)
                        <tr>
                            <td style="padding:6px 0"><code>{{ $table }}</code></td>
                            <td style="padding:6px 0;text-align:right">{{ number_format($count, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="settings-help">Hanya database aplikasi yang dikosongkan. Akun PPPoE di MikroTik tidak diubah. Tindakan ini tidak bisa dibatalkan, jadi cadangkan database dan catat data router serta paket lebih dulu.</p>

            @if(! config('billing_reset.enabled'))
                <p class="settings-help" style="margin-top:18px">
                    Fitur dimatikan. Set <code>BILLING_RESET_ENABLED=true</code> di <code>.env</code>,
                    jalankan <code>php artisan config:clear</code>, lalu muat ulang halaman ini.
                </p>
            @else
                <form method="POST" action="{{ route('data-reset.destroy') }}"
                    onsubmit="return confirm('Semua data billing akan dihapus permanen. Lanjutkan?')">
                    @csrf
                    @method('DELETE')

                    <div class="settings-field" style="margin-top:18px">
                        <label for="reset-confirmation">Ketik KOSONGKAN</label>
                        <input id="reset-confirmation" name="confirmation" type="text" required autocomplete="off">
                    </div>

                    <div class="settings-field" style="margin-top:18px">
                        <label for="reset-password">Kata sandi Anda</label>
                        <input id="reset-password" name="password" type="password" required autocomplete="current-password">
                    </div>

                    <div class="settings-save-row">
                        <p>Hanya super admin yang bisa melakukan ini.</p>
                        <button class="settings-save" type="submit" style="background:#b42318">Kosongkan semua data</button>
                    </div>
                </form>
            @endif
        </section>
    </main>

    @include('partials.billing-launcher')

    <script src="{{ asset('js/billing-launcher.js') }}?v={{ filemtime(public_path('js/billing-launcher.js')) }}" defer></script>
</body>
</html>
