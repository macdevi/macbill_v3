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
            <p>Pilih menu di bawah untuk membuka pengaturannya.</p>
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

            <details class="settings-section settings-menu" {{ $errors->hasAny(['default_invoice_day', 'due_grace_days']) ? 'open' : '' }} aria-labelledby="billing-heading">
                <summary class="settings-menu-summary">
                    <div class="settings-section-heading">
                    <span class="settings-number" aria-hidden="true">01</span>
                    <div>
                        <h2 id="billing-heading">Penagihan</h2>
                        <p>Nilai bawaan untuk pelanggan yang mengikuti pengaturan bersama.</p>
                    </div>
                    </div>
                </summary>
                <div class="settings-menu-body">

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

            </div>
            </details>

            <details class="settings-section settings-menu" aria-labelledby="isolation-heading">
                <summary class="settings-menu-summary">
                    <div class="settings-section-heading">
                    <span class="settings-number" aria-hidden="true">02</span>
                    <div>
                        <h2 id="isolation-heading">Isolir layanan</h2>
                        <p>Pengaturan ini tidak langsung menjalankan tindakan pada router.</p>
                    </div>
                    </div>
                </summary>
                <div class="settings-menu-body">

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
            </div>
            </details>

            <details class="settings-section settings-menu" {{ $errors->hasAny(['tax_name', 'tax_rate', 'tax_mode', 'default_tax_enabled']) ? 'open' : '' }} aria-labelledby="tax-heading">
                <summary class="settings-menu-summary">
                    <div class="settings-section-heading">
                    <span class="settings-number" aria-hidden="true">03</span>
                    <div>
                        <h2 id="tax-heading">Pajak</h2>
                        <p>Digunakan untuk pelanggan yang mengikuti pengaturan pajak.</p>
                    </div>
                    </div>
                </summary>
                <div class="settings-menu-body">

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
            </div>
            </details>

            <details class="settings-section settings-menu" {{ $errors->hasAny(['wa_template']) ? 'open' : '' }} aria-labelledby="wa-heading">
                <summary class="settings-menu-summary">
                    <div class="settings-section-heading">
                    <span class="settings-number" aria-hidden="true">04</span>
                    <div>
                        <h2 id="wa-heading">Pesan WhatsApp</h2>
                        <p>Template pesan informasi tagihan untuk tombol WhatsApp.</p>
                    </div>
                    </div>
                </summary>
                <div class="settings-menu-body">

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
            </div>
            </details>

            <details class="settings-section settings-menu" aria-labelledby="generate-heading">
                <summary class="settings-menu-summary">
                    <div class="settings-section-heading">
                    <span class="settings-number" aria-hidden="true">05</span>
                    <div>
                        <h2 id="generate-heading">Penerbitan tagihan</h2>
                        <p>Terbitkan invoice manual per bulan untuk pelanggan yang dipilih. Penerbitan otomatis belum dijadwalkan.</p>
                    </div>
                    </div>
                </summary>
                <div class="settings-menu-body">

                <div class="settings-field" style="margin-top:18px">
                    <a class="settings-save settings-button-link" href="{{ route('invoices.create') }}">Generate tagihan</a>
                    <p class="settings-help">Halaman ini hanya membuka daftar pelanggan yang bisa ditagih. Invoice baru terbit setelah Anda memilih dan mengonfirmasi.</p>
                </div>
            </div>
            </details>

            <details class="settings-section settings-menu" {{ $errors->hasAny(['receipt_template']) ? 'open' : '' }} aria-labelledby="receipt-heading">
                <summary class="settings-menu-summary">
                    <div class="settings-section-heading">
                    <span class="settings-number" aria-hidden="true">06</span>
                    <div>
                        <h2 id="receipt-heading">Template nota</h2>
                        <p>Isi nota pembayaran untuk tampilan, WhatsApp, dan cetak.</p>
                    </div>
                    </div>
                </summary>
                <div class="settings-menu-body">

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
            </div>
            </details>

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

        @php($loginForm = \App\Services\LoginScreen::raw($settings->getAttribute('login_screen')))
        <form method="POST" action="{{ route('billing-settings.login-screen') }}" id="login-screen-form"
            data-name="{{ $settings->business_name }}"
            data-slug="{{ \Illuminate\Support\Str::slug((string) $settings->business_name) ?: 'macbilling' }}">
            @csrf
            @method('PUT')

            <details class="settings-section settings-menu" {{ $errors->hasAny(['login_command', 'login_title', 'login_tagline', 'login_modules', 'login_spinner', 'login_message', 'login_splash', 'login_rain', 'login_accent']) ? 'open' : '' }} aria-labelledby="login-screen-heading">
                <summary class="settings-menu-summary">
                    <div class="settings-section-heading">
                    <span class="settings-number" aria-hidden="true">07</span>
                    <div>
                        <h2 id="login-screen-heading">Tampilan login</h2>
                        <p>Teks layar pembuka dan terminal pada halaman masuk.</p>
                    </div>
                    </div>
                </summary>
                <div class="settings-menu-body">

                <p class="settings-help">
                    Penanda yang tersedia:
                    @foreach(\App\Services\LoginScreen::PLACEHOLDERS as $marker)
                        <code>{{ $marker }}</code>
                    @endforeach
                    . Nama usaha diubah di <a href="{{ route('business-profile.edit') }}">Profil Usaha</a>.
                </p>

                <div class="settings-field" style="margin-top:18px">
                    <label for="login-command">Perintah pembuka</label>
                    <input id="login-command" name="login_command" required maxlength="80"
                        value="{{ old('login_command', $loginForm['command']) }}">
                </div>

                <div class="settings-field" style="margin-top:18px">
                    <label for="login-title">Baris judul</label>
                    <input id="login-title" name="login_title" required maxlength="120"
                        value="{{ old('login_title', $loginForm['title']) }}">
                </div>

                <div class="settings-field" style="margin-top:18px">
                    <label for="login-tagline">Tagline</label>
                    <input id="login-tagline" name="login_tagline" required maxlength="80"
                        value="{{ old('login_tagline', $loginForm['tagline']) }}">
                    <p class="settings-help">Tampil juga di layar pembuka setelah nama usaha.</p>
                </div>

                <div class="settings-field" style="margin-top:18px">
                    <label for="login-modules">Modul yang dimuat</label>
                    <textarea id="login-modules" name="login_modules" rows="4" required maxlength="400">{{ old('login_modules', implode("\n", (array) $loginForm['modules'])) }}</textarea>
                    <p class="settings-help">Satu modul per baris, maksimal 6 baris dan 30 karakter per baris.</p>
                </div>

                <div class="settings-field" style="margin-top:18px">
                    <label for="login-spinner">Teks langkah terakhir</label>
                    <input id="login-spinner" name="login_spinner" required maxlength="60"
                        value="{{ old('login_spinner', $loginForm['spinner']) }}">
                </div>

                <div class="settings-field" style="margin-top:18px">
                    <label for="login-message">Pesan sebelum login</label>
                    <input id="login-message" name="login_message" required maxlength="100"
                        value="{{ old('login_message', $loginForm['message']) }}">
                </div>

                <div class="settings-field" style="margin-top:18px">
                    <label for="login-accent">Warna terminal</label>
                    <select id="login-accent" name="login_accent" required>
                        @foreach(\App\Services\LoginScreen::ACCENTS as $key => $accent)
                            <option value="{{ $key }}" @selected(old('login_accent', $loginForm['accent']) === $key)>{{ $accent['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="settings-field" style="margin-top:18px">
                    <label for="login-splash">Layar pembuka (nama usaha)</label>
                    <select id="login-splash" name="login_splash" required>
                        <option value="1" @selected((string) old('login_splash', (int) $loginForm['splash']) === '1')>Tampilkan</option>
                        <option value="0" @selected((string) old('login_splash', (int) $loginForm['splash']) === '0')>Sembunyikan</option>
                    </select>
                </div>

                <div class="settings-field" style="margin-top:18px">
                    <label for="login-rain">Hujan karakter di latar</label>
                    <select id="login-rain" name="login_rain" required>
                        <option value="1" @selected((string) old('login_rain', (int) $loginForm['rain']) === '1')>Tampilkan</option>
                        <option value="0" @selected((string) old('login_rain', (int) $loginForm['rain']) === '0')>Sembunyikan</option>
                    </select>
                </div>

                <p class="settings-help" style="margin-top:18px">Pratinjau:</p>
                <pre class="settings-preview" id="login-preview" aria-live="polite"></pre>

                <div class="settings-save-row">
                    <p>Lihat hasilnya dengan keluar lalu membuka halaman login.</p>
                    <div>
                        <button class="settings-save settings-save-secondary" type="submit" name="reset" value="1" formnovalidate
                            onclick="return confirm('Kembalikan tampilan login ke bawaan?')">Kembalikan bawaan</button>
                        <button class="settings-save" type="submit">Simpan tampilan login</button>
                    </div>
                </div>
            </div>
            </details>
        </form>

        <script>
        (function () {
            var f = document.getElementById('login-screen-form');
            if (!f) return;
            var box = document.getElementById('login-preview');
            var name = f.dataset.name, slug = f.dataset.slug;
            function sub(t) { return t.replace(/\{nama\}/g, name).replace(/\{slug\}/g, slug); }
            function val(n) { return f.elements[n].value; }
            function render() {
                var mods = val('login_modules').split(/\r?\n/)
                    .map(function (s) { return s.trim(); })
                    .filter(Boolean).slice(0, 6);
                var out = [sub(val('login_command')), sub(val('login_title')), sub(val('login_tagline'))];
                mods.forEach(function (m) { out.push(sub(m).padEnd(26, '.') + ' [ OK ]'); });
                out.push('* ' + sub(val('login_spinner')) + ' selesai', '', sub(val('login_message')), '', 'login: _');
                box.textContent = out.join('\n');
                box.dataset.accent = val('login_accent');
            }
            f.addEventListener('input', render);
            f.addEventListener('change', render);
            f.addEventListener('invalid', function (event) {
                var holder = event.target.closest('details');
                if (holder) holder.open = true;
            }, true);
            render();
        })();
        </script>

        <details class="settings-section settings-menu settings-danger" {{ $errors->hasAny(['confirmation', 'password']) ? 'open' : '' }} aria-labelledby="reset-heading">
                <summary class="settings-menu-summary">
                    <div class="settings-section-heading">
                <span class="settings-number" aria-hidden="true">08</span>
                <div>
                    <h2 id="reset-heading">Reset data billing</h2>
                    <p>Kosongkan router, paket, pelanggan, tagihan, pembayaran, dan pengeluaran. Pengguna, wilayah, dan pengaturan di atas tidak diubah.</p>
                </div>
                    </div>
                </summary>
                <div class="settings-menu-body">

            <table class="settings-counts">
                <tbody>
                    @foreach(\App\Services\BillingReset::counts() as $table => $count)
                        <tr>
                            <td><code>{{ $table }}</code></td>
                            <td class="settings-count-value">{{ number_format($count, 0, ',', '.') }}</td>
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
                        <button class="settings-save settings-save-danger" type="submit">Kosongkan semua data</button>
                    </div>
                </form>
            @endif
        </div>
            </details>

        <script>
        (function () {
            var items = Array.prototype.slice.call(document.querySelectorAll('details.settings-menu'));

            items.forEach(function (item) {
                item.querySelector('.settings-menu-summary').addEventListener('click', function () {
                    if (item.open) return;
                    items.forEach(function (other) {
                        if (other !== item) other.open = false;
                    });
                });
            });

            var form = document.querySelector('.settings-page > form');
            if (form) {
                form.addEventListener('invalid', function (event) {
                    var holder = event.target.closest('details');
                    if (holder) holder.open = true;
                }, true);
            }
        })();
        </script>
    </main>

    @include('partials.billing-launcher')

    <script src="{{ asset('js/billing-launcher.js') }}?v={{ filemtime(public_path('js/billing-launcher.js')) }}" defer></script>
</body>
</html>
