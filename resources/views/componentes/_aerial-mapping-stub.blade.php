{{--
    _aerial-mapping-stub — SCAFFOLD (Pilar 5, futuro).

    Estructura lógica para un futuro overlay de marcadores sobre una foto de dron
    en el Scouting. NO tiene backend: sólo la maqueta tras el flag 'aerial_mapping'.
    Cuando se implemente, la <img> mostrará la ortofoto y .am-markers recibirá pines
    absolutos posicionados por porcentaje (data-x/data-y) sobre la imagen.

    Uso: @include('componentes._aerial-mapping-stub')
--}}
@feature('aerial_mapping')
<style>
    .am-stub { border: 1px solid #e5e7eb; border-radius: 14px; overflow: hidden; background: #fff; }
    .am-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .8rem 1rem; border-bottom: 1px solid #f1f3f5; }
    .am-title { font-weight: 700; color: #0f172a; margin: 0; font-size: .95rem; }
    .am-beta { font-size: .68rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #7c3aed; background: #f3e8ff; border-radius: 999px; padding: .18rem .55rem; }
    /* Lienzo: la foto de dron va como <img>; los pines flotan encima en .am-markers */
    .am-canvas { position: relative; width: 100%; min-height: 260px; background:
        repeating-conic-gradient(#f6f7f9 0% 25%, #fff 0% 50%) 50% / 22px 22px; }
    .am-drop { position: absolute; inset: 14px; border: 2px dashed #cbd5e1; border-radius: 12px;
        display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .4rem;
        color: #64748b; text-align: center; padding: 1rem; }
    .am-drop i { font-size: 1.6rem; color: #94a3b8; }
    .am-photo { display: block; width: 100%; height: auto; }
    .am-markers { position: absolute; inset: 0; pointer-events: none; }
    .am-foot { padding: .65rem 1rem; border-top: 1px solid #f1f3f5; color: #94a3b8; font-size: .78rem; }
</style>

<div class="am-stub" data-aerial-mapping>
    <div class="am-head">
        <p class="am-title">Mapeo aéreo</p>
        <span class="am-beta">Próximamente</span>
    </div>

    <div class="am-canvas">
        {{-- Placeholder: aquí irá la ortofoto/foto de dron cuando exista backend.
             <img class="am-photo" src="…" alt="Foto de dron"> --}}
        <div class="am-drop">
            <i class="fas fa-helicopter"></i>
            <span>Sube foto de dron</span>
            <small>Arrastra o selecciona la ortofoto de la locación</small>
        </div>

        {{-- Capa de marcadores absolutos. En producción se pintarán pines aquí,
             posicionados por porcentaje sobre la imagen (data-x/data-y). --}}
        <div class="am-markers" data-hint="Los marcadores de puntos clave (acceso, base camp, riesgos) se colocarán aquí sobre la foto."></div>
    </div>

    <div class="am-foot">
        Módulo de mapeo aéreo — en preparación.
    </div>
</div>
@endfeature
