{{--
    Icono inline del Mapeo de riesgos (delta #50). UN solo estilo de pin: el color
    lo pone la gota; el icono va en blanco encima. SVG propio (no dependemos de qué
    nombres Lucide estén vendorizados) para que se vea igual en pantalla y en papel.

    Parámetros: $key (tipo de recurso | 'hazard' | 'area'), $class (opcional).
--}}
@php $__k = $key ?? ''; $__c = $class ?? ''; @endphp
<svg class="{{ $__c }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($__k)
    @case('extintor')
        <rect x="8" y="7" width="8" height="13" rx="2"/><path d="M10 7V5h4v2"/><path d="M14 5l3-1"/>
        @break
    @case('salida_emergencia')
        <path d="M13 4H6a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h7"/><path d="M14 12h7"/><path d="M18 8l4 4-4 4"/>
        @break
    @case('botiquin')
        <rect x="3" y="6" width="18" height="13" rx="2"/><path d="M9 6V4h6v2"/><path d="M12 10v5"/><path d="M9.5 12.5h5"/>
        @break
    @case('punto_alarma')
        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>
        @break
    @case('manguera_hidrante')
        <path d="M12 3s6 6 6 11a6 6 0 0 1-12 0c0-5 6-11 6-11z"/>
        @break
    @case('tablero_electrico')
        <path d="M13 2 4 14h7l-1 8 9-12h-7z"/>
        @break
    @case('punto_reunion')
        <path d="M5 21V4"/><path d="M5 4h11l-2 3 2 3H5"/>
        @break
    @case('acceso_ambulancia')
        <path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17.5" cy="18" r="1.6"/><path d="M7 9v3M5.5 10.5h3"/>
        @break
    @case('hazard')
        <path d="M12 3 2 20h20z"/><path d="M12 9v5"/><path d="M12 17h.01"/>
        @break
    @case('area')
        <rect x="4" y="4" width="16" height="16" rx="1" stroke-dasharray="3 3"/>
        @break
    @default
        <circle cx="12" cy="12" r="4"/>
@endswitch
</svg>
