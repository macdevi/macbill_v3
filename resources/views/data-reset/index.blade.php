<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1425">
    <title>Reset Data | Tagihan dan Management Jaringan</title>

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
    @include('partials.billing-page-header', ['headerTitle' => 'Reset Data'])

    <main class="settings-page">
        <div class="settings-heading">
            <span class="settings-eyebrow">MASA PERCOBAAN</span>
            <h1>Reset data billing</h1>
            <p>Mengosongkan router, paket, pelanggan, tagihan, pembayaran, dan pengeluaran. Pengguna, wilayah, dan pengaturan billing tidak diubah.</p>
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

        <section class="settings-section" aria-labelledby="count-heading">
            <div class="settings-section-heading">
                <span class="settings-number" aria-hidden="true">01</span>
                <div>
                    <h2 id="count-heading">Data yang akan dihapus</h2>
                    <p>Jumlah baris saat ini di setiap tabel.</p>
                </div>
            </div>

            <table style="width:100%;margin-top:18px;border-collapse:collapse">
                <tbody>
                    @foreach($counts as $table => $count)
                        <tr>
                            <td style="padding:6px 0"><code>{{ $table }}</code></td>
                            <td style="padding:6px 0;text-align:right">{{ number_format($count, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="settings-help">Hanya database aplikasi yang dikosongkan. Akun PPPoE di MikroTik tidak diubah. Catat dulu data router, paket, dan pemetaan profil karena harus dibuat ulang.</p>
        </section>

        <section class="settings-section" aria-labelledby="confirm-heading">
            <div class="settings-section-heading">
                <span class="settings-number" aria-hidden="true">02</span>
                <div>
                    <h2 id="confirm-heading">Konfirmasi</h2>
                    <p>Tindakan ini tidak bisa dibatalkan. Cadangkan database lebih dulu.</p>
                </div>
            </div>

            @if(! $enabled)
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
                        <label for="confirmation">Ketik KOSONGKAN</label>
                        <input id="confirmation" name="confirmation" type="text" required autocomplete="off">
                    </div>

                    <div class="settings-field" style="margin-top:18px">
                        <label for="password">Kata sandi Anda</label>
                        <input id="password" name="password" type="password" required autocomplete="current-password">
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
