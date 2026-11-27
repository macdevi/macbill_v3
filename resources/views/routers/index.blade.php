<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Router MikroTik | Tagihan dan Management Jaringan</title>
    <link rel="stylesheet" href="{{ asset('css/billing-routers.css') }}?v=1795252022983102024">
    <link rel="stylesheet" href="{{ asset('css/billing-router-dialogs.css') }}?v=1795252022983102024">
<link rel="stylesheet" href="{{ asset('css/billing-router-isolation.css') }}?v=1795252022983102024">
<link id="billing-theme-css" rel="stylesheet" href="{{ asset('css/billing-theme.css') }}?v=1795252022983102024">
<script src="{{ asset('js/billing-theme.js') }}?v=1795252022983102024"></script>
<link rel="stylesheet" href="{{ asset('css/billing-page-header.css') }}?v=1795252767952418476">
<link rel="stylesheet" href="{{ asset('css/billing-launcher.css') }}?v={{ filemtime(public_path('css/billing-launcher.css')) }}">
<link rel="stylesheet" href="{{ asset('css/billing-network-header.css') }}?v=1795253999346304957">
</head>
<body>
    @include('partials.billing-page-header', ['headerTitle' => 'Router MikroTik'])

    <main class="router-page">
        <div class="router-page-heading">
            <h1>Router MikroTik</h1>
            <p>Konfigurasi koneksi API RouterOS 6 dan 7.</p>
        </div>

        @if (session('status'))
            <div class="router-notice" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="router-error" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="router-list-toolbar">
    <p>Kelola konfigurasi koneksi router Anda.</p>
    <button type="button" class="rm-button rm-primary" id="router-add">+ Tambah Router</button>
</div>
<div class="router-layout router-list-only">


            <section class="router-card">
                <div class="router-card-heading">
                    <h2>Daftar router</h2>
                    <span>{{ $routers->count() }} koneksi</span>
                </div>

                @forelse ($routers as $router)
                    <article class="router-list-item">
                        <div class="router-list-title">
                            <h3>{{ $router->name }}</h3>
                            <div class="router-item-actions">
    <button type="button" class="rm-button router-edit"
        data-update-url="{{ route('routers.update', $router) }}"
        data-router="{{ json_encode([
            'id' => $router->id,
            'name' => $router->name,
            'host' => $router->host,
            'port' => $router->port,
            'username' => $router->username,
            'use_tls' => (bool) $router->use_tls,
            'wan_interface' => $router->wan_interface,
            'isolation_method' => $router->isolation_method,
            'isolation_profile' => $router->isolation_profile,
            'profiles_url' => route('routers.profiles', $router),
        ]) }}">Edit</button>
    <button type="button" class="rm-button rm-danger router-delete"
        data-delete-url="{{ route('routers.destroy', $router) }}"
        data-router-name="{{ $router->name }}">Hapus</button>
</div>
                        </div>
                        <p class="router-address">
                            {{ $router->host }} &middot; Port {{ $router->port }}
                        </p>
                        <div class="router-tags">
                            <span>{{ $router->use_tls ? 'TLS' : 'Tanpa TLS' }}</span>
                            <span>Belum diperiksa</span>
                        </div>
                        <p class="router-help">
                            WAN: {{ $router->wan_interface ?: 'Belum dipilih' }}
                        </p>

                        <p class="router-help">
    Isolir PPPoE:
    {{ $router->isolation_method === 'ppp_profile'
        ? 'Profil '.$router->isolation_profile.' (pilihan tersimpan, belum otomatis)'
        : 'Belum dikonfigurasi' }}
</p>

<form class="router-test-form" method="POST" 
                            action="{{ route('routers.test', $router) }}">
                            @csrf
                            <button type="submit" class="router-test-button">
                                Tes koneksi
                            </button>
                            <span class="router-test-hint">Hanya membaca data router</span>
                        </form>

                        @php($test = session('router_test'))
                        @if ($test && (int) $test['router_id'] === (int) $router->id)
                            <div class="router-test-result {{ $test['ok'] ? 'is-success' : 'is-failed' }}"
                                role="status">
                                <div class="router-test-result-heading">
                                    <strong>{{ $test['ok'] ? 'Connect' : 'Tes belum berhasil' }}</strong>
                                    <time datetime="{{ $test['checked_at'] }}">
                                        {{ \Illuminate\Support\Carbon::parse($test['checked_at'])->format('d/m/Y H:i:s T') }}
                                    </time>
                                </div>

                                @if ($test['ok'])
                                    <dl class="router-test-data">
                                        <div>
                                            <dt>Identitas</dt>
                                            <dd>{{ $test['identity'] }}</dd>
                                        </div>
                                        <div>
                                            <dt>RouterOS</dt>
                                            <dd>{{ $test['version'] }}</dd>
                                        </div>
                                        <div>
                                            <dt>CPU</dt>
                                            <dd>{{ $test['cpu'] }}%</dd>
                                        </div>
                                        <div>
                                            <dt>Uptime</dt>
                                            <dd>{{ $test['uptime'] }}</dd>
                                        </div>
                                    </dl>
                                    
<p>Hasil pemeriksaan sesaat, bukan monitoring langsung.</p>
                                @else
                                    <p>{{ $test['message'] }}</p>
                                @endif
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="router-empty">
                        <h3>Belum ada router</h3>
                        <p>Klik tombol Tambah Router untuk menambahkan koneksi.</p>
                    </div>
                @endforelse
            </section>
        </div>
    </main>
            <dialog id="router-modal" class="router-modal" aria-labelledby="router-modal-title">
    <div class="router-modal-inner">
<div class="router-modal-heading">
    <h2 id="router-modal-title">{{ $editing ? 'Edit router' : 'Tambah router' }}</h2>
    <button type="button" class="rm-close" data-close-dialog aria-label="Tutup">&times;</button>
</div>

                <form id="router-config-form"
        data-store-url="{{ route('routers.store') }}"
        data-auto-open="{{ ($editing || $errors->any()) ? '1' : '0' }}"
        method="POST"
                    action="{{ $editing ? route('routers.update', $editing) : route('routers.store') }}">
                    @csrf
<input type="hidden" name="_method" value="{{ $editing ? 'PUT' : 'POST' }}">
<input type="hidden" name="_router_id" value="{{ $editing?->id }}">

                    <label for="router-name">Nama koneksi</label>
                    <input id="router-name" name="name" required maxlength="100"
                        value="{{ old('name', $editing?->name) }}"
                        placeholder="Router Utama">

                    <div class="router-form-row">
                        <div>
                            <label for="router-host">IP / hostname</label>
                            <input id="router-host" name="host" required maxlength="253"
                                value="{{ old('host', $editing?->host) }}"
                                placeholder="Alamat router" autocapitalize="none"
                                spellcheck="false">
                        </div>
                        <div>
                            <label for="router-port">Port API</label>
                            <input id="router-port" name="port" type="number"
                                min="1" max="65535" required
                                value="{{ old('port', $editing?->port) }}"
                                placeholder="Port manual">
                        </div>
                    </div>

                    <input type="hidden" name="use_tls" value="0">
                    <label class="router-checkbox">
                        <input type="checkbox" name="use_tls" value="1"
                            @checked(old('use_tls', $editing?->use_tls ?? false))>
                        Gunakan TLS / API-SSL
                    </label>
                    <p class="router-help">
                        Pilihan TLS tidak mengganti port. Verifikasi sertifikat wajib
                        saat koneksi TLS nanti diaktifkan.
                    </p>

                    <label for="router-username">Username API</label>
                    <input id="router-username" name="username" required maxlength="100"
                        value="{{ old('username', $editing?->username) }}"
                        autocomplete="off" autocapitalize="none" spellcheck="false">

                    <label for="router-password">Password
    <span id="router-password-help">{{ $editing ? '(kosongkan jika tidak diganti)' : '' }}</span>
</label>
                    <input id="router-password" name="password" type="password"
                        maxlength="1024" autocomplete="new-password"
                        @required(!$editing)>

                    <label for="router-wan">Interface WAN <span>(opsional)</span></label>
                    <input id="router-wan" name="wan_interface" maxlength="100"
                        value="{{ old('wan_interface', $editing?->wan_interface) }}"
                        placeholder="Nama persis interface, boleh diisi nanti"
                        autocapitalize="none" spellcheck="false">

                    <section id="router-isolation-settings"
    data-initial-url="{{ $editing ? route('routers.profiles', $editing) : '' }}"
    data-initial-method="{{ old('isolation_method', $editing?->isolation_method ?? 'none') }}"
    data-initial-profile="{{ old('isolation_profile', $editing?->isolation_profile) }}">
    <h3>Pengaturan isolir PPPoE</h3>
    <p class="router-help">
        Hanya menyimpan pilihan. Belum menjalankan isolir pelanggan.
        Pengaturan ini tidak digunakan untuk pelanggan IP statis non-PPPoE.
    </p>

    <label for="router-isolation-method">Metode isolir</label>
    <select id="router-isolation-method" name="isolation_method">
        <option value="none">Belum dikonfigurasi</option>
        <option value="ppp_profile">Ganti profil PPP</option>
    </select>

    <div id="router-isolation-profile-fields" hidden>
        <label for="router-isolation-profile">Profil isolir</label>
        <select id="router-isolation-profile" name="isolation_profile">
            <option value="">Pilih profil isolir</option>
        </select>
        <button type="button" class="rm-button" id="router-load-profiles">
            Ambil profil dari MikroTik
        </button>
        <p id="router-profile-message" class="router-help" role="status"></p>
    </div>

    <p class="router-help">
        Router baru perlu disimpan terlebih dahulu dengan metode
        “Belum dikonfigurasi”, lalu dibuka kembali melalui Edit.
        Daftar profil dibaca memakai koneksi yang sudah tersimpan.
        Saat menyimpan metode profil PPP, aplikasi memverifikasi ulang
        pilihan menggunakan konfigurasi yang akan disimpan.
    </p>
</section>

<div class="router-modal-actions">
    <button class="rm-button" type="button" data-close-dialog>Batal</button>
    <button class="router-save" type="submit">Simpan konfigurasi</button>
</div>
                    <p class="router-help">
                        Menyimpan tidak menjalankan koneksi atau mengubah konfigurasi router.
                    </p>
                </form>
            </div>
</dialog>

<dialog id="router-delete-modal" class="router-modal router-modal-small"
    aria-labelledby="router-delete-title" aria-describedby="router-delete-description">
    <div class="router-modal-inner">
        <div class="router-modal-heading">
            <h2 id="router-delete-title">Hapus router?</h2>
            <button type="button" class="rm-close" data-close-dialog aria-label="Tutup">&times;</button>
        </div>
        <p id="router-delete-description">
            Hapus konfigurasi <strong id="router-delete-name"></strong> dari aplikasi?
        </p>
        <p class="router-help">
            Konfigurasi koneksi ini akan dihapus. Tindakan ini tidak menghapus akun,
            pelanggan, atau mengubah konfigurasi pada perangkat MikroTik.
        </p>
        <form id="router-delete-form" method="POST">
            @csrf
            @method('DELETE')
            <div class="router-modal-actions">
                <button type="button" class="rm-button" data-close-dialog autofocus>Batal</button>
                <button type="submit" class="rm-button rm-danger-fill">Ya, hapus konfigurasi</button>
            </div>
        </form>
    </div>
</dialog>


<script src="{{ asset('js/billing-router-dialogs.js') }}?v=1" defer></script>

@php($successTest = session('router_test'))
@if ($successTest && !empty($successTest['ok']))
<dialog id="router-success-modal"
    class="router-modal router-modal-small"
    aria-labelledby="router-success-title"
    aria-describedby="router-success-description">
    <div class="router-modal-inner">
        <div class="router-modal-heading">
            <h2 id="router-success-title">Berhasil terhubung</h2>
            <button type="button" class="rm-close"
                id="router-success-close" aria-label="Tutup">&times;</button>
        </div>

        <p id="router-success-description" style="line-height:1.8;color:var(--ui-text);margin:0">
            Koneksi MikroTik berhasil terhubung. Selanjutnya, silakan
            sesuaikan paket internet pada halaman Paket Internet.
        </p>

        <div class="router-modal-actions">
            <button type="button" class="rm-button rm-primary"
                id="router-success-understood" autofocus>
                Mengerti
            </button>
        </div>
    </div>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('router-success-modal');
    if (!dialog || document.querySelector('dialog[open]')) return;

    const previousFocus = document.activeElement;
    const close = () => dialog.close();

    document.getElementById('router-success-close').addEventListener('click', close);
    document.getElementById('router-success-understood').addEventListener('click', close);

    dialog.addEventListener('close', () => {
        if (!document.querySelector('dialog[open]')) {
            document.body.classList.remove('router-dialog-open');
        }
        if (previousFocus?.isConnected) previousFocus.focus();
    });

    dialog.showModal();
    document.body.classList.add('router-dialog-open');
    document.getElementById('router-success-understood').focus();
});
</script>
@endif

<script src="{{ asset('js/billing-router-isolation.js') }}?v=1" defer></script>
@include('partials.billing-launcher')
<script src="{{ asset('js/billing-launcher.js') }}?v=1795253508806308910" defer></script>
</body>
</html>
