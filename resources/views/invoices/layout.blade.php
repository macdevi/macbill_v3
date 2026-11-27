<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Tagihan') | Tagihan dan Management Jaringan</title>
    @foreach(['billing-customers','billing-theme','billing-page-header','billing-launcher','billing-network-header','billing-invoices'] as $file)
        <link rel="stylesheet" href="{{ asset('css/'.$file.'.css') }}?v={{ filemtime(public_path('css/'.$file.'.css')) }}">
    @endforeach
    <script src="{{ asset('js/billing-theme.js') }}?v={{ filemtime(public_path('js/billing-theme.js')) }}"></script>
</head>
<body>
@include('partials.billing-page-header', ['headerTitle' => trim($__env->yieldContent('title', 'Tagihan'))])
<main class="customer-page iv-page">
    @if(session('status'))
        <div class="cu-notice" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="cu-error" role="alert"><ul>
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul></div>
    @endif
    @yield('content')
</main>
@include('partials.billing-launcher')
<script src="{{ asset('js/billing-launcher.js') }}?v={{ filemtime(public_path('js/billing-launcher.js')) }}" defer></script>
</body>
</html>
