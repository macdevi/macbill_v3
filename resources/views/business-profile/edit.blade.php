<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1728">
    <title>Profil Usaha</title>

    @foreach([
        'billing-dashboard',
        'billing-theme',
        'billing-page-header',
        'billing-launcher',
        'billing-network-header',
    ] as $file)
        @if(is_file(public_path('css/'.$file.'.css')))
            <link rel="stylesheet"
                href="{{ asset('css/'.$file.'.css') }}?v={{ filemtime(public_path('css/'.$file.'.css')) }}">
        @endif
    @endforeach

    @if(is_file(public_path('js/billing-theme.js')))
        <script src="{{ asset('js/billing-theme.js') }}?v={{ filemtime(public_path('js/billing-theme.js')) }}"></script>
    @endif

    <style>
        .business-profile-page {
            box-sizing:border-box;
            width:min(680px,100%);
            margin:24px auto;
            padding:0 16px;
        }
        .business-profile-card {
            padding:24px;
            border:1px solid var(--ui-line,rgba(148,163,184,.25));
            border-radius:18px;
            background:var(--ui-surface,#111e32);
            color:inherit;
        }
        .business-profile-card h1 {
            margin:0 0 8px;
            font-size:22px;
            color:inherit;
        }
        .business-profile-description {
            margin:0 0 24px;
            font-size:13px;
            line-height:1.6;
            opacity:.75;
        }
        .business-profile-card label {
            display:block;
            margin-bottom:8px;
            font-size:13px;
            font-weight:600;
        }
        .business-profile-card input {
            box-sizing:border-box;
            width:100%;
            padding:12px;
            border:1px solid var(--ui-line,rgba(148,163,184,.35));
            border-radius:10px;
            background:var(--ui-bg,#0b1728);
            color:inherit;
            font:inherit;
        }
        .business-profile-card input:focus-visible {
            outline:2px solid #60a5fa;
            outline-offset:2px;
        }
        .business-profile-help {
            margin:8px 0 0;
            font-size:12px;
            line-height:1.5;
            opacity:.7;
        }
        .business-profile-error {
            margin:8px 0 0;
            color:#f87171;
            font-size:12px;
        }
        .business-profile-success {
            margin-bottom:18px;
            padding:12px;
            border:1px solid rgba(34,197,94,.35);
            border-radius:10px;
            background:rgba(34,197,94,.10);
            color:inherit;
            font-size:13px;
        }
        .business-profile-actions {
            display:flex;
            align-items:center;
            flex-wrap:wrap;
            gap:14px;
            margin-top:24px;
        }
        .business-profile-actions button {
            padding:11px 18px;
            border:0;
            border-radius:10px;
            background:#2563eb;
            color:#fff;
            font:inherit;
            font-size:13px;
            font-weight:600;
            cursor:pointer;
        }
        .business-profile-actions a {
            color:inherit;
            font-size:13px;
        }
        @media(max-width:600px) {
            .business-profile-card { padding:18px; }
        }
    </style>
</head>
<body class="billing-launcher-layout">
@include('partials.billing-page-header', [
    'headerTitle' => 'Profil Usaha',
])

<main class="business-profile-page">
    @if(session('status'))
        <div class="business-profile-success" role="status">
            {{ session('status') }}
        </div>
    @endif

    <section class="business-profile-card">
        <h1>Profil Usaha</h1>
        <p class="business-profile-description">
            Kelola nama usaha sebagai identitas bisnis di aplikasi.
        </p>

        <form method="POST" action="{{ route('business-profile.update') }}">
            @csrf
            @method('PUT')

            <label for="business-name">Nama usaha</label>
            <input
                id="business-name"
                type="text"
                name="business_name"
                value="{{ old('business_name', $settings->business_name) }}"
                maxlength="100"
                required
                aria-describedby="business-name-help{{ $errors->has('business_name') ? ' business-name-error' : '' }}"
                @error('business_name') aria-invalid="true" @enderror>

            <p id="business-name-help" class="business-profile-help">
                Maksimal 100 karakter. Gunakan nama usaha yang ingin ditampilkan.
            </p>

            @error('business_name')
                <p id="business-name-error" class="business-profile-error">
                    {{ $message }}
                </p>
            @enderror

            <div class="business-profile-actions">
                <button type="submit">Simpan Profil Usaha</button>
                <a href="{{ route('dashboard') }}">Kembali ke Dashboard</a>
            </div>
        </form>
    </section>
</main>
</body>
</html>
