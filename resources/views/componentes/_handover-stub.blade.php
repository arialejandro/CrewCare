{{--
    _handover-stub — SCAFFOLD (Pilar 5, futuro).

    Maqueta del handover de responsabilidad de una locación entre cuadrillas:
    Construcción → Rigging → Shooting. NO tiene backend: el stepper y los estados
    (Pendiente / Actual / Entregado) son placeholders, y el botón "Transferir
    responsabilidad" está deshabilitado. Vive tras el flag 'location_handover'.

    Uso: @include('componentes._handover-stub')
--}}
@feature('location_handover')
@php
    // Etapas de la cadena de custodia de la locación. 'state' es placeholder:
    //   done    → Entregado (ya transfirió)
    //   current → Actual (cuadrilla en control ahora)
    //   pending → Pendiente (aún no recibe)
    $hoSteps = [
        ['label' => 'Construcción', 'icon' => 'fa-hammer',         'state' => 'done'],
        ['label' => 'Rigging',      'icon' => 'fa-link',           'state' => 'current'],
        ['label' => 'Shooting',     'icon' => 'fa-clapperboard',   'state' => 'pending'],
    ];
    $hoBadge = [
        'done'    => ['Entregado', 'text-bg-success'],
        'current' => ['Actual',    'text-bg-primary'],
        'pending' => ['Pendiente', 'text-bg-secondary'],
    ];
@endphp
<style>
    .ho-stub { border: 1px solid #e5e7eb; border-radius: 14px; background: #fff; padding: 1.1rem 1.2rem; }
    .ho-title { font-weight: 700; color: #0f172a; margin: 0 0 .2rem; font-size: .95rem; }
    .ho-sub { color: #94a3b8; font-size: .78rem; margin: 0 0 1rem; }
    .ho-stepper { display: flex; align-items: flex-start; gap: 0; }
    .ho-step { flex: 1; text-align: center; position: relative; }
    /* Línea conectora entre pasos */
    .ho-step:not(:last-child)::after { content: ""; position: absolute; top: 22px; left: 50%; width: 100%; height: 3px; background: #e5e7eb; z-index: 0; }
    .ho-step.done:not(:last-child)::after { background: #86efac; }
    .ho-dot { position: relative; z-index: 1; width: 46px; height: 46px; margin: 0 auto .5rem; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; font-size: 1.05rem;
        background: #f1f5f9; color: #94a3b8; border: 2px solid #e5e7eb; }
    .ho-step.done .ho-dot { background: #dcfce7; color: #16a34a; border-color: #86efac; }
    .ho-step.current .ho-dot { background: #dbeafe; color: #2563eb; border-color: #93c5fd; box-shadow: 0 0 0 4px rgba(37,99,235,.12); }
    .ho-name { font-weight: 600; color: #334155; font-size: .82rem; }
    .ho-foot { margin-top: 1.1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-top: 1px solid #f1f3f5; padding-top: .9rem; }
    .ho-note { color: #94a3b8; font-size: .76rem; margin: 0; }
</style>

<div class="ho-stub" data-location-handover>
    <p class="ho-title">Handover de locación</p>
    <p class="ho-sub">Cadena de custodia entre cuadrillas (próximamente).</p>

    <div class="ho-stepper">
        @foreach($hoSteps as $step)
            @php list($badgeText, $badgeClass) = $hoBadge[$step['state']]; @endphp
            <div class="ho-step {{ $step['state'] }}">
                <div class="ho-dot"><i class="fas {{ $step['icon'] }}"></i></div>
                <div class="ho-name">{{ $step['label'] }}</div>
                <span class="badge {{ $badgeClass }} mt-1">{{ $badgeText }}</span>
            </div>
        @endforeach
    </div>

    <div class="ho-foot">
        <p class="ho-note">Sin backend todavía: los estados son ilustrativos.</p>
        <button type="button" class="btn btn-outline-dark btn-sm" disabled title="Próximamente">
            <i class="fas fa-people-arrows me-1"></i> Transferir responsabilidad
        </button>
    </div>
</div>
@endfeature
