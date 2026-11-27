<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="cu-network-status-url" content="{{ route('customers.network.status') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1425">
    <title>Pelanggan | Tagihan dan Management Jaringan</title>
    @foreach(['billing-customers','billing-theme','billing-page-header','billing-launcher','billing-network-header'] as $file)
        <link rel="stylesheet" href="{{ asset('css/'.$file.'.css') }}?v={{ filemtime(public_path('css/'.$file.'.css')) }}">
    @endforeach
    <link rel="stylesheet" href="{{ asset('css/billing-customer-tools.css') }}?v={{ filemtime(public_path('css/billing-customer-tools.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/billing-customer-detail.css') }}?v={{ filemtime(public_path('css/billing-customer-detail.css')) }}">
    <script src="{{ asset('js/billing-theme.js') }}?v={{ filemtime(public_path('js/billing-theme.js')) }}"></script>
    <link rel="stylesheet"
        href="{{ asset('css/billing-customer-isolation.css') }}?v={{ filemtime(public_path('css/billing-customer-isolation.css')) }}">
</head>
<body>
@include('partials.billing-page-header', ['headerTitle' => 'Pelanggan'])

<main class="customer-page">
    <div class="customer-heading">
        <div><h1>Pelanggan</h1><p id="cu-customer-count">{{ $customers->total() }} pelanggan terdaftar</p></div>
        <details class="cu-tools" id="cu-tools">
            <summary class="cu-tools-toggle" aria-label="Tindakan pelanggan" title="Tindakan pelanggan">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <circle cx="5" cy="12" r="1.8"/>
                    <circle cx="12" cy="12" r="1.8"/>
                    <circle cx="19" cy="12" r="1.8"/>
                </svg>
            </summary>
            <nav class="cu-tools-panel" aria-label="Tindakan pelanggan">
                <button class="cu-button" id="cu-bulk-prices-open" type="button">Edit nominal</button>
                <a class="cu-button" href="{{ route('customer-excel.template') }}">Template Excel</a>
                <a class="cu-button" href="{{ route('customer-excel-import.index') }}">Import Excel</a>
            <a class="cu-button" data-cu-export href="{{ route('customer-excel.confirm', request()->only(['q', 'router', 'status', 'type'])) }}">Export Excel</a>
            <a class="cu-button" data-cu-pppoe-open href="{{ route('customer-pppoe.index') }}">Import MikroTik</a>
            <button class="cu-button cu-primary" id="cu-add" type="button">+ Tambah Pelanggan</button>
            </nav>
        </details>
    </div>

    @if(session('status'))
        <div class="cu-notice" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="cu-error" role="alert"><ul>
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul></div>
    @endif

    <div class="cu-live-search-wrap">
<form class="cu-search" id="cu-live-search" method="GET" action="{{ route('customers.index') }}" role="search">
            <input type="search" name="q" value="{{ request('q') }}"
                placeholder="Cari pelanggan..."
                aria-label="Cari pelanggan"
                maxlength="150">
        </form>
<p id="cu-search-status" class="cu-search-status" role="status" aria-live="polite"></p>
</div>

    <div class="cu-list">
    @forelse($customers as $customer)
        @php
            $due = $customer->invoice_day ?? $settings->default_invoice_day;
            $pricing = \App\Services\CustomerPricing::forCustomer($customer, $settings);
            $payload = [
                'id' => $customer->id,
                'name' => $customer->name,
                'whatsapp' => $customer->whatsapp,
                'address' => $customer->address,
                'service_type' => $customer->service_type,
                'network_router_id' => $customer->network_router_id,
                'wilayah_id' => $customer->wilayah_id,
                'internet_package_id' => $customer->internet_package_id,
                'pppoe_username' => $customer->pppoe_username,
                'static_ip' => $customer->static_ip,
                'started_at' => $customer->started_at->format('Y-m-d'),
                'invoice_day' => $customer->invoice_day,
                'service_status' => $customer->service_status,
                'notes' => $customer->notes,
                'price_mode' => $customer->price_mode,
                'custom_monthly_price' => $customer->custom_monthly_price,
                'tax_enabled' => (int) $customer->tax_enabled,
            ];
        @endphp
        <article class="cu-card"
            data-cu-network-id="{{ $customer->id }}"
            data-cu-network-router="{{ $customer->network_router_id }}"
            data-cu-network-type="{{ $customer->service_type }}">
            <div class="cu-card-top">
                <div><span class="cu-code">{{ $customer->code }}</span><h2>{{ $customer->name }}</h2></div>
                @include('customers._service-status')
            </div>
            <div class="cu-card-meta">
                <span>{{ $customer->router->name }} · {{ $customer->service_type === 'pppoe' ? 'PPPoE' : 'IP statis' }}</span>
                <strong>{{ $customer->package->name }}</strong>
            </div>
            <div class="cu-card-meta" style="justify-content:space-between">
                <span>{{ $customer->price_mode === 'custom' ? 'Harga khusus' : 'Ikuti harga paket' }}</span>
                <strong>Rp{{ number_format($pricing['total'], 0, ',', '.') }}/bulan</strong>
            </div>
            <div class="cu-credentials">
                @if($customer->service_type === 'pppoe')
                    <div><span>Username</span><code>{{ $customer->pppoe_username }}</code></div>
                    <div><span>Password</span><code>{{ $customer->pppoe_password ?? 'Belum tersedia' }}</code></div>
                @else
                    <div><span>IP pelanggan</span><code>{{ $customer->static_ip }}</code></div>
                @endif
            </div>
            <div class="cu-card-bottom">
                <div class="cu-contact-meta">
                    <span>WA: {{ $customer->whatsapp ?: 'Belum diisi' }}</span>
                    <span>Tagihan: {{ $due ? 'tanggal '.$due : 'Belum diatur' }}</span>
                    <span>Koneksi:
                        <span class="cu-live-connection"
                            data-cu-auto-id="{{ $customer->id }}"
                            data-state="{{ $customer->service_type === 'pppoe' ? 'loading' : 'unsupported' }}">
                            {{ $customer->service_type === 'pppoe' ? 'Memeriksa...' : 'Belum didukung' }}
                        </span>
                    </span>
                </div>
                <div class="cu-actions">
                    <button type="button" class="cu-button cu-view"
                        data-template="cu-info-{{ $customer->id }}">Rincian</button>
                </div>
            </div>
        </article>

        <template id="cu-info-{{ $customer->id }}">
            @php
                $lastChange = $customer->latestChange;
                $detailTaxPercent = rtrim(rtrim(
                    number_format($settings->tax_rate_bps / 100, 2, '.', ''),
                    '0'
                ), '.');
            @endphp

            <div class="cu-compact-detail">
                <header class="cu-compact-identity">
                    <h3>{{ $customer->name }}</h3>
                    <div class="cu-compact-identity-meta">
                        <span>{{ $customer->code }}</span>
                        @include('customers._service-status')
                    </div>
                </header>

                <dl class="cu-compact-grid">
                    <div>
                        <dt>WhatsApp</dt>
                        <dd>{{ $customer->whatsapp ?: 'Belum diisi' }}</dd>
                    </div>
                    <div>
                        <dt>Koneksi PPPoE</dt>
                        <dd>
                            <span class="cu-live-connection"
                                data-cu-auto-id="{{ $customer->id }}"
                                data-state="loading">Memeriksa...</span>
                        </dd>
                        <small class="cu-compact-connection-time"
                            data-cu-compact-time-id="{{ $customer->id }}">
                            <span class="cu-auto-time"
                                data-cu-time-id="{{ $customer->id }}">Menunggu pembaruan</span>
                        </small>
                    </div>

                    <div>
                        <dt>Router</dt>
                        <dd>{{ $customer->router?->name ?: 'Tidak tersedia' }}</dd>
                    </div>
                    <div>
                        <dt>Paket</dt>
                        <dd>{{ $customer->package?->name ?: 'Tidak tersedia' }}</dd>
                    </div>

                    <div>
                        <dt>Mulai layanan</dt>
                        <dd>{{ $customer->started_at?->format('d/m/Y') ?: 'Belum diisi' }}</dd>
                    </div>
                    <div>
                        <dt>Tanggal tagihan</dt>
                        <dd>{{ $due ? $due.' setiap bulan' : 'Belum diatur' }}</dd>
                    </div>

                    <div>
                        <dt>Username PPPoE</dt>
                        <dd class="cu-compact-mono">{{ $customer->pppoe_username ?: 'Belum diisi' }}</dd>
                    </div>
                    <div>
                        <dt>Password</dt>
                        <dd>
                            @if($customer->pppoe_password !== null && $customer->pppoe_password !== '')
                                <div class="cu-compact-password">
                                    <code class="cu-password-value"><span class="cu-password-mask">••••••••</span><span class="cu-password-plain" hidden>{{ $customer->pppoe_password }}</span></code>
                                    <button type="button"
                                        class="cu-password-toggle"
                                        aria-label="Tampilkan password PPPoE"
                                        aria-pressed="false"
                                        title="Tampilkan password">
                                        <svg class="cu-password-eye" width="18" height="18"
                                            viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="1.7"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            aria-hidden="true">
                                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                        <svg class="cu-password-eye-off" width="18" height="18"
                                            viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="1.7"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            aria-hidden="true" hidden>
                                            <path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"/>
                                            <path d="M9.9 5.2A11 11 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3.1 4.1M6.2 6.2A20 20 0 0 0 2 12s3.5 7 10 7a11 11 0 0 0 5.1-1.3"/>
                                        </svg>
                                    </button>
                                </div>
                            @else
                                Belum tersedia
                            @endif
                        </dd>
                    </div>

                    <div class="cu-compact-wide">
                        <dt>Alamat</dt>
                        <dd class="cu-compact-text">{{ $customer->address ?: 'Belum diisi' }}</dd>
                    </div>
                </dl>

                <div class="cu-compact-price">
                    <div class="cu-compact-price-main">
                        <span>Total bulanan</span>
                        <strong>Rp{{ number_format($pricing['total'], 0, ',', '.') }}</strong>
                    </div>
                    <p>
                        {{ $customer->price_mode === 'custom' ? 'Harga khusus' : 'Ikuti harga paket' }}
                        ·
                        @if($customer->tax_enabled)
                            {{ $settings->tax_name }} {{ $detailTaxPercent }}%
                            {{ $settings->tax_mode === 'include' ? 'termasuk dalam harga' : 'ditambahkan ke harga' }}
                        @else
                            Tanpa pajak
                        @endif
                    </p>
                    <details class="cu-compact-price-details">
                        <summary>Rincian biaya</summary>
                        <dl>
                            <div><dt>Harga terpilih</dt><dd>Rp{{ number_format($pricing['price'], 0, ',', '.') }}</dd></div>
                            <div><dt>Sebelum pajak</dt><dd>Rp{{ number_format($pricing['net'], 0, ',', '.') }}</dd></div>
                            <div><dt>{{ $settings->tax_name }}</dt><dd>Rp{{ number_format($pricing['tax'], 0, ',', '.') }}</dd></div>
                        </dl>
                    </details>
                </div>

                @if(filled($customer->notes))
                    <div class="cu-compact-note">
                        <span>Catatan</span>
                        <p>{{ $customer->notes }}</p>
                    </div>
                @endif

                <div class="cu-actions cu-detail-actions"
                    role="group" aria-label="Tindakan pelanggan">
                    <button type="button" class="cu-button cu-edit"
                        data-customer="{{ json_encode($payload) }}"
                        data-url="{{ route('customers.update', $customer) }}">
                        Edit
                    </button>
                    @include('customers._isolation-button')
                    @include('customers._delete-button')
                </div>

                <footer class="cu-compact-change">
                    @if($lastChange)
                        <span>
                            {{ $lastChange->event === 'created' ? 'Ditambahkan' : 'Terakhir diubah' }}:
                            {{ $lastChange->actor_name }}
                        </span>
                        <time class="cu-compact-local-time"
                            datetime="{{ $lastChange->created_at->toIso8601String() }}">
                            {{ $lastChange->created_at->format('d/m/Y H:i T') }}
                        </time>
                    @else
                        <span>Belum ada perubahan yang tercatat.</span>
                    @endif
                </footer>
            </div>
        </template>
    @empty
        <div class="cu-empty"><h2>Belum ada pelanggan yang sesuai</h2><p>Tambahkan pelanggan atau ubah pencarian Anda.</p></div>
    @endforelse
    </div>

    <div class="cu-pagination">
        @if($customers->previousPageUrl())<a class="cu-button" href="{{ $customers->previousPageUrl() }}">Sebelumnya</a>@endif
        <span>Halaman {{ $customers->currentPage() }} / {{ $customers->lastPage() }}</span>
        @if($customers->nextPageUrl())<a class="cu-button" href="{{ $customers->nextPageUrl() }}">Berikutnya</a>@endif
    </div>
</main>

<dialog class="cu-modal" id="cu-modal" aria-labelledby="cu-title">
<div class="cu-modal-inner">
    <div class="cu-modal-heading"><h2 id="cu-title">Tambah pelanggan</h2><button type="button" class="cu-close" data-cu-close aria-label="Tutup">&times;</button></div>
    <form id="cu-form" method="POST"
        action="{{ old('_customer_id') ? route('customers.update', ['customer' => old('_customer_id')]) : route('customers.store') }}"
        data-store="{{ route('customers.store') }}">
        @csrf
        <input type="hidden" name="_method" value="POST">
        <input type="hidden" name="_customer_id">

        <label for="cu-name">Nama pelanggan</label>
        <input id="cu-name" name="name" required maxlength="150">

        <div class="cu-form-grid">
            <div><label for="cu-wa">WhatsApp</label><input id="cu-wa" name="whatsapp" type="tel" maxlength="30"></div>
            <div><label for="cu-start">Tanggal mulai</label><input id="cu-start" name="started_at" type="date" required></div>
        </div>

        <label for="cu-address">Alamat</label>
        <textarea id="cu-address" name="address" rows="2" maxlength="5000"></textarea>

        <label for="cu-wilayah">Wilayah</label>
        <select id="cu-wilayah" name="wilayah_id" required>
            <option value="">Pilih wilayah</option>
            @foreach($wilayahList as $area)<option value="{{ $area->id }}">{{ $area->name }}</option>@endforeach
        </select>

        <div class="cu-form-grid">
            <div>
                <label for="cu-type">Jenis layanan</label>
                <select id="cu-type" name="service_type"><option value="pppoe">PPPoE</option><option value="static">IP statis</option></select>
            </div>
            <div>
                <label for="cu-router">Router</label>
                <select id="cu-router" name="network_router_id" required>
                    <option value="">Pilih router</option>
                    @foreach($routers as $router)<option value="{{ $router->id }}">{{ $router->name }}</option>@endforeach
                </select>
            </div>
        </div>

        <label for="cu-package">Paket internet</label>
        <select id="cu-package" name="internet_package_id" required><option value="">Pilih paket</option></select>

        <div id="cu-pppoe" class="cu-form-grid">
            <div><label for="cu-username">Username PPPoE</label><input id="cu-username" name="pppoe_username" maxlength="100" autocomplete="off" autocapitalize="none" spellcheck="false"></div>
            <div>
                <label for="cu-password">Password PPPoE</label>
                <input id="cu-password" name="pppoe_password" type="text" maxlength="1024" autocomplete="off" autocapitalize="none" spellcheck="false">
                <p class="cu-help">Saat edit, kosongkan jika tidak diganti. Tidak dikirim ke MikroTik.</p>
            </div>
        </div>

        <div id="cu-static" hidden>
            <label for="cu-ip">IP pelanggan</label><input id="cu-ip" name="static_ip" maxlength="45" placeholder="192.168.10.2">
        </div>

        <div class="cu-form-grid">
            <div>
                <label for="cu-invoice-day">Tanggal tagihan</label>
        <select id="cu-invoice-day" name="invoice_day">
            <option value="">Ikuti bawaan{{ $settings->default_invoice_day ? ' · tanggal '.$settings->default_invoice_day : ' · belum diatur' }}</option>
            @for($day = 1; $day <= 31; $day++)
                <option value="{{ $day }}">Khusus tanggal {{ $day }}</option>
            @endfor
        </select>
            </div>
            <div>
                <label for="cu-status">Status layanan di billing</label>
                <select id="cu-status" name="service_status"><option value="active">Aktif</option><option value="stopped">Berhenti</option></select>
            </div>
        </div>

        

        @include('partials.customer-pricing-fields')

        <label for="cu-notes">Catatan</label>
        <textarea id="cu-notes" name="notes" rows="2" maxlength="5000"></textarea>

        <p class="cu-help">Penyimpanan hanya mencatat data di billing. Status ini tidak memutus atau mengaktifkan koneksi router.</p>
        <div class="cu-actions cu-modal-actions">
            <button type="button" class="cu-button" data-cu-close>Batal</button>
            <button type="submit" class="cu-button cu-primary">Simpan pelanggan</button>
        </div>
    </form>
</div>
</dialog>

<dialog class="cu-modal" id="cu-info-modal" aria-labelledby="cu-info-title">
    <div class="cu-modal-inner">
        <div class="cu-modal-heading"><h2 id="cu-info-title">Rincian pelanggan</h2><button type="button" class="cu-close" data-cu-close aria-label="Tutup">&times;</button></div>
        <div id="cu-info-body"></div>
    </div>
</dialog>

@php
    $packageOptions = $packages->map(fn ($p) => [
        'id' => $p->id,
        'name' => $p->name,
        'type' => $p->service_type,
        'active' => $p->is_active,
        'price' => (int) $p->monthly_price,
        'routers' => $p->routers->pluck('id')->all(),
    ])->values()->all();

    $initial = [
        'open' => $errors->any() && old('_method') !== null,
        'id' => old('_customer_id'),
        'name' => old('name', ''),
        'whatsapp' => old('whatsapp', ''),
        'address' => old('address', ''),
        'service_type' => old('service_type', 'pppoe'),
        'network_router_id' => old('network_router_id', ''),
        'wilayah_id' => old('wilayah_id', ''),
        'internet_package_id' => old('internet_package_id', ''),
        'pppoe_username' => old('pppoe_username', ''),
        'static_ip' => old('static_ip', ''),
        'started_at' => old('started_at', now()->format('Y-m-d')),
        'invoice_day' => old('invoice_day', ''),
        'service_status' => old('service_status', 'active'),
        'notes' => old('notes', ''),
        'price_mode' => old('price_mode', 'package'),
        'custom_monthly_price' => old('custom_monthly_price', ''),
        'tax_enabled' => (int) old('tax_enabled', (int) $settings->default_tax_enabled),
    ];
@endphp
<div id="cu-data" hidden data-packages="{{ json_encode($packageOptions) }}" data-router-wilayah="{{ json_encode($routerWilayah) }}" data-enforced="{{ \App\Models\Scopes\WilayahScope::enforced() ? 1 : 0 }}" data-initial="{{ json_encode($initial) }}" data-today="{{ now()->format('Y-m-d') }}"></div>

@include('partials.billing-launcher')
<script src="{{ asset('js/billing-launcher.js') }}?v={{ filemtime(public_path('js/billing-launcher.js')) }}" defer></script>
<script src="{{ asset('js/billing-customers.js') }}?v={{ filemtime(public_path('js/billing-customers.js')) }}" defer></script>
<script src="{{ asset('js/billing-customer-tools.js') }}?v={{ filemtime(public_path('js/billing-customer-tools.js')) }}" defer></script>
<script src="{{ asset('js/billing-customer-auto-network.js') }}?v={{ filemtime(public_path('js/billing-customer-auto-network.js')) }}" defer></script>
<script src="{{ asset('js/billing-customer-detail.js') }}?v={{ filemtime(public_path('js/billing-customer-detail.js')) }}" defer></script>
@include('customers._isolation-dialog')
@include('customers._bulk-prices')
@include('customers._pppoe-import-dialog')
@include('customers._delete-dialog')
</body>
</html>
