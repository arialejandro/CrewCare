@extends('layouts.app')
@section('title', 'Catálogo de tipos de efecto SFX')

{{--
    admin/sfx-effects/index.blade.php — CATÁLOGO de tipos de efecto (Capa A, Paso 4d).

    ⚠ NO ES EL PANEL SFX EN VIVO (`sfx.index`). Aquí vive la DOCTRINA reutilizable:
    qué es cada tipo de efecto, su riesgo principal, su control base y su normativa.
    No ocurre, no se dispara, no tiene fecha. La bitácora de disparos reales en set
    (iniciar/detener, inyección al DSR del día) es la OTRA vista. Se llaman casi igual
    a una letra de distancia: el rótulo (eyebrow + subtítulo) es lo que las separa
    para el usuario — no lo diluyas.

    Filtro EN CLIENTE (son 25 filas; mismo criterio que la lista de insumos). Las
    opciones de familia salen de los datos, nunca de una lista escrita a mano.

    PHP 7.4: sin nullsafe ni match.
--}}

@feature('sds_sfx')
@push('styles')
<style>
    /* ── Índice glass · lenguaje Cinematic Dark Glass (tokens de _brand-theme) ── */
    .cc-idx-head{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;justify-content:space-between;margin-bottom:1.75rem}
    .cc-idx-headline{display:flex;align-items:center;gap:1rem;min-width:0}
    .cc-idx-icon{width:48px;height:48px;border-radius:14px;flex:none;display:inline-flex;align-items:center;justify-content:center;
        color:var(--brand-primary);background:color-mix(in srgb,var(--brand-primary) 15%,transparent);border:1px solid color-mix(in srgb,var(--brand-primary) 28%,transparent)}
    .cc-idx-icon .cc-ico{width:22px;height:22px}
    .cc-idx-eyebrow{font-size:.66rem;letter-spacing:.2em;text-transform:uppercase;color:var(--brand-primary);font-weight:700}
    .cc-idx-title{margin:.1rem 0 .1rem;font-family:'Poppins',sans-serif;font-weight:800;letter-spacing:-.02em;font-size:1.5rem;color:var(--text);line-height:1.05}
    .cc-idx-sub{margin:0;color:var(--text-muted);font-size:.86rem;max-width:62ch}
    .cc-idx-actions{display:flex;gap:.6rem;flex-wrap:wrap}
    .cc-idx-ghost{display:inline-flex;align-items:center;gap:.5rem;padding:.62rem 1.05rem;border-radius:var(--radius-sm);text-decoration:none;font-weight:600;font-size:.88rem;background:var(--glass);color:var(--text);border:1px solid var(--stroke);transition:background .18s,border-color .18s;min-height:44px}
    .cc-idx-ghost:hover{background:var(--glass-2);border-color:var(--stroke-2);color:var(--text)}
    .cc-idx-ghost .cc-ico{width:16px;height:16px}

    /* Chips — bloque copiado de admin/consumables/index.blade.php (no está centralizado). */
    .cc-chip{display:inline-flex;align-items:center;gap:.35rem;font-size:.72rem;font-weight:700;letter-spacing:.02em;padding:.28rem .58rem;border-radius:999px;border:1px solid transparent;line-height:1;white-space:nowrap}
    .cc-chip-ok{color:var(--ok);background:color-mix(in srgb,var(--ok) 15%,transparent);border-color:color-mix(in srgb,var(--ok) 32%,transparent)}
    .cc-chip-warn{color:var(--warn);background:color-mix(in srgb,var(--warn) 16%,transparent);border-color:color-mix(in srgb,var(--warn) 32%,transparent)}
    .cc-chip-danger{color:var(--danger);background:color-mix(in srgb,var(--danger) 16%,transparent);border-color:color-mix(in srgb,var(--danger) 34%,transparent)}
    .cc-chip-neutral{color:var(--text-muted);background:var(--glass-2);border-color:var(--stroke)}
    .cc-chip .cc-ico{width:12px;height:12px}

    /* ── Barra de filtros (cliente) ─────────────────────────────────────────── */
    .fx-filters{display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem}
    .fx-field{position:relative;flex:1 1 260px;min-width:0}
    .fx-field .cc-ico{position:absolute;left:.8rem;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--text-muted);pointer-events:none}
    /* Controles con tokens propios: los .form-control de Bootstrap no están tematizados
       en oscuro en este repo, y aquí sí necesitamos contraste AA en ambos temas. */
    .fx-input,.fx-select{width:100%;min-height:44px;font-size:1rem;color:var(--text);background:var(--glass);
        border:1px solid var(--stroke);border-radius:var(--radius-sm);padding:.55rem .8rem;transition:border-color .18s,background .18s}
    .fx-input{padding-left:2.3rem}
    .fx-input::placeholder{color:var(--text-muted);opacity:1}
    .fx-input:focus,.fx-select:focus{outline:none;background:var(--glass-2);border-color:var(--brand-primary);box-shadow:0 0 0 3px var(--brand-glow)}
    .fx-select{flex:0 1 260px;width:auto;min-width:200px}
    /* El desplegable nativo hereda del sistema: fijamos fondo opaco o en oscuro se pierde. */
    .fx-select option{background:var(--bg-2);color:var(--text)}
    .fx-count{color:var(--text-muted);font-size:.82rem;margin-left:auto;white-space:nowrap}

    /* ── Rejilla de tarjetas del catálogo ──────────────────────────────────────
       Calca del lenguaje de tarjetas de la vertical de inspección
       (resources/views/inspection/_tool-card.blade.php + componentes/_inspection-styles),
       tintado a --brand-primary para que toda la página lea como una sola superficie.
       Antes esto era una tabla que solo se volvía tarjeta bajo 767px; ahora es rejilla
       en TODOS los anchos. Auto-fill: una columna en teléfono y tantas como quepan en
       escritorio, SIN breakpoints que mantener. El vidrio y el borde los pone la .card
       global (_brand-theme); aquí solo el layout, el hero, el hover y el CTA. */
    .fx-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,260px),1fr));gap:1rem;align-items:stretch}
    .fx-card{display:flex;flex-direction:column;overflow:hidden;height:100%;
        transition:transform .18s var(--ease,cubic-bezier(.16,1,.3,1)),border-color .18s,box-shadow .18s}
    .fx-card:hover{transform:translateY(-3px);border-color:var(--stroke-2);box-shadow:0 16px 34px -14px rgba(0,0,0,.5),var(--shadow) !important}
    .fx-card[hidden]{display:none !important}

    /* Hero sin foto (como las cards de herramienta): mono-icono sobre degradado de marca. */
    .fx-card__hero{position:relative;aspect-ratio:16/10;
        background:linear-gradient(135deg,color-mix(in srgb,var(--brand-primary) 20%,var(--surface-3)),var(--surface-3))}
    @supports not (aspect-ratio:1/1){.fx-card__hero{height:150px}}
    .fx-card__mono{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:color-mix(in srgb,var(--brand-primary) 60%,var(--text-muted));opacity:.85}
    .fx-card__mono .cc-ico{width:44px;height:44px}
    .fx-card__code{position:absolute;top:.55rem;left:.65rem;font-size:.68rem;font-weight:700;letter-spacing:.06em;font-variant-numeric:tabular-nums;color:color-mix(in srgb,var(--brand-primary) 72%,var(--text));opacity:.92}

    .fx-card__body{padding:.8rem .85rem .35rem;display:flex;flex-direction:column;gap:.35rem;flex:1}
    .fx-card__name{font-size:1.02rem;font-weight:700;color:var(--text);line-height:1.2;text-decoration:none}
    .fx-card__name:hover{color:var(--brand-primary);text-decoration:underline}
    .fx-card__meta{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center}
    .fx-uses{display:inline-flex;align-items:center;gap:.35rem;color:var(--text);font-weight:700;font-size:.78rem;font-variant-numeric:tabular-nums}
    .fx-uses .cc-ico{width:13px;height:13px;color:var(--text-muted)}
    .fx-uses-none{color:var(--text-muted);font-weight:600;font-size:.78rem}
    /* «Qué mirar» del tool-card, aquí «Riesgo principal»: la doctrina que la tabla
       llevaba en su columna ancha, sin perderla. */
    .fx-card__risk{margin-top:.15rem;padding:.45rem .55rem;border-radius:var(--radius-sm);font-size:.8rem;line-height:1.35;
        background:color-mix(in srgb,var(--brand-primary) 7%,var(--surface-3));color:var(--text);display:flex;gap:.4rem}
    .fx-card__risk .cc-ico{width:14px;height:14px;flex:none;margin-top:.15rem;color:var(--brand-primary)}
    .fx-card__risk .fx-risk-label{font-weight:700}

    .fx-card__actions{display:flex;gap:.4rem;padding:.5rem .85rem .85rem;margin-top:auto}
    .fx-card__open{flex:1;display:inline-flex;align-items:center;justify-content:center;gap:.45rem;min-height:44px;
        background:var(--brand-primary);color:var(--brand-on-primary);border:1px solid var(--brand-primary);
        border-radius:var(--radius-sm);font-weight:700;font-size:.85rem;text-decoration:none;transition:filter .18s,transform .18s}
    .fx-card__open:hover{filter:brightness(1.05);color:var(--brand-on-primary)}
    .fx-card__open .cc-ico{width:15px;height:15px}

    .cc-idx-empty{text-align:center;padding:3rem 1.5rem;color:var(--text-muted)}
    .cc-idx-empty .cc-ico{width:42px;height:42px;color:var(--text-muted);opacity:.55;margin-bottom:.7rem}
    .cc-idx-empty .fw-semibold{color:var(--text)}
    .cc-idx-empty p{max-width:52ch;margin:.4rem auto 0;font-size:.85rem}

    /* En teléfono el contador baja de línea (el auto-fill de .fx-cards ya colapsa a una
       sola columna solo con la rejilla, sin más breakpoints). */
    @media (max-width:600px){
        .fx-count{margin-left:0}
    }
</style>
@endpush

@section('content')
@php
    $effects = collect($effects);

    /**
     * «Aún no hay efectos capturados» y «este módulo no está instalado en esta
     * instancia» son dos mensajes MUY distintos, y una colección vacía no los
     * distingue: por eso el controlador manda la bandera aparte. El fallback al
     * modelo cubre a quien renderice esta vista sin pasarla.
     */
    $layerA = isset($layerAvailable) ? $layerAvailable : \App\Models\SfxEffectType::isAvailable();

    // Opciones del filtro SACADAS DE LOS DATOS (nunca una lista a mano).
    $families = $effects->pluck('family')
        ->filter(function ($f) { return $f !== null && trim((string) $f) !== ''; })
        ->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();
@endphp

<div class="container-fluid py-4 px-3 px-md-4">

    <div class="cc-idx-head">
        <div class="cc-idx-headline">
            <span class="cc-idx-icon">@include('componentes._icon', ['name' => 'zap', 'label' => 'Catálogo de efectos'])</span>
            <div>
                <div class="cc-idx-eyebrow">Seguridad · SFX · Catálogo</div>
                <h1 class="cc-idx-title">Catálogo de tipos de efecto</h1>
                <p class="cc-idx-sub">
                    Doctrina reutilizable por tipo de efecto: qué es, su riesgo principal, su control base y su
                    normativa. <strong>No es la bitácora de disparos en set</strong> — eso es el Panel SFX en vivo.
                </p>
            </div>
        </div>
        <div class="cc-idx-actions">
            <a href="{{ route('sfx.index') }}" class="cc-idx-ghost">
                @include('componentes._icon', ['name' => 'flame']) Panel SFX en vivo
            </a>
            <a href="{{ route('consumables.index') }}" class="cc-idx-ghost">
                @include('componentes._icon', ['name' => 'droplet']) SDS / Consumibles
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-3" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 rounded-3" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    @if(!$layerA)
        {{-- BD sin el delta de la Capa A: no es un error, el módulo simplemente aún no existe. --}}
        <div class="card border-0 rounded-3">
            <div class="card-body">
                <div class="cc-idx-empty">
                    @include('componentes._icon', ['name' => 'zap', 'label' => 'Catálogo no disponible'])
                    <div class="fw-semibold">El catálogo de tipos de efecto aún no está disponible.</div>
                    <p>
                        Esta instancia todavía no tiene aplicada la base de datos del catálogo SPFX.
                        En cuanto se aplique, los tipos de efecto aparecerán aquí solos —
                        mientras tanto, el Panel SFX en vivo y las hojas SDS siguen funcionando con normalidad.
                    </p>
                </div>
            </div>
        </div>
    @else

        <div class="fx-filters">
            <div class="fx-field">
                <label for="fx-q" class="visually-hidden">Buscar tipo de efecto</label>
                @include('componentes._icon', ['name' => 'search'])
                <input type="search" id="fx-q" class="fx-input" autocomplete="off"
                       placeholder="Buscar por nombre, clave, familia o riesgo…">
            </div>
            <label for="fx-family" class="visually-hidden">Filtrar por familia</label>
            <select id="fx-family" class="fx-select">
                <option value="">Todas las familias</option>
                @foreach($families as $family)
                    <option value="{{ $family }}">{{ $family }}</option>
                @endforeach
            </select>
            <div class="fx-count" id="fx-count" aria-live="polite"></div>
        </div>

        @if($effects->isEmpty())
            {{-- Catálogo VACÍO (0 tipos de efecto sembrados): distinto de «nada casa el
                 filtro», que es el bloque #fx-noresults de más abajo. --}}
            <div class="card border-0 rounded-3">
                <div class="card-body">
                    <div class="cc-idx-empty">
                        @include('componentes._icon', ['name' => 'zap', 'label' => 'Catálogo vacío'])
                        <div class="fw-semibold">Aún no hay tipos de efecto en el catálogo.</div>
                        <p>El catálogo SPFX se importa desde el documento fuente; en cuanto se siembre, aparecerá aquí.</p>
                    </div>
                </div>
            </div>
        @else
            {{-- Rejilla de tarjetas (auto-fill) en TODOS los anchos. Cada tarjeta conserva
                 lo que la tabla mostraba: clave (hero), nombre (enlace), familia, insumos
                 ligados, riesgo principal, estado de verificación y el acceso a la ficha.
                 El #fx-rows y los data-* los consume el mismo filtro en cliente de siempre. --}}
            <div class="fx-cards" id="fx-rows">
                @foreach($effects as $effect)
                    @php
                        /**
                         * Conteo de insumos ligados: llega en la misma consulta vía el
                         * withCount() del controlador. El fallback existe para que quitar
                         * ese withCount no degrade a un «Sin insumos» FALSO en silencio
                         * (aquí ya estamos dentro de $layerA: el pivote existe).
                         */
                        if (isset($effect->consumables_count)) {
                            $uses = (int) $effect->consumables_count;
                        } elseif ($effect->relationLoaded('consumables')) {
                            $uses = $effect->consumables->count();
                        } else {
                            $uses = $effect->consumables()->count();
                        }

                        $haystack = trim(implode(' ', array_filter([
                            $effect->name, $effect->code, $effect->family, $effect->main_risk,
                        ])));
                    @endphp
                    <article class="fx-card card border-0 rounded-3" data-family="{{ $effect->family }}" data-search="{{ $haystack }}">
                        <div class="fx-card__hero">
                            @if($effect->code)
                                <span class="fx-card__code">{{ $effect->code }}</span>
                            @endif
                            <span class="fx-card__mono">@include('componentes._icon', ['name' => 'zap', 'label' => null])</span>
                        </div>

                        <div class="fx-card__body">
                            <a class="fx-card__name" href="{{ route('sfx-effects.show', $effect->id) }}">{{ $effect->name }}</a>

                            <div class="fx-card__meta">
                                @if($effect->family)
                                    <span class="cc-chip cc-chip-neutral">{{ $effect->family }}</span>
                                @endif

                                @if($uses > 0)
                                    <span class="fx-uses" title="Insumos SDS ligados a este tipo de efecto">
                                        @include('componentes._icon', ['name' => 'flask-conical'])
                                        {{ $uses }} {{ $uses === 1 ? 'insumo' : 'insumos' }}
                                    </span>
                                @else
                                    <span class="fx-uses-none">Sin insumos</span>
                                @endif

                                @if($effect->isVerified())
                                    @php
                                        /**
                                         * `verified_by_id` NULL y CON valor son DOS cosas distintas y no se
                                         * pueden colapsar en una sola frase. Medido en esta BD: los 25 tipos
                                         * de efecto tienen 17 con `verified_at` y CERO con `verified_by_id`
                                         * — el sello lo sembró el import, no lo puso nadie. El título fijo
                                         * de antes («validada por un responsable de SDS») le atribuía a un
                                         * humano un acto de gobierno que NUNCA ocurrió, en los 17: quien
                                         * pasa el cursor concluía que una autoridad avaló el EPP mínimo, el
                                         * personal certificado y la normativa de ese efecto.
                                         * Sus dos vistas hermanas ya degradan bien (sfx-effects/show arma el
                                         * título sin el «por X» cuando verifiedBy es null; consumables/show
                                         * dice «Verificada de origen (catálogo base)»): este index era el
                                         * único que afirmaba la persona inexistente.
                                         * Se decide con la COLUMNA, no con la relación `verifiedBy`: el
                                         * controlador no la trae y tocarla aquí sería una consulta por fila.
                                         * PHP 7.4: sin nullsafe ni match.
                                         */
                                        $fxSeal = $effect->verified_by_id === null
                                            ? 'Sello de origen: la ficha viene validada del catálogo SPFX (' . $effect->verified_at->format('d/m/Y') . '). Ningún responsable de SDS la ha revisado en esta instancia.'
                                            : 'Ficha del catálogo validada por un responsable de SDS el ' . $effect->verified_at->format('d/m/Y') . '.';
                                    @endphp
                                    <span class="cc-chip cc-chip-ok" title="{{ $fxSeal }}">
                                        @include('componentes._icon', ['name' => 'check-circle']) Verificado
                                    </span>
                                @else
                                    <span class="cc-chip cc-chip-warn" title="Ficha del catálogo aún no validada por un responsable de SDS.">
                                        @include('componentes._icon', ['name' => 'alert-triangle']) Pendiente de verificación
                                    </span>
                                @endif
                            </div>

                            <div class="fx-card__risk">
                                @include('componentes._icon', ['name' => 'alert-triangle'])
                                <span><span class="fx-risk-label">Riesgo principal:</span> {{ $effect->main_risk ?: '—' }}</span>
                            </div>
                        </div>

                        <div class="fx-card__actions">
                            <a class="fx-card__open" href="{{ route('sfx-effects.show', $effect->id) }}">
                                @include('componentes._icon', ['name' => 'eye']) Ver ficha
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Empty-state del FILTRO — aquí SÍ hay fichas, solo que ninguna casa. Lo alterna
                 el mismo JS de siempre (ahora conmuta [hidden] sobre este bloque, no un <tr>). --}}
            <div id="fx-noresults" hidden>
                <div class="card border-0 rounded-3">
                    <div class="card-body">
                        <div class="cc-idx-empty">
                            @include('componentes._icon', ['name' => 'search', 'label' => 'Sin coincidencias'])
                            <div class="fw-semibold">Ningún tipo de efecto coincide con el filtro.</div>
                            <p>Prueba con otra palabra o vuelve a «Todas las familias».</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif

</div>
@endsection

@push('scripts')
<script>
(function () {
    var q      = document.getElementById('fx-q');
    var family = document.getElementById('fx-family');
    var body   = document.getElementById('fx-rows');
    if (!q || !family || !body) { return; }

    var rows  = Array.prototype.slice.call(body.querySelectorAll('.fx-card[data-search]'));
    var none  = document.getElementById('fx-noresults');
    var count = document.getElementById('fx-count');

    // Sin acentos y en minúsculas: "atmosfera" debe encontrar "Atmósfera".
    function fold(s) {
        return (s || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    var index = rows.map(function (row) {
        return { row: row, hay: fold(row.getAttribute('data-search')), fam: row.getAttribute('data-family') || '' };
    });

    function apply() {
        var needle = fold(q.value).trim();
        var fam    = family.value;
        var shown  = 0;

        index.forEach(function (item) {
            var ok = (!fam || item.fam === fam) && (!needle || item.hay.indexOf(needle) !== -1);
            item.row.hidden = !ok;
            if (ok) { shown++; }
        });

        if (none) { none.hidden = (shown > 0 || index.length === 0); }
        if (count) {
            count.textContent = index.length
                ? (shown === index.length
                    ? index.length + (index.length === 1 ? ' tipo de efecto' : ' tipos de efecto')
                    : shown + ' de ' + index.length)
                : '';
        }
    }

    q.addEventListener('input', apply);
    family.addEventListener('change', apply);
    apply();
})();
</script>
@endpush
@endfeature
