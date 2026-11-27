<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Export Pelanggan | Tagihan dan Management Jaringan</title>
    @foreach(['billing-customers','billing-theme','billing-page-header','billing-launcher','billing-network-header'] as $file)
        <link rel="stylesheet" href="{{ asset('css/'.$file.'.css') }}?v={{ filemtime(public_path('css/'.$file.'.css')) }}">
    @endforeach
    <script src="{{ asset('js/billing-theme.js') }}?v={{ filemtime(public_path('js/billing-theme.js')) }}"></script>
</head>
<body>
@include('partials.billing-page-header', [
    'headerTitle' => 'Export Excel',
    'headerBackUrl' => route('customers.index', $filters),
    'headerBackLabel' => 'Pelanggan',
])

<main class="customer-page" style="max-width:760px">
    <div class="customer-heading">
        <div><h1>Export pelanggan</h1><p>{{ number_format($count, 0, ',', '.') }} pelanggan sesuai filter akan diexport.</p></div>
    </div>

    @if($errors->any())
        <div class="cu-error" role="alert"><ul>
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul></div>
    @endif

    <section class="cu-card">
        <p style="font-size:13px;line-height:1.9;margin-top:0">
            File memuat identitas pelanggan, username, password PPPoE,
            serta rincian harga dan pajak. Data yang diexport mencakup seluruh
            hasil filter, bukan hanya halaman daftar yang sedang terlihat.
        </p>

        <form method="POST" action="{{ route('customer-excel.export') }}">
            @csrf
            @foreach($filters as $key => $value)
                @if($value !== null)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach

            <label style="display:flex;align-items:flex-start;gap:10px;font-size:12px;line-height:1.8">
                <input type="checkbox" name="confirm_password_export" value="1" required style="margin-top:5px">
                <span>Saya memahami file ini berisi password pelanggan dan akan menyimpannya dengan aman.</span>
            </label>

            @if($count > 5000)
                <p class="cu-help">Maksimal 5.000 pelanggan per export. Persempit filter terlebih dahulu.</p>
            @endif

            <div class="cu-actions" style="margin-top:20px;flex-wrap:wrap">
                <a class="cu-button" href="{{ route('customers.index', $filters) }}">Batal</a>
                <button class="cu-button cu-primary" type="submit" @disabled($count === 0 || $count > 5000)>Unduh Excel dengan password</button>
            </div>
        </form>
    </section>
</main>

@include('partials.billing-launcher')
<script src="{{ asset('js/billing-launcher.js') }}?v={{ filemtime(public_path('js/billing-launcher.js')) }}" defer></script>
</body>
</html>
