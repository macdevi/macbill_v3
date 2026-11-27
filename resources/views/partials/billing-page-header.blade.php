<header class="topbar billing-page-header billing-network-header">
    <div class="billing-network-packets" aria-hidden="true">
        <span class="network-packet network-packet-one"></span>
        <span class="network-packet network-packet-two"></span>
        <span class="network-packet network-packet-three"></span>
        <span class="network-packet network-packet-four"></span>
    </div>

    <div class="billing-header-inner">
        <div class="billing-header-start">
            @php($dashboardActive = request()->routeIs('dashboard'))
            <a class="billing-header-dashboard {{ $dashboardActive ? 'is-active' : '' }}"
                style="--tone: #60a5fa"
                href="{{ route('dashboard') }}"
                @if($dashboardActive) aria-current="page" @endif>
                <svg width="20" height="20" viewBox="0 0 24 24"
                    fill="none" stroke="currentColor" stroke-width="1.6"
                    stroke-linecap="round" stroke-linejoin="round"
                    aria-hidden="true">
                    <path d="M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z"/>
                </svg>
                <span>Dashboard</span>
            </a>
        </div>

        <div class="billing-header-end">
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
