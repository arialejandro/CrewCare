{{-- ============================================================================================
     CINTILLO DE TRATAMIENTO PREVIO — COMPONENTE COMPARTIDO (2026-07-25).
     Un solo componente y una sola query (cmedic::lastForPatient) para los DOS caminos: consulta de
     crew (admin.cmedica) y consulta de paciente sin cuenta (admin.lite.consulta). Antes eran dos
     copias que divergían solas (una tabla Bootstrap, un grid propio; una legible, otra muteada).

     PROPÓSITO: SEGURIDAD, no consulta. Que el médico no repita un tratamiento ni sume dosis. Por eso:
       · SÓLO la ÚLTIMA consulta del paciente (nunca una lista), de CUALQUIER médico.
       · Completa y LEGIBLE de un vistazo: fecha · diagnóstico · medicamento (dosis/presentación/
         cantidad) + manejo. Máximo contraste (--text): un diagnóstico y una dosis NO son secundarios.
       · SIN recorte: sin max-height, sin scroll interno, sin desvanecido. Si el texto es largo, el
         bloque CRECE (flex-wrap); ese dato existe para leerse.
       · Si no hay consulta previa lo DICE ("Sin atenciones previas registradas"), no queda vacío.

     Contrato: $prev = un App\Models\cmedic (la última consulta) o null.
     ============================================================================================ --}}
@php($prev = $prev ?? null)

@once
<style>
    .cc-cintillo {
        border: 1px solid var(--warning, #b45309); border-radius: 14px;
        background: var(--surface-2); color: var(--text);
        padding: .9rem 1rem; margin-bottom: 1.1rem;
    }
    .cc-cintillo--empty { border-color: var(--border); }
    .cc-cintillo__head { display: flex; align-items: flex-start; gap: .6rem; margin-bottom: .55rem; }
    .cc-cintillo__ico { color: var(--warning, #b45309); flex: none; }
    .cc-cintillo--empty .cc-cintillo__ico { color: var(--text-muted); }
    .cc-cintillo__title { font-weight: 700; font-size: .95rem; margin: 0; color: var(--text); }
    .cc-cintillo__sub { font-size: .78rem; color: var(--text-muted); margin: .12rem 0 0; }
    /* Una sola fila que CRECE (wrap), nunca se corta. */
    .cc-cintillo__row { display: flex; flex-wrap: wrap; gap: .3rem .9rem; align-items: baseline; }
    .cc-cintillo__date { font-variant-numeric: tabular-nums; color: var(--text); font-weight: 600; white-space: nowrap; }
    .cc-cintillo__dx { font-weight: 700; color: var(--text); }
    /* El medicamento es el dato que evita la sobredosis: máximo contraste, jamás muteado. */
    .cc-cintillo__med { color: var(--text); font-weight: 600; }
    .cc-cintillo__mgmt { display: block; width: 100%; margin-top: .15rem; font-size: .85rem; font-style: italic; color: var(--text); }
    .cc-cintillo__empty { color: var(--text-muted); font-size: .9rem; }
</style>
@endonce

<div class="cc-cintillo {{ $prev ? '' : 'cc-cintillo--empty' }}">
    <div class="cc-cintillo__head">
        <span class="cc-cintillo__ico">
            @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico-20', 'label' => null])
        </span>
        <div>
            <p class="cc-cintillo__title">{{ __('Tratamiento previo del paciente') }}</p>
            <p class="cc-cintillo__sub">{{ __('Revísalo antes de recetar: la última atención, de cualquier médico.') }}</p>
        </div>
    </div>

    @if($prev)
        @php($fecha = $prev->consultation_date ?: $prev->created_at)
        @php($med = $prev->medsLine())
        @php($mgmt = implode(' · ', $prev->managementLabels()))
        <div class="cc-cintillo__row">
            <span class="cc-cintillo__date">{{ $fecha ? \Carbon\Carbon::parse($fecha)->format('d/m/Y') : '—' }}</span>
            <span class="cc-cintillo__dx">{{ trim((string) $prev->diagnosis) ?: '—' }}</span>
            <span class="cc-cintillo__med">{{ $med !== '' ? $med : ($mgmt !== '' ? $mgmt : __('Sin medicamento')) }}</span>
            @if($med !== '' && $mgmt !== '')
                <span class="cc-cintillo__mgmt">{{ $mgmt }}</span>
            @endif
        </div>
    @else
        <div class="cc-cintillo__empty">{{ __('Sin atenciones previas registradas') }}</div>
    @endif
</div>
