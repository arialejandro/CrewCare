{{-- Cabecera compartida de las pantallas del llamado: navegación por día + pestañas + back.
     Variables: $day (Carbon), $dayLabel, $nav (prev/next/today/dateStr), $active ('config'|'departments'|'people'). --}}
@php
    $ds = $nav['dateStr'];
    $tabs = [
        'config'      => ['Configuración', 'callsheet.config',      'sliders'],
        'departments' => ['Departamentos', 'callsheet.departments', 'layers'],
        'people'      => ['Personas',      'callsheet.people',      'users'],
    ];
@endphp
<div class="cs-head">
    <div class="cs-head__top">
        <div class="cs-daynav">
            <a class="cs-daybtn" href="{{ route($active === 'config' ? 'callsheet.config' : ($active === 'departments' ? 'callsheet.departments' : 'callsheet.people'), ['date' => $nav['prev']]) }}" aria-label="Día anterior">
                @include('componentes._icon', ['name' => 'chevron-left', 'class' => 'cc-ico', 'label' => null])
            </a>
            <div class="cs-daynav__center">
                <span class="cs-daylabel">{{ $dayLabel }}</span>
                <span class="cs-datestr">{{ \Carbon\Carbon::parse($day)->isoFormat('ddd D MMM YYYY') }}</span>
            </div>
            <a class="cs-daybtn" href="{{ route($active === 'config' ? 'callsheet.config' : ($active === 'departments' ? 'callsheet.departments' : 'callsheet.people'), ['date' => $nav['next']]) }}" aria-label="Día siguiente">
                @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-ico', 'label' => null])
            </a>
        </div>
        <div class="cs-head__actions">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('callsheet.format') }}">
                @include('componentes._icon', ['name' => 'layout', 'class' => 'cc-ico me-1', 'label' => null]) Formato
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('callsheet.places') }}">
                @include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-ico me-1', 'label' => null]) Lugares
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('callsheet.back', ['date' => $ds]) }}" target="_blank" rel="noopener">
                @include('componentes._icon', ['name' => 'download', 'class' => 'cc-ico me-1', 'label' => null]) Ver back
            </a>
            <a class="btn btn-sm btn-primary" href="{{ route('callsheet.package', ['date' => $ds]) }}">
                @include('componentes._icon', ['name' => 'send', 'class' => 'cc-ico me-1', 'label' => null]) Paquete
            </a>
        </div>
    </div>
    <nav class="cs-tabs">
        @foreach($tabs as $key => [$label, $route, $icon])
            <a class="cs-tab {{ $active === $key ? 'is-active' : '' }}" href="{{ route($route, ['date' => $ds]) }}">
                @include('componentes._icon', ['name' => $icon, 'class' => 'cc-ico', 'label' => null])
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>
</div>
