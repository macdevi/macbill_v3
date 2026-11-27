@php
    $statusAction = $customer->relationLoaded('networkAction')
        ? $customer->networkAction
        : null;

    $statusPause = $customer->relationLoaded('billingPause')
        ? $customer->billingPause
        : null;

    if ($statusAction) {
        $serviceLabel = $statusAction->status === 'uncertain'
            ? 'Perlu pemeriksaan'
            : 'Sedang diproses';
        $serviceClass = 'cu-life-pending';
    } elseif ($customer->service_status === 'stopped') {
        $serviceLabel = 'Berhenti';
        $serviceClass = 'cu-life-stopped';
    } elseif ($statusPause) {
        $serviceLabel = 'Terisolir';
        $serviceClass = 'cu-life-isolated';
    } else {
        $serviceLabel = 'Aktif';
        $serviceClass = 'is-active';
    }
@endphp
<span class="cu-status {{ $serviceClass }}">Layanan: {{ $serviceLabel }}</span>
