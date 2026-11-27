<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1425">
    <title>Pasang Baru | Tagihan dan Management Jaringan</title>
    @foreach(['billing-theme','billing-page-header','billing-launcher','billing-network-header','billing-pasang-baru'] as $file)
        <link rel="stylesheet" href="{{ asset('css/'.$file.'.css') }}?v={{ filemtime(public_path('css/'.$file.'.css')) }}">
    @endforeach
    <script src="{{ asset('js/billing-theme.js') }}?v={{ filemtime(public_path('js/billing-theme.js')) }}"></script>
</head>
<body>
@include('partials.billing-page-header', ['headerTitle' => 'Pasang Baru'])

<main class="pb-page">
    <div class="pb-heading">
        <span class="pb-eyebrow">PELANGGAN BARU</span>
        <h1 class="pb-sr">Pasang Baru</h1>
        <p>Daftarkan pelanggan baru. Akun PPPoE dibuat di MikroTik saat disimpan.</p>
    </div>

    @if(session('status'))
        <div class="pb-alert pb-ok" role="status">{{ session('status') }}</div>
    @endif
    @if(session('warning'))
        <div class="pb-alert pb-warn" role="alert">{{ session('warning') }}</div>
    @endif
    @if($errors->any())
        <div class="pb-alert pb-error" role="alert">
            <ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('pasang-baru.store') }}" id="pb-form" class="pb-card" autocomplete="off">
        @csrf
        <input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}">

        <h2>Data pelanggan</h2>
        <div class="pb-grid">
            <label>Nama
                <input name="name" value="{{ old('name') }}" maxlength="150" required>
            </label>
            <label>WhatsApp
                <input name="whatsapp" value="{{ old('whatsapp') }}" maxlength="30" inputmode="tel">
            </label>
            <label class="pb-wide">Alamat
                <textarea name="address" rows="2" maxlength="5000">{{ old('address') }}</textarea>
            </label>
        </div>

        <h2>Penempatan</h2>
        <div class="pb-grid">
            <label>Wilayah
                <select name="wilayah_id" id="pb-wilayah" required>
                    <option value="">Pilih wilayah</option>
                    @foreach($wilayah as $w)
                        <option value="{{ $w->id }}"
                            @selected((string) old('wilayah_id', $wilayah->count() === 1 ? $w->id : '') === (string) $w->id)>
                            {{ $w->name ?? $w->nama ?? ('Wilayah #'.$w->id) }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label>Router
                <select name="network_router_id" id="pb-router" required>
                    <option value="">Pilih router</option>
                    @foreach($routers as $r)
                        <option value="{{ $r->id }}" @selected((string) old('network_router_id') === (string) $r->id)>
                            {{ $r->name ?? $r->host }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label>Paket internet
                <select name="internet_package_id" id="pb-package" required>
                    <option value="">Pilih paket</option>
                    @foreach($packages as $p)
                        <option value="{{ $p->id }}" @selected((string) old('internet_package_id') === (string) $p->id)>
                            {{ $p->name ?? ('Paket #'.$p->id) }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>

        <h2>Akun PPPoE</h2>
        <div class="pb-grid">
            <label>Username
                <input name="pppoe_username" id="pb-username" value="{{ old('pppoe_username') }}"
                    maxlength="100" required autocapitalize="off" spellcheck="false">
            </label>
            <label>Password
                <span class="pb-pass">
                    <input type="password" name="pppoe_password" id="pb-password"
                        maxlength="128" required autocomplete="new-password">
                    <button type="button" id="pb-pass-toggle">Tampilkan</button>
                </span>
            </label>
        </div>

        <h2>Tagihan</h2>
        <div class="pb-grid">
            <label>Tanggal mulai
                <input type="date" name="started_at" value="{{ old('started_at', $today) }}" required>
            </label>
            <label>Tanggal tagihan
                <input type="number" name="invoice_day" min="1" max="31"
                    value="{{ old('invoice_day') }}"
                    placeholder="{{ $defaultInvoiceDay ? 'Bawaan: '.$defaultInvoiceDay : 'Wajib diisi' }}">
            </label>
            <label>Harga
                <select name="price_mode" id="pb-price-mode">
                    <option value="package" @selected(old('price_mode', 'package') === 'package')>Sesuai paket</option>
                    <option value="custom" @selected(old('price_mode') === 'custom')>Harga khusus</option>
                </select>
            </label>
            <label id="pb-custom-wrap">Harga khusus per bulan
                <input type="number" name="custom_monthly_price" min="0" max="1000000000"
                    value="{{ old('custom_monthly_price') }}">
            </label>
            <label class="pb-check">
                <input type="hidden" name="tax_enabled" value="0">
                <input type="checkbox" name="tax_enabled" value="1" @checked(old('tax_enabled') == '1')>
                Kenakan pajak
            </label>
            <label class="pb-wide">Tagihan pertama
                <select name="first_billing_mode" required>
                    <option value="prorata" @selected(old('first_billing_mode', 'prorata') === 'prorata')>Prorata: tagih hari sejak tanggal mulai sampai sebelum tanggal tagihan berikutnya</option>
                    <option value="full" @selected(old('first_billing_mode') === 'full')>Penuh: tagih satu bulan penuh untuk periode pertama</option>
                    <option value="skip" @selected(old('first_billing_mode') === 'skip')>Lewati: periode pertama tidak ditagih</option>
                </select>
            </label>
            <label class="pb-wide">Catatan
                <textarea name="notes" rows="2" maxlength="5000">{{ old('notes') }}</textarea>
            </label>
        </div>

        <button type="submit" class="pb-submit" id="pb-submit">Simpan dan buat akun di router</button>
    </form>

    @if($pending->isNotEmpty())
        <section class="pb-card">
            <h2>Menunggu aktivasi</h2>
            <ul class="pb-pending">
                @foreach($pending as $c)
                    <li>
                        <div>
                            <strong>{{ $c->name }}</strong>
                            <span>{{ $c->pppoe_username }} &middot; {{ $c->router?->name ?? $c->router?->host }}</span>
                        </div>
                        @if($open->has($c->id))
                            <em>Perlu diperiksa manual ({{ $open[$c->id] }})</em>
                        @else
                            <form method="POST" action="{{ route('pasang-baru.activate', $c) }}"
                                data-pb-retry data-name="{{ $c->pppoe_username }}">
                                @csrf
                                <input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                                <button type="submit">Coba aktifkan lagi</button>
                            </form>
                            <form method="POST" action="{{ route('customers.destroy', $c) }}"
                                data-pb-cancel data-name="{{ $c->name }}">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="mode" value="billing">
                                <input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                                <button type="submit">Batalkan</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</main>

@include('partials.billing-launcher')

<script src="{{ asset('js/billing-launcher.js') }}?v={{ filemtime(public_path('js/billing-launcher.js')) }}" defer></script>

<script>
(function () {
    var cfg = @json($config);
    var w = document.getElementById('pb-wilayah');
    var r = document.getElementById('pb-router');
    var p = document.getElementById('pb-package');

    function member(list, id) {
        return (list || []).indexOf(parseInt(id, 10)) !== -1;
    }

    function syncPackages() {
        Array.prototype.forEach.call(p.options, function (o) {
            if (!o.value) return;
            o.disabled = !r.value || !member(cfg.packageRouters[o.value], r.value);
        });
        if (p.selectedOptions[0] && p.selectedOptions[0].disabled) p.value = '';
    }

    function syncRouters() {
        Array.prototype.forEach.call(r.options, function (o) {
            if (!o.value) return;
            o.disabled = cfg.enforced && !member(cfg.routerWilayah[o.value], w.value);
        });
        if (r.selectedOptions[0] && r.selectedOptions[0].disabled) r.value = '';
        syncPackages();
    }

    w.addEventListener('change', syncRouters);
    r.addEventListener('change', syncPackages);
    syncRouters();

    var mode = document.getElementById('pb-price-mode');
    var wrap = document.getElementById('pb-custom-wrap');
    function syncPrice() { wrap.hidden = mode.value !== 'custom'; }
    mode.addEventListener('change', syncPrice);
    syncPrice();

    var pass = document.getElementById('pb-password');
    var toggle = document.getElementById('pb-pass-toggle');
    toggle.addEventListener('click', function () {
        var show = pass.type === 'password';
        pass.type = show ? 'text' : 'password';
        toggle.textContent = show ? 'Sembunyikan' : 'Tampilkan';
    });

    var form = document.getElementById('pb-form');
    var submit = document.getElementById('pb-submit');
    form.addEventListener('submit', function (e) {
        var user = document.getElementById('pb-username').value;
        if (!window.confirm('Akun PPPoE "' + user + '" akan dibuat di router sekarang. Lanjutkan?')) {
            e.preventDefault();
            return;
        }
        submit.disabled = true;
        submit.textContent = 'Memproses...';
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-pb-cancel]'), function (f) {
        f.addEventListener('submit', function (e) {
            if (!window.confirm('Batalkan dan hapus pelanggan "' + f.dataset.name + '" dari billing? Akun router tidak diubah.')) {
                e.preventDefault();
                return;
            }
            f.querySelector('button').disabled = true;
        });
    });

    Array.prototype.forEach.call(document.querySelectorAll('[data-pb-retry]'), function (f) {
        f.addEventListener('submit', function (e) {
            if (!window.confirm('Buat akun PPPoE "' + f.dataset.name + '" di router sekarang?')) {
                e.preventDefault();
                return;
            }
            f.querySelector('button').disabled = true;
        });
    });
})();
</script>
</body>
</html>
