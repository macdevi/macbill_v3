<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Riwayat Pelanggan | Tagihan dan Management Jaringan</title>
    @foreach(['billing-customers','billing-theme','billing-page-header','billing-launcher','billing-network-header'] as $file)
        <link rel="stylesheet" href="{{ asset('css/'.$file.'.css') }}?v={{ filemtime(public_path('css/'.$file.'.css')) }}">
    @endforeach
    <script src="{{ asset('js/billing-theme.js') }}?v={{ filemtime(public_path('js/billing-theme.js')) }}"></script>
    <style>
        .ch-entry {
            padding:17px;
            margin-bottom:13px;
            border:1px solid var(--ui-line);
            border-radius:12px;
            background:var(--ui-surface);
        }
        .ch-top {
            display:flex;
            justify-content:space-between;
            gap:10px;
            flex-wrap:wrap;
            font-size:12px;
            line-height:1.7;
        }
        .ch-meta {
            margin:5px 0 13px;
            color:var(--ui-muted);
            font-size:11px;
            overflow-wrap:anywhere;
        }
        .ch-change {
            display:grid;
            grid-template-columns:155px 1fr 1fr;
            gap:12px;
            padding:11px 0;
            border-top:1px solid var(--ui-line);
            font-size:12px;
            line-height:1.7;
            overflow-wrap:anywhere;
        }
        .ch-change small {
            display:block;
            color:var(--ui-muted);
            font-size:10px;
        }
        .ch-empty {
            padding:28px 17px;
            border:1px solid var(--ui-line);
            border-radius:12px;
            background:var(--ui-surface);
            font-size:12px;
            line-height:1.8;
            color:var(--ui-muted);
        }
        @media(max-width:600px) {
            .ch-entry { padding:13px; }
            .ch-change { grid-template-columns:1fr 1fr;gap:7px 12px; }
            .ch-label { grid-column:1/-1; }
        }
    </style>
</head>
<body>
@include('partials.billing-page-header', [
    'headerTitle' => 'Riwayat pelanggan',
    'headerBackUrl' => route('customers.index'),
    'headerBackLabel' => 'Pelanggan',
])

<main class="customer-page" style="max-width:950px">
    <div class="customer-heading">
        <div>
            <h1>Riwayat perubahan</h1>
            <p>{{ $customer->name }} · {{ $customer->code }}</p>
        </div>
    </div>

    <p class="cu-help" style="margin-bottom:17px">
        Pencatatan dimulai sejak fitur diaktifkan. Waktu mengikuti zona waktu aplikasi.
        Password dan data koneksi tidak ditampilkan pada riwayat ini.
    </p>

    @forelse($entries as $entry)
        <article class="ch-entry">
            <div class="ch-top">
                <span>{{ $entry->event === 'created' ? 'Pelanggan ditambahkan' : 'Data pelanggan diperbarui' }}</span>
                <time datetime="{{ $entry->created_at->toIso8601String() }}">
                    {{ $entry->created_at->format('d/m/Y H:i:s') }}
                </time>
            </div>
            <p class="ch-meta">
                Oleh: {{ $entry->actor_name }}
                · Sumber: {{ $entry->source }}
            </p>

            @foreach($entry->changes as $change)
                <div class="ch-change">
                    <span class="ch-label">{{ $change['label'] }}</span>
                    <span>
                        <small>Sebelum</small>
                        {{ $entry->event === 'created' ? 'Belum ada pelanggan' : $change['before'] }}
                    </span>
                    <span>
                        <small>Sesudah</small>
                        {{ $change['after'] }}
                    </span>
                </div>
            @endforeach
        </article>
    @empty
        <div class="ch-empty">
            Belum ada perubahan yang tercatat untuk pelanggan ini.
            Data pelanggan yang sudah ada tidak dibuatkan riwayat lama.
        </div>
    @endforelse

    <div class="cu-pagination">
        @if($entries->previousPageUrl())
            <a class="cu-button" href="{{ $entries->previousPageUrl() }}">Sebelumnya</a>
        @endif
        <span>Halaman {{ $entries->currentPage() }} / {{ $entries->lastPage() }}</span>
        @if($entries->nextPageUrl())
            <a class="cu-button" href="{{ $entries->nextPageUrl() }}">Berikutnya</a>
        @endif
    </div>
</main>

@include('partials.billing-launcher')
<script src="{{ asset('js/billing-launcher.js') }}?v={{ filemtime(public_path('js/billing-launcher.js')) }}" defer></script>
</body>
</html>
