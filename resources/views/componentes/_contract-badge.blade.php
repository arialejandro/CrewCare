{{--
    _contract-badge — marcador de ESTADO DE CONTRATO de una persona, junto a su nombre en el Crew
    List / buscador / Dados de baja. Recibe $cs de App\Support\ContractStatus::forUserIds():
      · cobertura: state (sin_contrato|incompleto|ok) + label + detail (qué falta del trato).
      · vigencia : exp_state (vence|sin_fecha|covered|na) + exp_label (con la fecha si vence).
    Pinta hasta DOS chips (cobertura y, si aplica, vigencia). Estado por TEXTO + icono, nunca solo
    color. CSP-safe: sin <script> ni on*=. Degrada limpio si no llega $cs.
--}}
@php
    $cs  = $cs ?? null;
    $cov = [
        \App\Support\ContractStatus::SIN_CONTRATO => ['cls' => 'cc-cmark--none',    'icon' => 'x-circle'],
        \App\Support\ContractStatus::INCOMPLETO   => ['cls' => 'cc-cmark--partial', 'icon' => 'alert-triangle'],
        \App\Support\ContractStatus::OK           => ['cls' => 'cc-cmark--ok',      'icon' => 'check-circle'],
    ];
    $exp = [
        \App\Support\ContractStatus::EXP_VENCE     => ['cls' => 'cc-cmark--vence',   'icon' => 'clock'],
        \App\Support\ContractStatus::EXP_SIN_FECHA => ['cls' => 'cc-cmark--nofecha', 'icon' => 'calendar'],
    ];
@endphp
@if($cs && isset($cov[$cs['state'] ?? '']))
    @php $m = $cov[$cs['state']]; @endphp
    <span class="cc-cmark {{ $m['cls'] }}" @if(!empty($cs['detail'])) title="{{ $cs['detail'] }}" @endif>
        @include('componentes._icon', ['name' => $m['icon'], 'class' => 'cc-ico', 'label' => null])
        <span>{{ $cs['label'] }}</span>
    </span>
@endif
@if($cs && isset($exp[$cs['exp_state'] ?? '']))
    @php $e = $exp[$cs['exp_state']]; @endphp
    <span class="cc-cmark {{ $e['cls'] }}" title="{{ __('El contrato no alcanza el último día de rodaje de su unidad.') }}">
        @include('componentes._icon', ['name' => $e['icon'], 'class' => 'cc-ico', 'label' => null])
        <span>{{ $cs['exp_label'] }}</span>
    </span>
@endif
