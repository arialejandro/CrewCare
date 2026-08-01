{{--
    Cintillo "de un vistazo" bajo el hero (negro). Es la BASE reutilizable del resumen rápido de
    cualquier reporte; su fuerza es la JERARQUÍA de la información:

      · LEAD  = el sujeto del reporte (p.ej. la persona lesionada). Bloque PRIMARIO: grande, con
                acento de marca a la izquierda, nombre en mayúsculas + subrótulo (depto./puesto).
      · STATS = datos SECUNDARIOS de lectura rápida (parte del cuerpo, riesgo, estado…). Celdas
                pequeñas y centradas, con color opcional (ok/warn) para severidad/cumplimiento.

    Reutilizable: cualquier vista pasa su propio $lead + $stats. Requiere @include('componentes._doc-hero-styles').

    Parámetros:
      $lead  (array|null)  ['icon' => '', 'label' => '', 'value' => '', 'sub' => '']   (bloque primario)
      $stats (array)       [ ['label' => '', 'value' => '', 'tone' => 'ok|warn|'], ... ]  (secundarios)
                           Se omiten los stats (y el lead) con value vacío/null.
--}}
@php
    $lead    = $lead ?? null;
    $hasLead = is_array($lead) && isset($lead['value']) && trim((string) $lead['value']) !== '';
    $stats   = collect($stats ?? [])
        ->filter(function ($i) { return isset($i['value']) && trim((string) $i['value']) !== ''; })
        ->values();
@endphp
@if($hasLead || $stats->count())
<div class="hero-band">
    @if($hasLead)
    <div class="hb-lead">
        @if(!empty($lead['icon']))<span class="hb-ico" aria-hidden="true">{{ $lead['icon'] }}</span>@endif
        <span class="hb-txt">
            <span class="hb-lbl">{{ $lead['label'] ?? '' }}</span>
            <span class="hb-name">{{ $lead['value'] }}</span>
            @if(!empty($lead['sub']))<span class="hb-sub">{{ $lead['sub'] }}</span>@endif
        </span>
    </div>
    @endif
    @if($stats->count())
    <div class="hb-stats">
        @foreach($stats as $it)
        <div class="hb-cell">
            <span class="hb-lbl">{{ $it['label'] ?? '' }}</span>
            <span class="hb-val {{ $it['tone'] ?? '' }}">{{ $it['value'] }}</span>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endif
