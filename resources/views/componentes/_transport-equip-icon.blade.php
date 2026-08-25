{{-- Ícono de equipamiento de una corrida (Bloque 2 §2). Recibe $icon (clave de transport_equipment.icon).
     Reutilizable por el editor y por el documento/PDF. Desconocido → caja genérica. --}}
@php $icon = $icon ?? ''; @endphp
<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex:none;vertical-align:middle">
@switch($icon)
@case('tag')<path d="M3 3h8l10 10-8 8L3 13V3z"/><circle cx="7.5" cy="7.5" r="1.2"/>@break
@case('cooler')<rect x="3" y="8" width="18" height="11" rx="1"/><path d="M3 12h18M8 8V6h8v2"/>@break
@case('droplet')<path d="M12 3s6 7 6 11a6 6 0 0 1-12 0c0-4 6-11 6-11z"/>@break
@case('signal')<path d="M4.5 12a8 8 0 0 1 8-8M7 14a5 5 0 0 1 5-5"/><circle cx="12" cy="16.5" r="1.2"/>@break
@case('map-pin')<path d="M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7z"/><circle cx="12" cy="9" r="2.4"/>@break
@case('ramp')<path d="M3 19h18M4 19 20 7"/>@break
@case('link')<path d="M10 13a3 3 0 0 0 4.2.4l2.4-2.4a3 3 0 0 0-4.2-4.2L11 8"/><path d="M14 11a3 3 0 0 0-4.2-.4L7.4 13a3 3 0 0 0 4.2 4.2L13 16"/>@break
@case('cart')<rect x="7" y="4" width="8" height="11" rx="1"/><path d="M7 15H5V4"/><circle cx="8" cy="19" r="1.4"/><circle cx="14" cy="19" r="1.4"/>@break
@case('chair')<path d="M7 4v8h10V4M7 12v6M17 12v6M5 12h14"/>@break
@case('umbrella')<path d="M12 3a9 9 0 0 1 9 8H3a9 9 0 0 1 9-8zM12 11v7a2 2 0 0 0 4 0"/>@break
@case('first-aid')<rect x="4" y="6" width="16" height="12" rx="1"/><path d="M12 9v6M9 12h6"/>@break
@case('fire')<path d="M12 3c1.2 3 4 4 4 8a4 4 0 0 1-8 0c0-2 1-3 2-4 .3 1.8 2 1.8 2 0 0-1.6 0-2.8 0-4z"/>@break
@default<rect x="4" y="4" width="16" height="16" rx="2"/>
@endswitch
</svg>
