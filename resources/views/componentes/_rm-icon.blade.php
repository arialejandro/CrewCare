{{--
    Iconos del Mapeo de riesgos (delta #50). Pictogramas RELLENOS de señalización
    industrial (recursos = serie E/F verde-rojo; peligros = símbolo W por tipo).
    SVG propio inline (la CSP bloquea CDNs; todo debe verse offline y en papel).
    El COLOR lo pone la gota vía currentColor: recursos = símbolo blanco;
    peligros = símbolo NEGRO sobre amarillo de advertencia (ISO). El símbolo llena
    ~0.62 del pin (escala en el CSS del lienzo/documento), así se lee de lejos.

    Claves — recursos: extintor|salida_emergencia|botiquin|punto_alarma|
      manguera_hidrante|tablero_electrico|punto_reunion|acceso_ambulancia
    peligros: haz-warn|haz-bolt|haz-flame|haz-fall|haz-fallobj|haz-suspended|
      haz-collapse|haz-slip|haz-temp|haz-water|haz-vehicle|haz-people|haz-bio|
      haz-toxic|haz-animal|haz-drone|haz-firearm|haz-explosive|haz-exit
    especiales: hazard (alias warn) | area
--}}
@php $__k = $key ?? ''; $__c = $class ?? ''; @endphp
<svg class="{{ $__c }}" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true">
@switch($__k)
    {{-- ── RECURSOS (símbolo blanco) ─────────────────────────────────── --}}
    @case('extintor')
        <rect x="8" y="8.4" width="8" height="11.6" rx="2.6"/>
        <rect x="10.2" y="5.8" width="3.6" height="3" rx=".4"/>
        <rect x="8" y="3.9" width="9" height="2.1" rx="1"/>
        <path d="M16.8 4.6c2.4.2 3.1 1.7 3.1 3.5V9.4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
        <rect x="18.9" y="8.4" width="2.1" height="3" rx=".6"/>
        <rect x="9.7" y="11.4" width="4.6" height="3.3" rx=".5" fill="#000" fill-opacity=".18"/>
        @break
    @case('salida_emergencia')
    @case('haz-exit')
        <path d="M13 3h6.4v18H13v-2.6h3.8V5.6H13z"/>
        <path d="M3 12l6.6-5.3v3.2h3.6v4.2H9.6v3.2z"/>
        @break
    @case('botiquin')
        <rect x="3.7" y="7.1" width="16.6" height="12.3" rx="2.2" fill="none" stroke="currentColor" stroke-width="2.3"/>
        <rect x="9" y="4.5" width="6" height="2.9" rx="1"/>
        <path d="M11 10.4h2v2.4h2.4v2h-2.4v2.4h-2v-2.4H8.6v-2H11z"/>
        @break
    @case('punto_alarma')
        <path d="M12 3.1a1.5 1.5 0 0 0-1.5 1.4A5.7 5.7 0 0 0 6.5 10.1c0 3.9-1.6 5-1.6 5a1.05 1.05 0 0 0 .75 1.8h12.7a1.05 1.05 0 0 0 .75-1.8s-1.6-1.1-1.6-5a5.7 5.7 0 0 0-4-5.6A1.5 1.5 0 0 0 12 3.1z"/>
        <path d="M9.9 18.1a2.1 2.1 0 0 0 4.2 0z"/>
        @break
    @case('manguera_hidrante')
        <circle cx="10.4" cy="12.6" r="7.3" fill="none" stroke="currentColor" stroke-width="2.6"/>
        <circle cx="10.4" cy="12.6" r="2.1"/>
        <rect x="16.6" y="11.1" width="4.2" height="3" rx=".8"/>
        <path d="M12.6 7.3l3.4-1.9" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        @break
    @case('tablero_electrico')
        <rect x="5" y="3.4" width="14" height="17.2" rx="2" fill="none" stroke="currentColor" stroke-width="2.2"/>
        <path d="M13 6l-4.6 7h3.3l-1.1 5 4.7-7.2h-3.2z"/>
        @break
    @case('punto_reunion')
        <circle cx="7.1" cy="7.4" r="2.2"/>
        <circle cx="16.9" cy="7.4" r="2.2"/>
        <circle cx="12" cy="6.3" r="2.5"/>
        <path d="M3.4 18.8v-1.9a3.2 3.2 0 0 1 4.8-2.8 3.6 3.6 0 0 1 7.6 0 3.2 3.2 0 0 1 4.8 2.8v1.9z"/>
        @break
    @case('acceso_ambulancia')
        <rect x="3" y="8" width="11" height="7" rx="1.1" fill="none" stroke="currentColor" stroke-width="1.9"/>
        <path d="M14 10.4h3.2l3.1 3.1V15H14z" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/>
        <path d="M6.6 9.4h1.7v1.6h1.6v1.7H8.3v1.6H6.6v-1.6H5v-1.7h1.6z"/>
        <circle cx="7.4" cy="16.8" r="1.6"/>
        <circle cx="16.8" cy="16.8" r="1.6"/>
        @break

    {{-- ── PELIGROS (símbolo negro sobre amarillo) ────────────────────── --}}
    @case('haz-warn')
    @case('hazard')
        <path d="M10.5 5h3l-.5 8.6h-2z"/>
        <circle cx="12" cy="17.2" r="1.8"/>
        @break
    @case('haz-bolt')
        <path d="M13.6 2.2l-8.2 11.6h5.4l-1.8 8 8.6-12.4h-5.6z"/>
        @break
    @case('haz-flame')
        <path d="M12.3 2.2c.5 3 2.4 4.2 3.7 6 2 2.9.9 7.6-3.3 8.9a5.5 5.5 0 0 1-6.4-7.3c.5 1.2 1.6 1.7 2.6 1.6-1.1-2.6.3-5.3 2.1-6.8-.2 1.5.4 2.5 1.3 2.9.2-2.1.2-4 .3-5.3z"/>
        @break
    @case('haz-fall')
        {{-- persona cayendo desde un borde (a distinto nivel) --}}
        <path d="M3 12.4h2v6h4v2H3z"/>
        <circle cx="14.6" cy="4.9" r="2"/>
        <path d="M13 7c1.3-.5 2.7.2 3.1 1.5l.9 2.9 2.8 1.4-.9 1.8-3.3-1.6-.6-1.7-.9 2.6 2.4 3.9-1.7 1-2.7-4.2-.2-3.6z"/>
        @break
    @case('haz-fallobj')
        {{-- objetos que caen sobre una superficie --}}
        <rect x="4.8" y="3.4" width="5.2" height="5.2" rx=".6" transform="rotate(-14 7.4 6)"/>
        <rect x="12.8" y="6.2" width="4.4" height="4.4" rx=".6" transform="rotate(12 15 8.4)"/>
        <path d="M4 18.4h16v2H4z"/>
        <path d="M8 11.2l-1.4 4.4M15.4 11.6l1 3.6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        @break
    @case('haz-suspended')
        {{-- carga suspendida sobre persona --}}
        <path d="M4 3.6h16v2H4z"/>
        <path d="M12 5.6v2.4" fill="none" stroke="currentColor" stroke-width="1.8"/>
        <path d="M8.4 8h7.2l-1.3 5.4H9.7z"/>
        <circle cx="12" cy="17.4" r="1.9"/>
        <path d="M9.4 21v-1.2a2.6 2.6 0 0 1 5.2 0V21z"/>
        @break
    @case('haz-collapse')
        {{-- estibado inestable / colapso --}}
        <path d="M3.4 20h17.2v1.7H3.4z"/>
        <rect x="4.4" y="12.6" width="6.2" height="5.6"/>
        <rect x="4.9" y="6.6" width="5.6" height="5" transform="rotate(-7 7.7 9.1)"/>
        <rect x="12.4" y="8.4" width="6" height="9.8" transform="rotate(11 15.4 13.3)"/>
        @break
    @case('haz-slip')
        {{-- resbalón / tropiezo --}}
        <circle cx="8.4" cy="4.9" r="2"/>
        <path d="M7 7.2c1.4-.3 2.6.6 2.9 1.9l1.9 6.5 3.9 1.2-.6 2-4.8-1.5-1-3.2-1 4.6-3.6 3-1.4-1.5 2.9-2.5z"/>
        <path d="M3 19.4c3-.5 5-2 6-2.6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
        <path d="M13.4 20.4l6.6-1.8" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
        @break
    @case('haz-temp')
        <path d="M12 3.2a2.3 2.3 0 0 0-2.3 2.3v7.7a4 4 0 1 0 4.6 0V5.5A2.3 2.3 0 0 0 12 3.2z"/>
        <path d="M12 8v6.4" fill="none" stroke="#000" stroke-opacity=".28" stroke-width="1.4"/>
        @break
    @case('haz-water')
        <path d="M12 3.1c0 0 6.5 6.8 6.5 11a6.5 6.5 0 0 1-13 0C5.5 9.9 12 3.1 12 3.1z"/>
        @break
    @case('haz-vehicle')
        <path d="M2.8 8.2h10v7H2.8z"/>
        <path d="M12.8 10.4h3.4l3.4 3.1v1.7h-6.8z"/>
        <circle cx="7" cy="16.6" r="1.8"/>
        <circle cx="17" cy="16.6" r="1.8"/>
        @break
    @case('haz-people')
        <circle cx="7.8" cy="7.4" r="2.2"/>
        <circle cx="16.2" cy="7.4" r="2.2"/>
        <path d="M4 18.6v-2.1a3.8 3.8 0 0 1 7.6 0v2.1z"/>
        <path d="M12.4 18.6v-2.1a3.8 3.8 0 0 1 7.6 0v2.1z"/>
        @break
    @case('haz-bio')
        {{-- microbio (riesgo biológico) --}}
        <circle cx="12" cy="12" r="4.7"/>
        <path d="M12 3.2v3.4M12 17.4v3.4M3.2 12h3.4M17.4 12h3.4M5.8 5.8l2.4 2.4M18.2 5.8l-2.4 2.4M5.8 18.2l2.4-2.4M18.2 18.2l-2.4-2.4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        <circle cx="12" cy="3" r="1.2"/><circle cx="12" cy="21" r="1.2"/>
        <circle cx="3" cy="12" r="1.2"/><circle cx="21" cy="12" r="1.2"/>
        <circle cx="5.2" cy="5.2" r="1.1"/><circle cx="18.8" cy="5.2" r="1.1"/>
        <circle cx="5.2" cy="18.8" r="1.1"/><circle cx="18.8" cy="18.8" r="1.1"/>
        @break
    @case('haz-toxic')
        {{-- calavera (tóxico / materiales peligrosos) --}}
        <path d="M6.2 17.4l11.6 2.8M17.8 17.4L6.2 20.2" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
        <path fill-rule="evenodd" d="M12 3.3c-3.7 0-6.6 2.7-6.6 6.2 0 2.1 1.1 3.7 2.4 4.6v2.1h1.7l.6 1.5h3.8l.6-1.5h1.7v-2.1c1.3-.9 2.4-2.5 2.4-4.6 0-3.5-2.9-6.2-6.6-6.2zM9.3 9.1a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm5.4 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zM12 13.1l-1 2.2h2z"/>
        @break
    @case('haz-animal')
        <circle cx="7.4" cy="9" r="1.9"/>
        <circle cx="12" cy="7.2" r="2"/>
        <circle cx="16.6" cy="9" r="1.9"/>
        <path d="M12 11c2.7 0 4.8 2.2 4.8 4.3a2.6 2.6 0 0 1-2.6 2.6c-.9 0-1.5-.4-2.2-.4s-1.3.4-2.2.4a2.6 2.6 0 0 1-2.6-2.6C7.2 13.2 9.3 11 12 11z"/>
        @break
    @case('haz-drone')
        <circle cx="5.3" cy="5.3" r="2.5" fill="none" stroke="currentColor" stroke-width="1.9"/>
        <circle cx="18.7" cy="5.3" r="2.5" fill="none" stroke="currentColor" stroke-width="1.9"/>
        <circle cx="5.3" cy="18.7" r="2.5" fill="none" stroke="currentColor" stroke-width="1.9"/>
        <circle cx="18.7" cy="18.7" r="2.5" fill="none" stroke="currentColor" stroke-width="1.9"/>
        <path d="M7 7l3.2 3.2M17 7l-3.2 3.2M7 17l3.2-3.2M17 17l-3.2-3.2" fill="none" stroke="currentColor" stroke-width="1.9"/>
        <rect x="9.6" y="9.6" width="4.8" height="4.8" rx="1.2"/>
        @break
    @case('haz-firearm')
        <path d="M3.6 8.2h13.8v3.4h-3.5l-.9 1.8c-.5 1-1.3 1.6-2.5 1.6h-.3v3.6H7.7V15c-1.5-.2-2.7-1.1-3.3-2.6L3.2 9.8C2.9 9 3.4 8.2 4.3 8.2z"/>
        <rect x="15.2" y="8.6" width="4" height="1.4" rx=".3"/>
        @break
    @case('haz-explosive')
        <path d="M12 2L13.11 7.85L17 3.34L15.04 8.96L20.66 7L16.15 10.89L22 12L16.15 13.11L20.66 17L15.04 15.04L17 20.66L13.11 16.15L12 22L10.89 16.15L7 20.66L8.96 15.04L3.34 17L7.85 13.11L2 12L7.85 10.89L3.34 7L8.96 8.96L7 3.34L10.89 7.85Z"/>
        @break

    @case('area')
        <rect x="4" y="4" width="16" height="16" rx="1.6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-dasharray="3.4 3"/>
        @break
    @default
        <circle cx="12" cy="12" r="4.2"/>
@endswitch
</svg>
