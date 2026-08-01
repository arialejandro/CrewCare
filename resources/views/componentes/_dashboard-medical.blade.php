{{--
    _dashboard-medical.blade.php — PANEL MÉDICO del tablero (2026-07-24).

    Responde al encargo del owner: "el home del panel no debería mostrar lo que muestra perfil…
    en el apartado médico debería mostrar los datos relevantes de atenciones, medicamentos
    usados, etc. Que sea un dashboard con data viva real."

    DE DÓNDE SALEN LOS NÚMEROS: HomeController@medicalPanel, sobre cmedic::visibleTo($user).
    Aquí NO se consulta la base ni se calcula nada: la vista sólo pinta lo que recibe.

    QUÉ SE PROTEGE Y QUÉ NO:
      · No hay una sola fila por persona. Ni nombres, ni diagnósticos, ni observaciones: son
        conteos. El expediente y la bitácora siguen siendo el único lugar donde se lee lo clínico.
      · El bloque de MEDICAMENTOS sólo llega si el controlador lo autorizó (`medical.materials`,
        el mismo permiso que abre el conteo). La vista no vuelve a decidirlo, sólo lo respeta.
      · Si lo que se muestra viene filtrado por médico, se dice con una etiqueta. Un número
        parcial sin avisar es peor que no mostrarlo.

    Recibe:
      $m     (array) datos del panel.
      $ancho (bool)  true → ocupa las 3 columnas (cuando no hay gráfica que lo acompañe).
--}}
@php
    $serie   = isset($m['serie']) && is_array($m['serie']) ? $m['serie'] : [];
    $maxSem  = count($serie) ? max($serie) : 0;
    $topMeds = isset($m['top_meds']) ? $m['top_meds'] : [];
    $manejo  = isset($m['manejo']) ? $m['manejo'] : [];
    $vacio   = (int) $m['consultas'] === 0;
@endphp

<div class="cc-panel p-4 sm:p-5 {{ $ancho ? 'lg:col-span-3' : '' }}">

    <div class="flex flex-wrap items-start justify-between gap-2 mb-4">
        <h2 class="cc-panel__title font-poster text-base sm:text-lg flex items-center gap-2">
            <span class="cc-accent-bar"></span>
            {{ __('dashboard.med_title') }}
        </h2>
        <div class="flex flex-wrap items-center gap-2">
            @if(!empty($m['solo_propio']))
                <span class="cc-med__tag">{{ __('dashboard.med_scope_own') }}</span>
            @endif
            <span class="cc-panel__note text-[11px]">{{ __('dashboard.med_window') }}</span>
        </div>
    </div>

    @if($vacio)
        {{-- Vacío HONESTO: cero atenciones se dice, no se disimula con una cifra de relleno. --}}
        <div class="cc-empty flex flex-col items-center justify-center text-center gap-2 py-8">
            <span class="cc-empty__ico">
                @include('componentes._icon', ['name' => 'stethoscope', 'class' => 'cc-ico-24', 'label' => null])
            </span>
            <p class="text-sm font-semibold">{{ __('dashboard.med_empty') }}</p>
            <p class="text-xs max-w-[28ch]">{{ __('dashboard.med_empty_hint') }}</p>
        </div>
    @else

        {{-- Cifras. tabular-nums para que las columnas no bailen al cambiar de valor. --}}
        <div class="cc-med__stats">
            <div class="cc-med__stat">
                <span class="cc-med__num">{{ $m['consultas'] }}</span>
                <span class="cc-med__cap">{{ __('dashboard.med_consults') }}</span>
            </div>
            <div class="cc-med__stat">
                <span class="cc-med__num">{{ $m['hoy'] }}</span>
                <span class="cc-med__cap">{{ __('dashboard.med_today') }}</span>
            </div>
            <div class="cc-med__stat">
                <span class="cc-med__num">{{ $m['personas'] }}</span>
                <span class="cc-med__cap">{{ __('dashboard.med_people') }}</span>
            </div>
            @if((int) $m['sin_exp'] > 0)
                {{-- Sólo aparece cuando hay algo que perseguir: un cero permanente en ámbar
                     enseña a ignorar el color, y este número existe para que alguien actúe. --}}
                <div class="cc-med__stat cc-med__stat--warn" title="{{ __('dashboard.med_no_record_hint') }}">
                    <span class="cc-med__num">{{ $m['sin_exp'] }}</span>
                    <span class="cc-med__cap">{{ __('dashboard.med_no_record') }}</span>
                </div>
            @endif
        </div>

        @if($maxSem > 0)
            {{-- Serie semanal REAL (8 semanas, la última a la derecha). Barras en CSS, sin JS ni
                 canvas: se ven igual al imprimir y no dependen de que cargue una librería. --}}
            <div class="cc-med__bars" role="img"
                 aria-label="{{ __('dashboard.med_weekly') }}: {{ implode(', ', $serie) }}">
                @foreach($serie as $n)
                    {{-- La semana en CERO se dibuja como una raya al ras, no como una barrita
                         mínima: un muñón visible se lee como "poca actividad" y lo que hubo fue
                         ninguna. El elemento se conserva para que las semanas no se muevan. --}}
                    <span class="cc-med__bar{{ $n > 0 ? '' : ' cc-med__bar--cero' }}"
                          style="--h: {{ $n > 0 ? max(14, (int) round($n / $maxSem * 100)) : 0 }}%"
                          title="{{ $n }}"></span>
                @endforeach
            </div>
            <p class="cc-med__foot">
                {{ __('dashboard.med_weekly') }}
                @if(!empty($m['ultima']))
                    · {{ __('dashboard.med_last') }}: {{ $m['ultima']->format('d/m/Y') }}
                @endif
            </p>
        @endif

        <div class="cc-med__split">
            @if(!empty($m['puede_meds']) && count($topMeds))
                <div>
                    <h3 class="cc-med__h3">{{ __('dashboard.med_meds_title') }}</h3>
                    <ul class="cc-med__list">
                        @foreach($topMeds as $med)
                            <li>
                                <span class="cc-med__li-name">{{ $med['label'] }}</span>
                                <span class="cc-med__li-qty">{{ $med['quantity'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(count($manejo))
                <div>
                    <h3 class="cc-med__h3">{{ __('dashboard.med_mgmt_title') }}</h3>
                    <div class="cc-med__chips">
                        @foreach($manejo as $etiqueta => $veces)
                            <span class="cc-med__chip">{{ $etiqueta }} <b>{{ $veces }}</b></span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Atajos a las pantallas que detallan lo de arriba. El conteo sólo si lo tiene permitido:
         el tablero no ofrece una puerta que el backend va a cerrar. --}}
    <div class="cc-med__links">
        <a href="{{ route('medicocrud') }}" class="cc-med__link">
            @include('componentes._icon', ['name' => 'stethoscope', 'class' => 'cc-ico-16', 'label' => null])
            {{ __('dashboard.med_go_consults') }}
        </a>
        <a href="{{ route('medical.bitacora') }}" class="cc-med__link">
            @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico-16', 'label' => null])
            {{ __('dashboard.med_go_log') }}
        </a>
        @if(!empty($m['puede_meds']))
            <a href="{{ route('medical.materials') }}" class="cc-med__link">
                @include('componentes._icon', ['name' => 'package', 'class' => 'cc-ico-16', 'label' => null])
                {{ __('dashboard.med_go_materials') }}
            </a>
        @endif
    </div>
</div>

<style>
    /* Tokens semánticos → claro y oscuro sin escribir dos veces cada regla. */
    .cc-med__tag {
        font-size: .62rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
        padding: 3px 8px; border-radius: 20px; color: var(--brand-primary);
        background: color-mix(in srgb, var(--brand-primary) 16%, transparent);
    }
    .cc-med__stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(88px, 1fr)); gap: .75rem; }
    .cc-med__stat {
        display: flex; flex-direction: column; gap: .1rem;
        padding: .6rem .75rem; border-radius: var(--radius-sm);
        border: 1px solid var(--stroke); background: var(--glass);
    }
    .cc-med__stat--warn {
        border-color: color-mix(in srgb, var(--warn) 45%, transparent);
        background: color-mix(in srgb, var(--warn) 10%, transparent);
    }
    .cc-med__stat--warn .cc-med__num { color: var(--warn); }
    .cc-med__num { font-size: 1.5rem; font-weight: 800; line-height: 1; color: var(--text); font-variant-numeric: tabular-nums; }
    .cc-med__cap { font-size: .72rem; font-weight: 600; color: var(--text-muted); line-height: 1.2; }

    /* Barras: altura por variable --h; align-items:flex-end las ancla al piso. */
    .cc-med__bars { display: flex; align-items: flex-end; gap: 4px; height: 46px; margin-top: 1rem; }
    .cc-med__bar {
        flex: 1; height: var(--h); min-height: 4px; border-radius: 3px 3px 0 0;
        background: linear-gradient(180deg, var(--brand-primary), color-mix(in srgb, var(--brand-primary) 35%, transparent));
    }
    .cc-med__bar--cero { height: 2px; min-height: 2px; border-radius: 2px; background: var(--stroke-2); }
    .cc-med__bar:last-child:not(.cc-med__bar--cero) { background: linear-gradient(180deg, var(--brand-accent), color-mix(in srgb, var(--brand-accent) 35%, transparent)); }
    .cc-med__foot { font-size: .68rem; color: var(--text-muted); margin: .35rem 0 0; }

    .cc-med__split { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem 1.5rem; margin-top: 1.1rem; }
    .cc-med__h3 { font-size: .66rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--text-muted); margin: 0 0 .5rem; }
    .cc-med__list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .3rem; }
    .cc-med__list li { display: flex; align-items: baseline; gap: .5rem; font-size: .82rem; color: var(--text); }
    .cc-med__li-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cc-med__li-qty { font-weight: 700; font-variant-numeric: tabular-nums; color: var(--brand-primary); }
    .cc-med__chips { display: flex; flex-wrap: wrap; gap: .35rem; }
    .cc-med__chip {
        font-size: .72rem; padding: 3px 9px; border-radius: 20px; color: var(--text-muted);
        border: 1px solid var(--stroke); background: var(--glass);
    }
    .cc-med__chip b { color: var(--text); font-variant-numeric: tabular-nums; }

    .cc-med__links { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1.1rem; padding-top: .9rem; border-top: 1px solid var(--stroke); }
    .cc-med__link {
        display: inline-flex; align-items: center; gap: .4rem; min-height: 36px;
        padding: .35rem .75rem; border-radius: var(--radius-sm); font-size: .8rem; font-weight: 600;
        color: var(--text-muted); text-decoration: none; border: 1px solid var(--stroke); background: var(--glass);
        transition: color .16s var(--ease), border-color .16s var(--ease), background .16s var(--ease);
    }
    .cc-med__link:hover { color: var(--text); border-color: var(--stroke-2); background: var(--glass-2); }
    .cc-med__link svg { flex: none; }
</style>
