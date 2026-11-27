<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Paket Internet | Tagihan dan Management Jaringan</title>
    <link rel="stylesheet" href="{{ asset('css/billing-packages.css') }}?v=1795252022983102024">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/billing-package-sync.css') }}?v=1795252022983102024">
<link rel="stylesheet" href="{{ asset('css/billing-package-details.css') }}?v=1795252022983102024">
    <link rel="stylesheet" href="{{ asset('css/billing-packages-mobile.css') }}?v=1795252022983102024">
<link id="billing-theme-css" rel="stylesheet" href="{{ asset('css/billing-theme.css') }}?v=1795252022983102024">
<script src="{{ asset('js/billing-theme.js') }}?v=1795252022983102024"></script>
<link rel="stylesheet" href="{{ asset('css/billing-page-header.css') }}?v=1795252767952418476">
<link rel="stylesheet" href="{{ asset('css/billing-launcher.css') }}?v={{ filemtime(public_path('css/billing-launcher.css')) }}">
<link rel="stylesheet" href="{{ asset('css/billing-network-header.css') }}?v=1795253999346304957">
</head>
<body>
@include('partials.billing-page-header', ['headerTitle' => 'Paket Internet'])

<main class="pkg-page">
    <div class="pkg-heading">
        <div>
            <h1>Paket Internet</h1>
            <p>Kelola harga layanan dan pemetaan profil normal per router.</p>
        </div>
        <button type="button" class="pkg-button pkg-primary" id="pkg-add">+ Tambah Paket</button>
    </div>

    @if(session('status'))
        <div class="pkg-notice" role="status">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="pkg-error" role="alert">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="pkg-grid">
        @forelse($packages as $package)
            @php
                $payload = [
                    'id' => $package->id,
                    'name' => $package->name,
                    'monthly_price' => $package->monthly_price,
                    'download_mbps' => $package->download_mbps,
                    'upload_mbps' => $package->upload_mbps,
                    'rate_limit' => $package->rate_limit,
                    'service_type' => $package->service_type,
                    'is_active' => $package->is_active,
                    'mappings' => $package->routers->map(fn ($r) => [
                        'router_id' => $r->id,
                        'profile' => $r->pivot->normal_profile,
                        'router_name' => $r->name,
                    ])->values()->all(),
                ];
            @endphp
            <article class="pkg-card">
                <div class="pkg-card-top">
                    <h2>{{ $package->name }}</h2>
                    <span class="pkg-badge {{ $package->is_active ? 'active' : '' }}">
                        {{ $package->is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>
                <p class="pkg-price">
                    Rp {{ number_format($package->monthly_price, 0, ',', '.') }}
                    <small>/ bulan</small>
                </p>
                <p class="pkg-type">{{ $package->service_type === 'pppoe' ? 'PPPoE' : 'IP statis' }}</p>
                <div class="pkg-bandwidth-summary">
                    <span>Download <strong>{{ $package->download_mbps !== null ? rtrim(rtrim($package->download_mbps, '0'), '.') . ' Mbps' : 'Belum diatur' }}</strong></span>
                    <span>Upload <strong>{{ $package->upload_mbps !== null ? rtrim(rtrim($package->upload_mbps, '0'), '.') . ' Mbps' : 'Belum diatur' }}</strong></span>
                </div>

                @if($package->service_type === 'pppoe')
                    <ul class="pkg-mapping-list">
                        @foreach($package->routers as $router)
                            <li>
                                <span>{{ $router->name }}</span>
                                <code>{{ $router->pivot->normal_profile }}</code>
                                <div class="pkg-router-sync" hidden>
                                    <span class="pkg-muted pkg-sync-status">Belum diperiksa</span>
                                    <button type="button" class="pkg-button pkg-sync-preview"
                                        data-url="{{ route('packages.preview', [$package, $router]) }}"
                                        data-push="{{ route('packages.push', [$package, $router]) }}">
                                        Push ke MikroTik
                                    </button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="pkg-muted">Pencatatan paket saja. Pengaturan bandwidth IP statis belum diaktifkan.</p>
                @endif

                <div class="pkg-actions">
                    <button type="button" class="pkg-button pkg-edit"
                        data-package="{{ json_encode($payload) }}"
                        data-url="{{ route('packages.update', $package) }}">Edit</button>
                    <button type="button" class="pkg-button pkg-danger pkg-delete"
                        data-name="{{ $package->name }}"
                        data-url="{{ route('packages.destroy', $package) }}">Hapus</button>
                    <button type="button" class="pkg-button pkg-view"
                        data-package="{{ json_encode($payload) }}">Lihat</button>
                    @if($package->service_type === 'pppoe')
                        <button type="button" class="pkg-button pkg-primary pkg-push-card">
                            Push ke MikroTik
                        </button>
                    @endif
                </div>
            </article>
        @empty
            <div class="pkg-empty">
                <h2>Belum ada paket internet</h2>
                <p>Klik Tambah Paket untuk membuat paket pertama.</p>
            </div>
        @endforelse
    </div>
</main>

<dialog id="pkg-modal" class="pkg-modal" aria-labelledby="pkg-title">
    <div class="pkg-modal-inner">
        <div class="pkg-modal-heading">
            <h2 id="pkg-title">Tambah paket</h2>
            <button type="button" class="pkg-close" data-close aria-label="Tutup">&times;</button>
        </div>

        <form method="POST" id="pkg-form"
            action="{{ old('_package_id') ? route('packages.update', ['package' => old('_package_id')]) : route('packages.store') }}"
            data-store="{{ route('packages.store') }}">
            @csrf
            <input type="hidden" name="_method" value="{{ old('_package_id') ? 'PUT' : 'POST' }}">
            <input type="hidden" name="_package_id" value="{{ old('_package_id') }}">

            <label for="pkg-name">Nama paket</label>
            <input id="pkg-name" name="name" required maxlength="100" placeholder="Rumah 10 Mbps">

            <label for="pkg-price">Harga bulanan (Rp)</label>
            <input id="pkg-price" name="monthly_price" type="number"
                min="0" max="1000000000" step="1" required placeholder="150000">
            <p class="pkg-muted">Masukkan angka tanpa titik, misalnya 150000.</p>

            <div class="pkg-bandwidth-fields">
                <div>
                    <label for="pkg-download">Download (Mbps)</label>
                    <input id="pkg-download" name="download_mbps" type="number"
                        min="0" max="100000" step="0.001" placeholder="10">
                </div>
                <div>
                    <label for="pkg-upload">Upload (Mbps)</label>
                    <input id="pkg-upload" name="upload_mbps" type="number"
                        min="0" max="100000" step="0.001" placeholder="5">
                </div>
            </div>
            <p class="pkg-muted">Kosong berarti belum diatur. Angka 0 berarti tanpa batas pada arah tersebut.</p>
            <label for="pkg-full-rate">Rate-limit lengkap MikroTik</label>
            <input id="pkg-full-rate" name="rate_limit" maxlength="512"
                autocomplete="off" spellcheck="false"
                placeholder="5M/10M 8M/15M 4M/8M 10/10 8 2M/4M">
            <p class="pkg-muted">
                Terisi otomatis saat profil dipilih. Bisa diedit untuk pengaturan lanjutan.
                Urutan pasangan: upload/download. Perubahan download/upload di atas
                mempertahankan bagian burst dan parameter lanjutan.
            </p>
            <p id="pkg-import-source" class="pkg-muted" role="status"></p>
            <div id="pkg-advanced-values" class="pkg-advanced-values"></div>

            <label for="pkg-service">Jenis layanan</label>
            <select id="pkg-service" name="service_type">
                <option value="pppoe">PPPoE</option>
                <option value="static">IP statis</option>
            </select>

            <label for="pkg-active">Status paket</label>
            <select id="pkg-active" name="is_active">
                <option value="1">Aktif</option>
                <option value="0">Nonaktif</option>
            </select>

            <section id="pkg-mapping-section">
                <h3>Profil normal per router</h3>
                <p class="pkg-muted">
                    Pilih minimal satu router. Ambil daftar profil lalu pilih profil normal.
                    Ini bukan profil isolir. Profil diperiksa ulang saat disimpan.
                </p>

                @forelse($routers as $router)
                    <div class="pkg-router-row"
                        data-router-id="{{ $router->id }}"
                        data-url="{{ route('routers.profiles', $router) }}"
                        data-detail-url="{{ route('packages.profile-details', $router) }}">
                        <label class="pkg-check">
                            <input type="checkbox" class="pkg-router-enabled">
                            {{ $router->name }}
                        </label>
                        <div class="pkg-router-fields" hidden>
                            <input type="hidden" class="pkg-router-id"
                                name="mappings[{{ $router->id }}][router_id]"
                                value="{{ $router->id }}" disabled>
                            <label for="pkg-profile-{{ $router->id }}">Profil normal</label>
                            <select id="pkg-profile-{{ $router->id }}" class="pkg-profile"
                                name="mappings[{{ $router->id }}][profile]" disabled>
                                <option value="">Pilih profil normal</option>
                            </select>
                            <button type="button" class="pkg-button pkg-fetch">Ambil profil</button>
                            <p class="pkg-muted pkg-fetch-message" role="status"></p>
                        </div>
                    </div>
                @empty
                    <p class="pkg-muted">
                        Belum ada router. Tambahkan router terlebih dahulu melalui
                        <a href="{{ route('routers.index') }}">halaman Router MikroTik</a>.
                    </p>
                @endforelse
            </section>

            <p id="pkg-static-note" class="pkg-muted" hidden>
                Paket IP statis hanya dicatat di aplikasi.
                Pengaturan bandwidth dan isolir IP statis belum diaktifkan.
            </p>

            <div class="pkg-actions pkg-modal-actions">
                <button type="button" class="pkg-button" data-close>Batal</button>
                <button type="submit" class="pkg-button pkg-primary">Simpan paket</button>
            </div>
            <p class="pkg-muted">Menyimpan paket tidak mengubah konfigurasi MikroTik.</p>
        </form>
    </div>
</dialog>

<dialog id="pkg-delete-modal" class="pkg-modal pkg-modal-small" aria-labelledby="pkg-delete-title">
    <div class="pkg-modal-inner">
        <h2 id="pkg-delete-title">Hapus paket?</h2>
        <p>Hapus paket <strong id="pkg-delete-name"></strong> dari aplikasi?</p>
        <p class="pkg-muted">Profil pada MikroTik tidak dihapus.</p>
        <form id="pkg-delete-form" method="POST">
            @csrf
            @method('DELETE')
            <div class="pkg-actions pkg-modal-actions">
                <button type="button" class="pkg-button" data-close autofocus>Batal</button>
                <button type="submit" class="pkg-button pkg-danger">Ya, hapus paket</button>
            </div>
        </form>
    </div>
</dialog>

@php
    $initial = [
        'open' => $errors->any(),
        'id' => old('_package_id'),
        'name' => old('name', ''),
        'monthly_price' => old('monthly_price', ''),
        'download_mbps' => old('download_mbps', ''),
        'upload_mbps' => old('upload_mbps', ''),
        'rate_limit' => old('rate_limit', ''),
        'service_type' => old('service_type', 'pppoe'),
        'is_active' => old('is_active', '1'),
        'mappings' => array_values(old('mappings', []) ?? []),
    ];
@endphp
<div id="pkg-initial" hidden data-value="{{ json_encode($initial) }}"></div>
<script src="{{ asset('js/billing-packages.js') }}?v=3" defer></script>

<dialog id="pkg-sync-modal" class="pkg-modal" aria-labelledby="pkg-sync-title">
    <div class="pkg-modal-inner">
        <div class="pkg-modal-heading">
            <h2 id="pkg-sync-title">Preview MikroTik</h2>
            <button type="button" class="pkg-close" id="pkg-sync-close" aria-label="Tutup">&times;</button>
        </div>
        <div id="pkg-sync-details" style="white-space:pre-wrap;line-height:1.8;overflow-wrap:anywhere"></div>
        <p class="pkg-muted">
            Push hanya mengganti rate-limit profil ini.
            Pengguna lain yang memakai profil yang sama di router juga dapat terdampak,
            termasuk pengguna yang belum tercatat di aplikasi.
            Tidak ada pemutusan sesi otomatis.
        </p>
        <label class="pkg-check">
            <input type="checkbox" id="pkg-sync-confirm">
            Saya menyetujui perubahan yang ditampilkan.
        </label>
        <p id="pkg-sync-result" role="status" style="line-height:1.7"></p>
        <div class="pkg-actions pkg-modal-actions">
            <button type="button" class="pkg-button" id="pkg-sync-cancel">Tutup</button>
            <button type="button" class="pkg-button pkg-primary" id="pkg-sync-push" disabled>
                Push ke MikroTik
            </button>
        </div>
    </div>
</dialog>
<script src="{{ asset('js/billing-package-sync.js') }}?v=1795252544943490627" defer></script>


<dialog id="pkg-info-modal" class="pkg-modal" aria-labelledby="pkg-info-title">
    <div class="pkg-modal-inner">
        <div class="pkg-modal-heading">
            <h2 id="pkg-info-title">Informasi paket</h2>
            <button type="button" class="pkg-close" data-info-close aria-label="Tutup">&times;</button>
        </div>
        <div id="pkg-info-body"></div>
        <div class="pkg-actions pkg-modal-actions">
            <button type="button" class="pkg-button" data-info-close>Tutup</button>
        </div>
    </div>
</dialog>

<dialog id="pkg-router-choice" class="pkg-modal pkg-modal-small" aria-labelledby="pkg-router-choice-title">
    <div class="pkg-modal-inner">
        <h2 id="pkg-router-choice-title">Push ke MikroTik</h2>
        <p class="pkg-muted">Pilih router tujuan. Perubahan akan ditampilkan untuk dikonfirmasi sebelum dikirim.</p>
        <div id="pkg-router-choice-buttons" class="pkg-choice-buttons"></div>
        <div class="pkg-actions pkg-modal-actions">
            <button type="button" class="pkg-button" id="pkg-router-choice-cancel">Batal</button>
        </div>
    </div>
</dialog>

<script src="{{ asset('js/billing-package-details.js') }}?v=1" defer></script>

@include('partials.billing-launcher')
<script src="{{ asset('js/billing-launcher.js') }}?v=1795253508806308910" defer></script>
</body>
</html>
