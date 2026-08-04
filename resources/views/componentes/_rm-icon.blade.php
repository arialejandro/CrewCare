{{--
    Iconos del Mapeo de riesgos (delta #50). Pictogramas RELLENOS calcados de la
    señalización ISO 7010 (recursos = serie E/F; peligros = símbolo por categoría).
    SVG propio inline (la CSP bloquea CDNs; todo debe verse offline y en papel). El
    color lo pone la gota; el símbolo va en blanco (currentColor).

    Claves: recursos (extintor|salida_emergencia|botiquin|punto_alarma|
    manguera_hidrante|tablero_electrico|punto_reunion|acceso_ambulancia) ·
    peligros por categoría (haz-bolt|haz-flame|haz-fall|haz-temp|haz-water|
    haz-vehicle|haz-people|haz-struct|haz-bio|haz-animal|haz-drone|haz-warn) ·
    'hazard' (genérico) · 'area'.
--}}
@php $__k = $key ?? ''; $__c = $class ?? ''; @endphp
<svg class="{{ $__c }}" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true">
@switch($__k)
    {{-- ── RECURSOS (E/F) ────────────────────────────────────────────── --}}
    @case('extintor')
        <rect x="9" y="7.6" width="6" height="12.4" rx="2.2"/>
        <path d="M10.6 7.6V6.1h2.8v1.5z"/>
        <path d="M13.2 6.5l3.5-1.4v1.8l-3.5.9z"/>
        <rect x="15.6" y="4.5" width="1.7" height="2.7" rx=".7"/>
        @break
    @case('salida_emergencia')
        <path d="M14 3h5.6v18H14v-2.4h3.2V5.4H14z"/>
        <path d="M3.4 12l6.4-5.1v3.1h3.3v4H9.8v3.1z"/>
        @break
    @case('botiquin')
        <path d="M9.8 3.6h4.4v6.2h6.2v4.4h-6.2v6.2H9.8v-6.2H3.6V9.8h6.2z"/>
        @break
    @case('punto_alarma')
        <path d="M12 3.2a1.4 1.4 0 0 0-1.4 1.3A5.5 5.5 0 0 0 6.6 10c0 4.1-1.7 5.3-1.7 5.3a1 1 0 0 0 .7 1.7h12.8a1 1 0 0 0 .7-1.7s-1.7-1.2-1.7-5.3a5.5 5.5 0 0 0-4-5.5A1.4 1.4 0 0 0 12 3.2z"/>
        <path d="M10.1 18.3a2 2 0 0 0 3.8 0z"/>
        @break
    @case('manguera_hidrante')
        <path fill-rule="evenodd" d="M9.5 4.4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zm0 4.2a2.8 2.8 0 1 1 0 5.6 2.8 2.8 0 0 1 0-5.6z"/>
        <path d="M15.6 10.4h4.6a1 1 0 0 1 1 1v1.2a1 1 0 0 1-1 1h-4.6z"/>
        @break
    @case('tablero_electrico')
        <rect x="5" y="3.6" width="14" height="16.8" rx="2" fill="none" stroke="currentColor" stroke-width="2"/>
        <path d="M13 6.4l-4.3 6.5h3.1l-1 4.7 4.4-6.7h-3z"/>
        @break
    @case('punto_reunion')
        <circle cx="7.4" cy="7.6" r="2.1"/>
        <circle cx="16.6" cy="7.6" r="2.1"/>
        <circle cx="12" cy="6.7" r="2.4"/>
        <path d="M3.8 18.6v-1.7a3 3 0 0 1 4.5-2.6 3.4 3.4 0 0 1 7.4 0 3 3 0 0 1 4.5 2.6v1.7z"/>
        @break
    @case('acceso_ambulancia')
        <path d="M3.2 8.2h9.5a1 1 0 0 1 1 1v5.4H3.2z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
        <path d="M13.7 10.4h3.4l2.9 2.9v1.3h-6.3z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
        <path d="M6.5 9.6h1.6v1.5h1.5v1.6H8.1v1.5H6.5v-1.5H5v-1.6h1.5z"/>
        <circle cx="7.3" cy="16.7" r="1.5"/>
        <circle cx="16.6" cy="16.7" r="1.5"/>
        @break

    {{-- ── PELIGROS (símbolo por categoría) ──────────────────────────── --}}
    @case('haz-bolt')
        <path d="M13 2.6l-7 11h5l-1.6 7.8 8-12h-5z"/>
        @break
    @case('haz-flame')
        <path d="M12 2.4c1.4 3 5.4 4.9 5.4 9.4A5.4 5.4 0 0 1 6.6 12c0-2 1-3.6 2.1-4.6.2 1 .8 1.7 1.6 2 .2-2.5 1-4.3 1.7-7z"/>
        @break
    @case('haz-fall')
        <circle cx="13.5" cy="4.8" r="1.8"/>
        <path d="M12.4 7.1l-2.1 1.9-3 1 .6 1.9 2.7-.9 1-.7-.2 2.1-3.1 4.3 1.6 1.1 2.9-3.9 3.6 3.9 1.4-1.4-3-3.1.5-3.5 1.9 1.2 1 2.7 1.9-.8-1.4-3.5-3.6-2z"/>
        <path d="M3 19.2h6.5v1.6H3z"/>
        @break
    @case('haz-temp')
        <path d="M12 3.4a2.1 2.1 0 0 0-2.1 2.1v7.6a3.7 3.7 0 1 0 4.2 0V5.5A2.1 2.1 0 0 0 12 3.4z"/>
        <circle cx="12" cy="16.4" r="2.4" fill="#fff" fill-opacity=".0"/>
        @break
    @case('haz-water')
        <path d="M12 3.4s6.2 6.4 6.2 10.6a6.2 6.2 0 0 1-12.4 0C5.8 9.8 12 3.4 12 3.4z"/>
        @break
    @case('haz-vehicle')
        <path d="M3 9h11v6H3z"/>
        <path d="M14 11h3l3 3v1h-6z"/>
        <circle cx="7" cy="16.4" r="1.6"/>
        <circle cx="17" cy="16.4" r="1.6"/>
        @break
    @case('haz-people')
        <circle cx="8" cy="7.6" r="2.1"/>
        <circle cx="16" cy="7.6" r="2.1"/>
        <path d="M4.4 18.4v-2a3.6 3.6 0 0 1 7.2 0v2z"/>
        <path d="M12.4 18.4v-2a3.6 3.6 0 0 1 7.2 0v2z"/>
        @break
    @case('haz-struct')
        <path d="M3.6 4h6v6h-6z"/>
        <path d="M13 12.5h6.4v6.4H13z"/>
        <path d="M13.2 3.8l3.2 3.2-3.2 3.2-3.2-3.2z"/>
        @break
    @case('haz-bio')
        <path d="M10.1 3.2h3.8v2l-1 1v3.4l4 6.6a2 2 0 0 1-1.7 3H8.8a2 2 0 0 1-1.7-3l4-6.6V6.2l-1-1z"/>
        @break
    @case('haz-animal')
        <circle cx="7.6" cy="9" r="1.8"/>
        <circle cx="12" cy="7.4" r="1.9"/>
        <circle cx="16.4" cy="9" r="1.8"/>
        <path d="M12 11c2.6 0 4.6 2.1 4.6 4.1a2.5 2.5 0 0 1-2.5 2.5c-.8 0-1.4-.4-2.1-.4s-1.3.4-2.1.4a2.5 2.5 0 0 1-2.5-2.5C7.4 13.1 9.4 11 12 11z"/>
        @break
    @case('haz-drone')
        <circle cx="5.2" cy="6" r="2"/>
        <circle cx="18.8" cy="6" r="2"/>
        <circle cx="5.2" cy="18" r="2"/>
        <circle cx="18.8" cy="18" r="2"/>
        <path d="M6.4 7.2l3 3M17.6 7.2l-3 3M6.4 16.8l3-3M17.6 16.8l-3-3" fill="none" stroke="currentColor" stroke-width="1.8"/>
        <rect x="9.4" y="9.4" width="5.2" height="5.2" rx="1.2"/>
        @break
    @case('haz-warn')
    @case('hazard')
        <path d="M10.7 4.8h2.6l-.5 9.4h-1.6z"/>
        <circle cx="12" cy="17.6" r="1.5"/>
        @break

    @case('area')
        <rect x="4" y="4" width="16" height="16" rx="1.5" fill="none" stroke="currentColor" stroke-width="2" stroke-dasharray="3 3"/>
        @break
    @default
        <circle cx="12" cy="12" r="4"/>
@endswitch
</svg>
