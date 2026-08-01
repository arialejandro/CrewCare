{{-- Card de herramienta. Espera $tool (modelo Eloquent con family + paro_count).
     Relleno SÓLIDO = algo de la herramienta; contorno punteado = algo del catálogo. --}}
@php
    $aliases = is_array($tool->aliases ?? null) ? $tool->aliases : [];
    $aliasStr = implode(' · ', array_slice($aliases, 0, 3));
    $paro = (int) ($tool->paro_count ?? 0);
    $hasPermit = ! empty($tool->triggers_permit_name);
    $needsOperator = (bool) $tool->requires_designated_operator;
    $look = trim((string) ($tool->quick_id ?? ''));
    $launch = $launch ?? [];
    $regimeLabels = ['por_jornada' => __('Por jornada'), 'por_colocacion' => __('Por colocación')];
    $regimeChip = $regimeLabels[$tool->inspection_regime ?? ''] ?? null;
@endphp
<div class="tool-card">
    <div class="tool-card__hero">
        <span class="tool-card__code">{{ $tool->code }}</span>
        <span class="tool-card__mono">@include('componentes._icon', ['name' => 'wrench', 'label' => null])</span>

        @if ($paro > 0)
            <span class="tool-card__paro" title="{{ $paro }} {{ __('puntos de paro') }}">
                @include('componentes._icon', ['name' => 'octagon-alert', 'label' => null])
                {{ $paro }}
            </span>
        @endif

        <div class="tool-card__badges">
            @if ($needsOperator)
                <span class="tool-badge tool-badge--solid" title="{{ __('Requiere operador designado: checklist doble') }}">
                    @include('componentes._icon', ['name' => 'user', 'label' => null])
                    {{ __('OPERADOR') }}
                </span>
            @endif
            @if ($hasPermit)
                <span class="tool-badge tool-badge--catalog" title="{{ __('Dispara un permiso de actividad') }}: {{ $tool->triggers_permit_name }}">
                    @include('componentes._icon', ['name' => 'file-check', 'label' => null])
                    {{ __('PERMISO') }}
                </span>
            @endif
        </div>
    </div>

    <div class="tool-card__body">
        <p class="tool-card__name">{{ $tool->name }}</p>
        @if ($tool->name_en)
            <p class="tool-card__sub">{{ $tool->name_en }}</p>
        @endif
        @if ($aliasStr !== '')
            <p class="tool-card__alias">{{ $aliasStr }}</p>
        @endif
        @if ($regimeChip)
            <span class="insp-tag" style="align-self:flex-start;margin-top:.15rem;" title="{{ __('Régimen de vigencia') }}">{{ $regimeChip }}</span>
        @endif
        @if ($look !== '')
            <div class="tool-card__look">
                @include('componentes._icon', ['name' => 'search', 'label' => null])
                <span><strong>{{ __('Qué mirar') }}:</strong> {{ $look }}</span>
            </div>
        @endif
    </div>

    <div class="tool-card__actions">
        <a href="{{ route('tools.inspect.form', array_merge([$tool->id], $launch)) }}" class="tool-card__inspect">
            @include('componentes._icon', ['name' => 'clipboard-check', 'label' => null])
            {{ __('Inspeccionar') }}
        </a>
        <a href="{{ route('tools.show', $tool->id) }}" class="tool-card__fiche" title="{{ __('Ver ficha') }}" aria-label="{{ __('Ver ficha') }}">
            @include('componentes._icon', ['name' => 'list', 'label' => null])
        </a>
    </div>
</div>
