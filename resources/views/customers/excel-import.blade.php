<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Import Excel | Tagihan dan Management Jaringan</title>
    @foreach(['billing-customers','billing-theme','billing-page-header','billing-launcher','billing-network-header'] as $file)
        <link rel="stylesheet" href="{{ asset('css/'.$file.'.css') }}?v={{ filemtime(public_path('css/'.$file.'.css')) }}">
    @endforeach
    <script src="{{ asset('js/billing-theme.js') }}?v={{ filemtime(public_path('js/billing-theme.js')) }}"></script>
    <style>
        .ei-panel { padding:18px;border:1px solid var(--ui-line);border-radius:13px;background:var(--ui-surface);margin-bottom:14px; }
        .ei-panel label { display:block;font-size:12px;margin:0 0 8px; }
        .ei-panel input:not([type=checkbox]) { width:100%;padding:11px;border:1px solid var(--ui-line-strong);border-radius:9px;background:var(--ui-input);color:var(--ui-text);font:inherit;font-size:12px;min-height:43px; }
        .ei-fields { display:grid;grid-template-columns:2fr 1fr;gap:14px; }
        .ei-toolbar { display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:15px 0; }
        .ei-row { display:flex;align-items:flex-start;gap:12px; }
        .ei-row > input { width:17px;height:17px;flex-shrink:0;margin-top:4px; }
        .ei-body { min-width:0;flex:1; }
        .ei-name { font-size:14px;overflow-wrap:anywhere; }
        .ei-meta { font-size:11px;color:var(--ui-muted);line-height:1.9;overflow-wrap:anywhere;margin-top:5px; }
        .ei-errors { margin:8px 0 0;padding-left:17px;font-size:11px;color:var(--ui-danger);line-height:1.8; }
        .ei-summary { display:flex;gap:8px;flex-wrap:wrap;margin:12px 0 16px; }
        .ei-summary span { padding:7px 10px;border:1px solid var(--ui-line);border-radius:8px;background:var(--ui-surface);font-size:11px; }
        .ei-select-all { display:flex;align-items:center;gap:8px;font-size:12px; }
        @media(max-width:600px) { .ei-fields { grid-template-columns:1fr; } .ei-panel { padding:13px; } }
    </style>
</head>
<body>
@include('partials.billing-page-header', [
    'headerTitle' => 'Import Excel',
    'headerBackUrl' => route('customers.index'),
    'headerBackLabel' => 'Pelanggan',
])

<main class="customer-page" style="max-width:950px">
    <div class="customer-heading">
        <div><h1>Import Excel</h1><p>Unggah, periksa, lalu simpan pelanggan baru.</p></div>
    </div>

    @if(session('status'))
        <div class="cu-notice" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="cu-error" role="alert"><ul>
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul></div>
    @endif

    @if(!$batch)
        <form method="POST" action="{{ route('customer-excel-import.preview') }}"
            enctype="multipart/form-data" class="ei-panel ei-submit">
            @csrf
            <div class="ei-fields">
                <div>
                    <label for="ei-file">File pelanggan (.xlsx)</label>
                    <input id="ei-file" type="file" name="file" accept=".xlsx" required>
                </div>
                <div>
                    <label for="ei-start">Tanggal mulai layanan</label>
                    <input id="ei-start" type="date" name="started_at"
                        value="{{ now()->format('Y-m-d') }}" required>
                </div>
                @if(($wilayahChoices ?? collect())->count() > 1)
                <div>
                    <label for="ei-wilayah">Wilayah pelanggan</label>
                    <select id="ei-wilayah" name="wilayah_id">
                        <option value="">Otomatis dari router</option>
                        @foreach($wilayahChoices as $area)
                            <option value="{{ $area->id }}" @selected((string) old('wilayah_id') === (string) $area->id)>{{ $area->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
            </div>
            <p class="cu-help">
                Gunakan template 13 kolom terbaru, maksimal 1.000 pelanggan dan 5 MB.
                Tanggal mulai berlaku untuk pelanggan yang dipilih pada file ini.
                Kolom Total bulanan tidak perlu diisi.
            </p>
            <div class="cu-actions" style="flex-wrap:wrap;margin-top:15px">
                <a class="cu-button" href="{{ route('customer-excel.template') }}">Unduh template</a>
                <button type="submit" class="cu-button cu-primary">Tampilkan preview</button>
            </div>
        </form>
    @else
        <div class="ei-toolbar">
            <span class="cu-muted">Mulai layanan: {{ $startedAt }} · Preview berlaku 20 menit sejak unggah.</span>
            <form method="POST" action="{{ route('customer-excel-import.discard', $batch->id) }}">
                @csrf @method('DELETE')
                <button class="cu-button" type="submit">Selesai / hapus preview</button>
            </form>
        </div>

        <div class="ei-summary">
            <span>Siap: {{ $summary['ready'] }}</span>
            <span>Duplikat: {{ $summary['duplicate'] }}</span>
            <span>Perlu diperbaiki: {{ $summary['invalid'] }}</span>
        </div>

        <form method="POST" class="ei-submit"
            action="{{ route('customer-excel-import.commit', ['batch' => $batch->id, 'page' => $rows->currentPage()]) }}">
            @csrf
            <div class="ei-toolbar">
                <label class="ei-select-all">
                    <input type="checkbox" id="ei-all">
                    Pilih semua baris siap di halaman ini
                </label>
                <span class="cu-muted"><span id="ei-count">0</span> dipilih</span>
            </div>

            @foreach($rows as $row)
                <article class="ei-panel ei-row">
                    <input class="ei-pick" type="checkbox" name="selected[]"
                        id="ei-row-{{ $row['index'] }}" value="{{ $row['index'] }}"
                        @disabled(!$row['ready'])>
                    <div class="ei-body">
                        <label class="ei-name" for="ei-row-{{ $row['index'] }}">
                            {{ $row['name'] ?: 'Nama belum diisi' }}
                        </label>
                        <div class="ei-meta">
                            Baris Excel {{ $row['row_number'] }} ·
                            {{ $row['router'] }} · {{ $row['package'] }}<br>
                            Akun/IP: {{ $row['identity'] ?: 'Belum valid' }} ·
                            Password: {{ $row['password_available'] ? 'Tersedia, tidak ditampilkan' : 'Tidak diisi' }}<br>
                            {{ $row['custom'] ? 'Harga khusus' : 'Ikuti harga paket' }} ·
                            Total: {{ $row['total'] === null ? 'Belum dapat dihitung' : 'Rp'.number_format($row['total'], 0, ',', '.') }} ·
                            Pajak: {{ $row['tax'] }}<br>
                            Tanggal tagihan: {{ $row['day'] ?: 'Belum diatur' }} ·
                            Status: {{ $row['status'] === 'active' ? 'Aktif' : ($row['status'] === 'stopped' ? 'Berhenti' : 'Belum valid') }}
                        </div>
                        @if($row['duplicate'])
                            <p class="cu-help">Sudah terdaftar pada router yang sama. Dilewati, data lama tidak diubah.</p>
                        @endif
                        @if($row['errors'])
                            <ul class="ei-errors">
                                @foreach($row['errors'] as $error)<li>{{ $error }}</li>@endforeach
                            </ul>
                        @endif
                    </div>
                </article>
            @endforeach

            <div class="ei-toolbar">
                <span class="cu-muted">Hanya baris terpilih pada halaman ini yang disimpan.</span>
                <button id="ei-save" class="cu-button cu-primary" type="submit" disabled>Import baris terpilih</button>
            </div>
        </form>

        <div class="cu-pagination">
            @if($rows->previousPageUrl())<a class="cu-button" href="{{ $rows->previousPageUrl() }}">Sebelumnya</a>@endif
            <span>Halaman {{ $rows->currentPage() }} / {{ $rows->lastPage() }}</span>
            @if($rows->nextPageUrl())<a class="cu-button" href="{{ $rows->nextPageUrl() }}">Berikutnya</a>@endif
        </div>
        <p class="cu-help">
            Simpan pilihan sebelum berpindah halaman. Untuk memperbaiki isian, ubah file lalu unggah ulang.
            Password tidak ditampilkan pada preview.
        </p>
    @endif
</main>

@include('partials.billing-launcher')
<script src="{{ asset('js/billing-launcher.js') }}?v={{ filemtime(public_path('js/billing-launcher.js')) }}" defer></script>
<script src="{{ asset('js/billing-excel-import.js') }}?v=1" defer></script>
</body>
</html>
