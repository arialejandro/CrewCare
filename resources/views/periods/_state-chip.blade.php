{{-- Chip del estado de recepción de un documento (derivado por PayeePackage). REGLA: el estado
     dice RECIBIDO / FALTA, nunca "vigente" ni "cumple" — describe la RECEPCIÓN, no el cumplimiento.
     $state ∈ missing | received | not_positive | expired --}}
@php
    $meta = [
        \App\Support\PayeePackage::ST_RECEIVED     => ['badge' => 'text-bg-success',       'label' => __('Recibido')],
        \App\Support\PayeePackage::ST_MISSING      => ['badge' => 'text-bg-light border text-muted', 'label' => __('Falta')],
        \App\Support\PayeePackage::ST_NOT_POSITIVE => ['badge' => 'text-bg-warning',       'label' => __('Recibido · no positiva')],
        \App\Support\PayeePackage::ST_EXPIRED      => ['badge' => 'text-bg-warning',       'label' => __('Recibido · caducado')],
    ];
    $m = $meta[$state] ?? ['badge' => 'text-bg-light border text-muted', 'label' => $state];
@endphp
<span class="badge {{ $m['badge'] }}" style="font-weight:600">{{ $m['label'] }}</span>
