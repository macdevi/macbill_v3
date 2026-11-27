<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Import MikroTik | Tagihan dan Management Jaringan</title>

    <script>
        try {
            if (window.parent !== window && window.frameElement?.id === 'cu-pppoe-frame') {
                document.documentElement.classList.add('pi-embedded');
            }
        } catch (_) {}
    </script>

    @foreach(['billing-customers','billing-theme','billing-page-header','billing-network-header'] as $file)
        <link rel="stylesheet" href="{{ asset('css/'.$file.'.css') }}?v={{ filemtime(public_path('css/'.$file.'.css')) }}">
    @endforeach

    <link rel="stylesheet" href="{{ asset('css/billing-pppoe-import.css') }}?v={{ filemtime(public_path('css/billing-pppoe-import.css')) }}">
    <script src="{{ asset('js/billing-theme.js') }}?v={{ filemtime(public_path('js/billing-theme.js')) }}"></script>
</head>
<body>
<div class="pi-standalone">
    @include('partials.billing-page-header', [
        'headerTitle' => 'Import MikroTik',
        'headerBackUrl' => route('customers.index'),
        'headerBackLabel' => 'Pelanggan',
    ])
</div>

<main class="customer-page pi-page">
    @if(session('status'))
        <div class="cu-notice" role="status">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="cu-error" role="alert">
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if(!$batch)
        <form method="POST" action="{{ route('customer-pppoe.preview') }}" class="pi-panel pi-submit">
            @csrf
            <label for="pi-router">Router sumber</label>
            <div class="pi-router-line">
                <select id="pi-router" name="network_router_id" required>
                    <option value="">Pilih router</option>
                    @foreach($routers as $router)
                        <option value="{{ $router->id }}" @selected((string) old('network_router_id') === (string) $router->id)>
                            {{ $router->name }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="cu-button cu-primary">Muat akun</button>
            </div>
            <p class="cu-help">Hanya membaca akun router. Belum ada pelanggan yang disimpan.</p>
        </form>
    @else
        @if($packages->isEmpty())
            <div class="cu-error">
                Belum ada paket PPPoE aktif yang dipetakan ke router ini.
                Siapkan pemetaan paket, lalu muat ulang preview.
            </div>
        @endif

        <form method="POST" class="pi-submit" action="{{ route('customer-pppoe.commit', $batch->id) }}">
            @csrf



            <div class="pi-toolbar">
                <span class="pi-summary">Maksimal 500 pelanggan per impor. Pilih semua untuk memilih akun yang tersedia.</span>
                <span class="pi-summary"><span id="pi-count">0</span> dipilih</span>
            </div>

            <div class="pi-table-wrap">
                <table class="pi-table">
                    <thead>
                        <tr>
                            <th scope="col">
                                <label class="pi-select-all">
                                    <input type="checkbox" id="pi-pick-all"
                                        aria-label="Pilih semua akun yang dapat diimpor, maksimal 500">
                                    <span>Pilih semua</span>
                                </label>
                            </th>
                            <th scope="col">Nama pelanggan / username</th>
                            <th scope="col">Paket billing</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        @php
                            $i = $row['index'];
                            $isolationSource = $row['isolation_source'];
                            $blocked = !$row['valid_username']
                                || $packages->isEmpty()
                                || ($isolationSource && (
                                    !$isolationImportReady || $row['disabled']
                                ));
                            $checked = !$blocked && old("items.$i.selected") == '1';
                        @endphp
                        <tr class="pi-row">
                            <td>
                                <input type="checkbox" class="pi-pick"
                                    id="pi-pick-{{ $i }}"
                                    aria-label="Pilih {{ $row['username'] }}"
                                    name="items[{{ $i }}][selected]" value="1"
                                    @disabled($blocked) @checked($checked)>
                            </td>
                            <td>
                                <input class="pi-editable"
                                    aria-label="Nama pelanggan {{ $row['username'] }}"
                                    name="items[{{ $i }}][name]"
                                    value="{{ old("items.$i.name", $row['name']) }}"
                                    maxlength="150" required @disabled(!$checked)>

                                <small>{{ $row['username'] }}</small>

                                @if(!$row['valid_username'])
                                    <small class="pi-warning">Format atau panjang username belum didukung.</small>
                                @endif
                            </td>
                            <td>
                                <select class="pi-editable"
                                    aria-label="Paket billing {{ $row['username'] }}"
                                    name="items[{{ $i }}][package_id]" required @disabled(!$checked)>
                                    <option value="">Pilih paket</option>
                                    @foreach($packages as $package)
                                        <option value="{{ $package->id }}"
                                            @selected((string) old("items.$i.package_id", $row['suggested_package']) === (string) $package->id)>
                                            {{ $package->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="pi-empty">
                            Tidak ada akun PPPoE/any yang belum terdaftar pada router ini.
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if($rows->isNotEmpty())
                <div class="pi-toolbar pi-footer">
                    @if($wilayahChoices->isNotEmpty())
                        <div class="pi-billing-control">
                            <label for="pi-wilayah">Wilayah pelanggan</label>
                            <select id="pi-wilayah" name="wilayah_id" required>
                                <option value="">Pilih wilayah</option>
                                @foreach($wilayahChoices as $area)
                                    <option value="{{ $area->id }}" @selected((string) old('wilayah_id') === (string) $area->id)>{{ $area->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="pi-billing-control">
                        <label for="pi-billing-day">Tanggal tagihan</label>
                        <select id="pi-billing-day" name="invoice_day">
                            <option value="">Ikuti bawaan{{ $settings->default_invoice_day ? ' · tanggal '.$settings->default_invoice_day : ' · belum diatur' }}</option>
                            @for($day=1;$day<=31;$day++)
                                <option value="{{ $day }}" @selected((string) old('invoice_day') === (string) $day)>
                                    Khusus tanggal {{ $day }}
                                </option>
                            @endfor
                        </select>
                    </div>
                    <button type="submit" class="cu-button cu-primary" id="pi-import" disabled>
                        Import 0 pelanggan
                    </button>
                </div>
            @endif
        </form>
    @endif
</main>

<script src="{{ asset('js/billing-pppoe-import.js') }}?v={{ filemtime(public_path('js/billing-pppoe-import.js')) }}" defer></script>
</body>
</html>
