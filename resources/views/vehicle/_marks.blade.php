{{-- Las DOS marcas distintas del badge (§9), NUNCA fundidas: INSPECCIONADO (checklist aprobado) y
     DOCUMENTOS REVISADOS (alguien vio el papel y lo declaró). Recibe $vehicle. --}}
@php
    $inspState = $vehicle->inspectionState();
    $inspAt    = $vehicle->inspectedAt();
    $docsOk    = $vehicle->docsReviewed();
    $docsAt    = $vehicle->docsReviewedAt();
    $inspChip = [
        'apto'    => ['bg' => '#dcfce7', 'fg' => '#166534', 'ic' => 'shield-check',  't' => 'Inspeccionado'],
        'no_apto' => ['bg' => '#fee2e2', 'fg' => '#991b1b', 'ic' => 'octagon-alert', 't' => 'No apto'],
        'pending' => ['bg' => '#f1f5f9', 'fg' => '#475569', 'ic' => 'clock',         't' => 'Sin inspeccionar'],
    ][$inspState];
@endphp
<div class="d-flex flex-wrap gap-2">
    <span class="insp-tag" style="background:{{ $inspChip['bg'] }};color:{{ $inspChip['fg'] }};">
        @include('componentes._icon', ['name' => $inspChip['ic'], 'label' => null]) {{ $inspChip['t'] }}@if($inspAt) · {{ $inspAt->format('d/m/Y') }}@endif
    </span>
    <span class="insp-tag" style="background:{{ $docsOk ? '#dcfce7' : '#f1f5f9' }};color:{{ $docsOk ? '#166534' : '#475569' }};">
        @include('componentes._icon', ['name' => $docsOk ? 'file-check' : 'file-text', 'label' => null]) Documentos {{ $docsOk ? 'revisados' : 'pendientes' }}@if($docsOk && $docsAt) · {{ $docsAt->format('d/m/Y') }}@endif
    </span>
</div>
