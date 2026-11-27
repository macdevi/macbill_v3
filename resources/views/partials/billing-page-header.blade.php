@php
    $headerTitle = $headerTitle ?? 'Ruang kerja';
    $headerBackUrl = $headerBackUrl ?? route('dashboard');
    $headerBackLabel = $headerBackLabel ?? 'Dashboard';
@endphp

<header class="topbar billing-page-header billing-network-header">
    <div class="billing-network-packets" aria-hidden="true">
        <span class="network-packet network-packet-one"></span>
        <span class="network-packet network-packet-two"></span>
        <span class="network-packet network-packet-three"></span>
        <span class="network-packet network-packet-four"></span>
    </div>

    <div class="billing-header-inner">
        <div class="billing-header-start">
            <a class="billing-header-back"
                href="{{ $headerBackUrl }}"
                aria-label="Kembali ke {{ $headerBackLabel }}"
                title="Kembali ke {{ $headerBackLabel }}">
                <svg width="19" height="19" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round"
                    aria-hidden="true">
                    <path d="m12 5-7 7 7 7M5 12h14"/>
                </svg>
            </a>

            <div class="billing-header-location">
                <a class="billing-header-parent" href="{{ $headerBackUrl }}">
                    {{ $headerBackLabel }}
                </a>
                <span class="billing-header-title">{{ $headerTitle }}</span>
            </div>
        </div>

        <div class="billing-header-end">
            @include('partials.billing-launcher-button')
            @can('manage-system')
                @can('manage-users')
                <a class="billing-header-shortcut"
                    href="{{ route('business-profile.edit') }}"
                    title="Profil Usaha"
                    aria-label="Profil Usaha">
                    <svg width="17" height="17" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="1.7"
                        stroke-linecap="round" stroke-linejoin="round"
                        aria-hidden="true">
                        <path d="M3 10h18L19 4H5l-2 6Z"/>
                        <path d="M5 10v10h14V10M9 20v-6h6v6"/>
                    </svg>
                    <span>Profil Usaha</span>
                </a>
                @endcan
                @if(request()->routeIs('packages.*'))
                    <a class="billing-header-shortcut" href="{{ route('routers.index') }}">
                        <svg width="17" height="17" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="1.7"
                            stroke-linecap="round" stroke-linejoin="round"
                            aria-hidden="true">
                            <rect x="3" y="11" width="18" height="9" rx="3"/>
                            <path d="M7 11V4M17 11V4M7 16h1M11 16h1M17 16h1"/>
                        </svg>
                        <span>Router MikroTik</span>
                    </a>
                @elseif(request()->routeIs('routers.*'))
                    <a class="billing-header-shortcut" href="{{ route('packages.index') }}">
                        <svg width="17" height="17" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="1.7"
                            stroke-linecap="round" stroke-linejoin="round"
                            aria-hidden="true">
                            <rect x="4" y="4" width="16" height="16" rx="4"/>
                            <path d="M8 9h8M8 13h5M8 17h3"/>
                        </svg>
                        <span>Paket Internet</span>
                    </a>
                @endif
            @endcan

            <div class="account billing-header-account"
                aria-label="Pilihan tampilan"></div>
        </div>
    </div>
</header>
