{{--
    _contract-badge — marcador de ESTADO DE CONTRATO de una persona, junto a su nombre en el Crew
    List / buscador / Dados de baja. Recibe $cs = ['state','label','detail'] de App\Support\ContractStatus.
    Estado por TEXTO + icono (nunca solo color). CSP-safe: sin <script> ni on*=. El detalle (qué falta
    del trato) va como tooltip nativo. No pinta nada si no llega $cs (degrada limpio).
--}}
@php
    $cs  = $cs ?? null;
    $map = [
        \App\Support\ContractStatus::SIN_CONTRATO => ['cls' => 'cc-cmark--none',    'icon' => 'x-circle'],
        \App\Support\ContractStatus::INCOMPLETO   => ['cls' => 'cc-cmark--partial', 'icon' => 'alert-triangle'],
        \App\Support\ContractStatus::OK           => ['cls' => 'cc-cmark--ok',      'icon' => 'check-circle'],
    ];
@endphp
@if($cs && isset($map[$cs['state']]))
    @php $m = $map[$cs['state']]; @endphp
    <span class="cc-cmark {{ $m['cls'] }}" @if(!empty($cs['detail'])) title="{{ $cs['detail'] }}" @endif>
        @include('componentes._icon', ['name' => $m['icon'], 'class' => 'cc-ico', 'label' => null])
        <span>{{ $cs['label'] }}</span>
    </span>
@endif
