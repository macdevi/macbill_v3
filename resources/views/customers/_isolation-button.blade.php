@if($manualIsolationAvailable ?? false)
    @php
        $isoAction = $customer->networkAction;
        $isoPause = $customer->billingPause;
        $isoSupported = $customer->service_type === 'pppoe'
            && $customer->service_status === 'active'
            && $customer->router?->isolation_method === 'ppp_profile';
    @endphp

    @if($isoAction)
        <span class="cu-iso-pending" role="status">
            Tindakan #{{ $isoAction->id }}:
            {{ $isoAction->status === 'uncertain' ? 'perlu pemeriksaan' : 'belum selesai' }}
        </span>
    @elseif($isoSupported)
        <button type="button"
            class="cu-button {{ $isoPause ? 'cu-iso-restore' : 'cu-iso-stop' }}"
            data-iso-open
            data-iso-url="{{ route('customers.isolation.store', $customer) }}"
            data-iso-kind="{{ $isoPause ? 'restore' : 'isolate' }}"
            data-iso-pause="{{ $isoPause?->id }}"
            data-iso-key="{{ (string) \Illuminate\Support\Str::uuid() }}"
            data-iso-version="{{ \App\Services\CustomerIsolation::version($customer) }}"
            data-iso-name="{{ $customer->name }}"
            data-iso-code="{{ $customer->code }}"
            data-iso-account="{{ $customer->pppoe_username }}"
            data-iso-router="{{ $customer->router->name }}">
            {{ $isoPause ? 'Buka isolir' : 'Isolir' }}
        </button>
    @else
        <button type="button" class="cu-button" disabled
            title="Tindakan tersedia untuk pelanggan aktif PPPoE dengan metode isolir profil.">
            Isolir
        </button>
    @endif
@endif
