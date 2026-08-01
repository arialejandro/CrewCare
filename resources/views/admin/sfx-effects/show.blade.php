@extends('layouts.app')
@section('title', 'Ficha de tipo de efecto SFX')

{{--
    admin/sfx-effects/show.blade.php — FICHA de un tipo de efecto (Capa A, Paso 4d).

    ⚠ NO ES EL PANEL SFX EN VIVO (`sfx.index`). Esto es DOCTRINA: qué es este tipo de
    efecto, con qué se hace, qué riesgo tiene y qué norma lo rige. No se dispara nada
    desde aquí y nada de lo que se toque aquí entra al DSR del día.

    ── LOS DOS JSON NORMATIVOS COMPARTEN EJE: JURISDICCIÓN (no organismo emisor) ──
    `standards_snapshot` {csatf, eeuu_ca, mexico, eeuu_fed} y `certified_personnel`
    {eeuu_ca, mexico} se recorren con el MISMO mapa ordenado ($jurisdictions).

    ⚠ POR QUÉ UN MAPA Y NO UN @foreach A CIEGAS: MySQL 5.7 REORDENA las claves de un
    JSON nativo (por longitud y luego binario). Verificado en esta BD: se guardó
    {csatf, eeuu_ca, mexico} y regresa {csatf, mexico, eeuu_ca}. Iterar el JSON crudo
    pintaría las jurisdicciones en un orden que nadie eligió. Mismo motivo por el que
    la HDS de 16 secciones se itera con Consumable::sdsSectionLabels().

    `eeuu_fed` existe en el contrato pero HOY VIENE VACÍA en los 25 efectos: verla
    vacía NO es un bug. No se pinta, y NO se pone un «—» que sugiera un hueco.

    PHP 7.4: sin nullsafe ni match.
--}}

@feature('sds_sfx')
@push('styles')
<style>
    /* ── Ficha glass · lenguaje Cinematic Dark Glass (tokens de _brand-theme) ── */
    .fx-back{display:inline-flex;align-items:center;gap:.4rem;min-height:44px;color:var(--text-muted);text-decoration:none;font-size:.85rem;font-weight:600;margin-bottom:.75rem}
    .fx-back:hover{color:var(--text)}
    .fx-back .cc-ico{width:15px;height:15px}

    .cc-idx-head{display:flex;align-items:flex-start;gap:1rem;flex-wrap:wrap;justify-content:space-between;margin-bottom:1.5rem}
    .cc-idx-headline{display:flex;align-items:flex-start;gap:1rem;min-width:0}
    .cc-idx-icon{width:48px;height:48px;border-radius:14px;flex:none;display:inline-flex;align-items:center;justify-content:center;
        color:var(--brand-primary);background:color-mix(in srgb,var(--brand-primary) 15%,transparent);border:1px solid color-mix(in srgb,var(--brand-primary) 28%,transparent)}
    .cc-idx-icon .cc-ico{width:22px;height:22px}
    .cc-idx-eyebrow{font-size:.66rem;letter-spacing:.2em;text-transform:uppercase;color:var(--brand-primary);font-weight:700}
    .cc-idx-title{margin:.15rem 0 .5rem;font-family:'Poppins',sans-serif;font-weight:800;letter-spacing:-.02em;font-size:1.5rem;color:var(--text);line-height:1.1}
    .fx-meta{display:flex;gap:.4rem;flex-wrap:wrap;align-items:center}
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

    /* ── Bloques de la ficha ────────────────────────────────────────────────── */
    /* SIN height:100% (2026-07-24 · bug de solape).
       Lo tenía para igualar la altura de dos tarjetas lado a lado, pero esta ficha APILA 10 y 8
       tarjetas dentro de cada columna. En una .row de Bootstrap la columna es un flex item
       estirado, así que `height:100%` hacía que CADA tarjeta midiera el alto COMPLETO de la
       columna: diez tarjetas al 100% = 1000% de alto, desbordando la columna y dibujándose unas
       encima de otras. La igualación sólo aplica cuando la columna trae una sola tarjeta, y eso
       es exactamente lo que dice :only-child. */
    .fx-card{background:var(--glass);border:1px solid var(--stroke);border-radius:var(--radius);padding:1.15rem 1.25rem}
    .row > [class*="col"] > .fx-card:only-child{height:100%}
    .fx-card + .fx-card{margin-top:1rem}
    .fx-card-title{display:flex;align-items:center;gap:.5rem;font-family:'Poppins',sans-serif;font-weight:700;font-size:.95rem;color:var(--text);margin:0 0 .7rem}
    .fx-card-title .cc-ico{width:17px;height:17px;color:var(--text-muted);flex:none}
    .fx-body{color:var(--text);font-size:.95rem;line-height:1.55;margin:0}
    .fx-hint{color:var(--text-muted);font-size:.78rem;line-height:1.5;margin:.55rem 0 0}

    /* Acentos semánticos: el riesgo tira a rojo, el control base a verde. */
    .fx-card-risk{border-color:color-mix(in srgb,var(--danger) 30%,transparent);background:color-mix(in srgb,var(--danger) 7%,var(--glass))}
    .fx-card-risk .fx-card-title,.fx-card-risk .fx-card-title .cc-ico{color:var(--danger)}
    .fx-card-control{border-color:color-mix(in srgb,var(--ok) 28%,transparent);background:color-mix(in srgb,var(--ok) 6%,var(--glass))}
    .fx-card-control .fx-card-title,.fx-card-control .fx-card-title .cc-ico{color:var(--ok)}

    /* Listas de viñeta (variantes, EPP, normas, personal). */
    .fx-list{list-style:none;margin:0;padding:0}
    .fx-list li{position:relative;padding:.3rem 0 .3rem 1.1rem;color:var(--text);font-size:.9rem;line-height:1.5}
    .fx-list li::before{content:"";position:absolute;left:0;top:.85em;width:5px;height:5px;border-radius:50%;background:var(--brand-primary);opacity:.75}
    .fx-list li + li{border-top:1px solid var(--stroke)}

    /* Jurisdicciones (mismo eje en normativa y personal certificado). */
    .fx-jur + .fx-jur{margin-top:.9rem;padding-top:.9rem;border-top:1px solid var(--stroke)}
    .fx-jur-name{display:flex;align-items:center;gap:.4rem;font-size:.7rem;letter-spacing:.12em;text-transform:uppercase;font-weight:700;color:var(--brand-primary);margin:0 0 .15rem}
    .fx-jur-hint{color:var(--text-muted);font-size:.75rem;line-height:1.45;margin:0 0 .35rem}

    /* ── Insumos ligados (la N:M) ───────────────────────────────────────────── */
    .fx-link-item{display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;flex-wrap:wrap;
        padding:.85rem;border:1px solid var(--stroke);border-radius:var(--radius-sm);background:var(--glass-2)}
    .fx-link-item + .fx-link-item{margin-top:.6rem}
    .fx-link-main{min-width:0;flex:1 1 260px}
    .fx-link-name{color:var(--text);font-weight:600;font-size:.95rem;text-decoration:none}
    .fx-link-name:hover{color:var(--brand-primary);text-decoration:underline}
    .fx-link-tags{display:flex;gap:.35rem;flex-wrap:wrap;margin-top:.4rem}
    .fx-del{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;min-height:44px;min-width:44px;padding:.5rem .8rem;
        border-radius:var(--radius-sm);border:1px solid var(--stroke);background:var(--glass);color:var(--text-muted);font-size:.8rem;font-weight:600;cursor:pointer}
    .fx-del:hover{color:var(--danger);border-color:color-mix(in srgb,var(--danger) 45%,transparent);background:color-mix(in srgb,var(--danger) 8%,transparent)}
    .fx-del .cc-ico{width:14px;height:14px}

    /* ── Gestión: asociar insumo (solo sds.manage) ──────────────────────────── */
    .fx-manage{margin-top:1.1rem;padding-top:1.1rem;border-top:1px dashed var(--stroke-2)}
    .fx-manage-note{color:var(--text-muted);font-size:.78rem;line-height:1.5;margin:0 0 .8rem}
    .fx-attach-row{display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end}
    .fx-label{display:block;font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;font-weight:700;color:var(--text-muted);margin-bottom:.3rem}
    .fx-field{position:relative;flex:1 1 220px;min-width:0}
    .fx-field .cc-ico{position:absolute;left:.8rem;bottom:14px;width:16px;height:16px;color:var(--text-muted);pointer-events:none}
    /* Controles con tokens propios: los .form-select de Bootstrap no están tematizados
       en oscuro en este repo, y aquí sí necesitamos contraste AA en ambos temas. */
    .fx-input,.fx-select{width:100%;min-height:44px;font-size:1rem;color:var(--text);background:var(--glass);
        border:1px solid var(--stroke);border-radius:var(--radius-sm);padding:.55rem .8rem}
    .fx-input{padding-left:2.3rem}
    .fx-input::placeholder{color:var(--text-muted);opacity:1}
    .fx-input:focus,.fx-select:focus{outline:none;background:var(--glass-2);border-color:var(--brand-primary);box-shadow:0 0 0 3px var(--brand-glow)}
    /* El desplegable nativo hereda del sistema: fijamos fondo opaco o en oscuro se pierde. */
    .fx-select option,.fx-select optgroup{background:var(--bg-2);color:var(--text)}
    .fx-cta{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;min-height:44px;padding:.62rem 1.1rem;border-radius:var(--radius-sm);
        font-weight:700;font-size:.88rem;background:var(--brand-primary);color:var(--brand-on-primary);border:1px solid var(--brand-primary);
        box-shadow:0 10px 26px -12px var(--brand-glow);cursor:pointer;white-space:nowrap}
    .fx-cta:hover{filter:brightness(1.04)}
    .fx-cta .cc-ico{width:16px;height:16px}
    .fx-matches{color:var(--text-muted);font-size:.75rem;margin:.45rem 0 0}

    .cc-idx-empty{text-align:center;padding:2.5rem 1.5rem;color:var(--text-muted)}
    .cc-idx-empty .cc-ico{width:42px;height:42px;color:var(--text-muted);opacity:.55;margin-bottom:.7rem}
    .cc-idx-empty .fw-semibold{color:var(--text)}
    .cc-idx-empty p{max-width:52ch;margin:.4rem auto 0;font-size:.85rem}
</style>
@endpush

@section('content')
{{-- Confirmación del submit de «Quitar» (desasociar insumo) sin JS inline. --}}
@include('componentes._confirm-submit')
@php
    $candidates = collect($candidates);

    /**
     * Sin la Capa A el controlador ya CORTA (layerGuard) y no se llega aquí. Este
     * cinturón cubre a quien renderice la vista por su cuenta: NUNCA ejecutar la N:M
     * sin comprobarlo — declarar el belongsToMany es perezoso y no truena, pero
     * EJECUTARLO contra tablas inexistentes sí.
     */
    $layerA = \App\Models\SfxEffectType::isAvailable();

    /**
     * Eje JURISDICCIÓN — orden canónico y etiquetas humanas. Se usa IGUAL en
     * `standards_snapshot` y en `certified_personnel` para que el ojo las mapee.
     * La clave cruda («eeuu_ca») no se enseña nunca.
     */
    $jurisdictions = [
        'csatf' => [
            'label' => 'Boletines CSATF',
            'hint'  => 'Referencia contractual: los estudios de EE. UU. los exigen por contrato aunque la producción esté bajo jurisdicción mexicana.',
        ],
        'eeuu_ca' => [
            'label' => 'California, EE. UU.',
            'hint'  => 'Cal-OSHA Título 8, Fire Code, SB 132 y permisos de la autoridad local (AHJ).',
        ],
        'mexico' => [
            'label' => 'México',
            'hint'  => 'NOM de la STPS, SEDENA, Ley Federal de Armas de Fuego y Explosivos, Protección Civil.',
        ],
        'eeuu_fed' => [
            'label' => 'Federal, EE. UU.',
            'hint'  => 'OSHA 29 CFR.',
        ],
    ];

    /** Normaliza a lista limpia: los valores SIEMPRE son listas, pero no se confía. */
    $asList = function ($raw) {
        if (!is_array($raw)) {
            $raw = ($raw === null || trim((string) $raw) === '') ? [] : [$raw];
        }
        $out = [];
        foreach ($raw as $item) {
            if ($item === null || is_array($item) || trim((string) $item) === '') {
                continue;
            }
            $out[] = $item;
        }
        return $out;
    };

    /**
     * Ordena un JSON de jurisdicciones por el mapa canónico (MySQL devuelve las claves
     * en su propio orden) y descarta las vacías — «eeuu_fed» sin datos NO se pinta.
     * Una jurisdicción futura que el catálogo agregue no se pierde: va al final.
     */
    $byJurisdiction = function ($raw) use ($jurisdictions, $asList) {
        $out = [];
        if (!is_array($raw)) {
            return $out;
        }
        foreach (array_keys($jurisdictions) as $key) {
            if (!array_key_exists($key, $raw)) {
                continue;
            }
            $items = $asList($raw[$key]);
            if (!empty($items)) {
                $out[$key] = $items;
            }
        }
        foreach ($raw as $key => $value) {
            if (isset($jurisdictions[$key])) {
                continue;
            }
            $items = $asList($value);
            if (!empty($items)) {
                $out[$key] = $items;
            }
        }
        return $out;
    };

    $variants  = $asList($effect->variants);
    $ppe       = $asList($effect->required_ppe);
    $personnel = $byJurisdiction($effect->certified_personnel);
    $standards = $byJurisdiction($effect->standards_snapshot);

    // Insumos ligados (N:M). Solo si la Capa A existe.
    $linked = collect();
    if ($layerA) {
        $linked = $effect->relationLoaded('consumables')
            ? $effect->consumables
            : $effect->consumables()->orderBy('name')->get();
    }

    // Candidatos agrupados por familia de material: un <select> plano de ~50 en un
    // teléfono es hostil. «Sin familia» al final (son las 12 fichas legacy sin `code`).
    $candidateGroups = [];
    foreach ($candidates as $candidate) {
        $family = ($candidate->material_family !== null && trim((string) $candidate->material_family) !== '')
            ? $candidate->material_family
            : 'Sin familia asignada';
        $candidateGroups[$family][] = $candidate;
    }
    ksort($candidateGroups, SORT_NATURAL | SORT_FLAG_CASE);
    if (isset($candidateGroups['Sin familia asignada'])) {
        $loose = $candidateGroups['Sin familia asignada'];
        unset($candidateGroups['Sin familia asignada']);
        $candidateGroups['Sin familia asignada'] = $loose;
    }
@endphp

<div class="container-fluid py-4 px-3 px-md-4">

    <a href="{{ route('sfx-effects.index') }}" class="fx-back">
        @include('componentes._icon', ['name' => 'chevron-left']) Volver al catálogo de tipos de efecto
    </a>

    @if(!$layerA)
        {{-- BD sin el delta de la Capa A: no es un error, el módulo aún no existe aquí. --}}
        <div class="card border-0 rounded-3">
            <div class="card-body">
                <div class="cc-idx-empty">
                    @include('componentes._icon', ['name' => 'zap', 'label' => 'Catálogo no disponible'])
                    <div class="fw-semibold">El catálogo de tipos de efecto aún no está disponible.</div>
                    <p>Esta instancia todavía no tiene aplicada la base de datos del catálogo SPFX.</p>
                </div>
            </div>
        </div>
    @else

    <div class="cc-idx-head">
        <div class="cc-idx-headline">
            <span class="cc-idx-icon">@include('componentes._icon', ['name' => 'zap', 'label' => 'Tipo de efecto'])</span>
            <div>
                <div class="cc-idx-eyebrow">Seguridad · SFX · Catálogo · Ficha</div>
                <h1 class="cc-idx-title">{{ $effect->name }}</h1>
                <div class="fx-meta">
                    @if($effect->code)
                        <span class="cc-chip cc-chip-neutral">{{ $effect->code }}</span>
                    @endif
                    @if($effect->family)
                        <span class="cc-chip cc-chip-neutral">{{ $effect->family }}</span>
                    @endif
                    @if($effect->isVerified())
                        @php
                            // El rastro de quién selló: el controlador ya cargó verifiedBy. Puede
                            // venir null (sello sembrado por el import, sin persona detrás) → no
                            // se inventa un nombre. Sin nullsafe (PHP 7.4).
                            $sealer = $effect->verifiedBy ? $effect->verifiedBy->name : null;
                            $seal = 'Ficha del catálogo validada'
                                . ($sealer ? ' por ' . $sealer : '')
                                . ' el ' . $effect->verified_at->format('d/m/Y') . '.';
                        @endphp
                        <span class="cc-chip cc-chip-ok" title="{{ $seal }}">
                            @include('componentes._icon', ['name' => 'check-circle']) Verificado
                        </span>
                    @else
                        <span class="cc-chip cc-chip-warn" title="Ficha del catálogo aún no validada por un responsable de SDS.">
                            @include('componentes._icon', ['name' => 'alert-triangle']) Pendiente de verificación
                        </span>
                    @endif
                    @if(!$effect->is_active)
                        <span class="cc-chip cc-chip-neutral">Inactivo</span>
                    @endif
                </div>
            </div>
        </div>
        <a href="{{ route('sfx.index') }}" class="cc-idx-ghost">
            @include('componentes._icon', ['name' => 'flame']) Panel SFX en vivo
        </a>
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
    @if($errors->any())
        <div class="alert alert-danger shadow-sm border-0 rounded-3" role="alert">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">

        {{-- ─────────── Columna doctrinal ─────────── --}}
        <div class="col-12 col-lg-7">

            @if($effect->definition)
                <div class="fx-card">
                    <h2 class="fx-card-title">
                        @include('componentes._icon', ['name' => 'info']) Definición
                    </h2>
                    <p class="fx-body">{{ $effect->definition }}</p>
                </div>
            @endif

            @if($effect->main_risk)
                <div class="fx-card fx-card-risk">
                    <h2 class="fx-card-title">
                        @include('componentes._icon', ['name' => 'alert-triangle']) Riesgo principal
                    </h2>
                    <p class="fx-body">{{ $effect->main_risk }}</p>
                </div>
            @endif

            @if($effect->base_control)
                <div class="fx-card fx-card-control">
                    <h2 class="fx-card-title">
                        @include('componentes._icon', ['name' => 'shield']) Control base
                    </h2>
                    <p class="fx-body">{{ $effect->base_control }}</p>
                </div>
            @endif

            @if(!empty($variants))
                <div class="fx-card">
                    <h2 class="fx-card-title">
                        @include('componentes._icon', ['name' => 'clipboard-list']) Variantes
                    </h2>
                    <ul class="fx-list">
                        @foreach($variants as $variant)
                            <li>{{ $variant }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($ppe))
                <div class="fx-card">
                    <h2 class="fx-card-title">
                        @include('componentes._icon', ['name' => 'shield-alert']) EPP mínimo requerido
                    </h2>
                    <ul class="fx-list">
                        @foreach($ppe as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

        </div>

        {{-- ─────────── Columna normativa ─────────── --}}
        <div class="col-12 col-lg-5">

            @if(!empty($personnel))
                <div class="fx-card">
                    <h2 class="fx-card-title">
                        @include('componentes._icon', ['name' => 'users']) Personal certificado
                    </h2>
                    @foreach($personnel as $key => $items)
                        <div class="fx-jur">
                            <p class="fx-jur-name">{{ isset($jurisdictions[$key]) ? $jurisdictions[$key]['label'] : $key }}</p>
                            <ul class="fx-list">
                                @foreach($items as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                    <p class="fx-hint">
                        Quién puede operar este efecto según la jurisdicción de la producción.
                    </p>
                </div>
            @endif

            @if(!empty($standards))
                <div class="fx-card">
                    <h2 class="fx-card-title">
                        @include('componentes._icon', ['name' => 'file-text']) Normativa aplicable
                    </h2>
                    @foreach($standards as $key => $items)
                        <div class="fx-jur">
                            <p class="fx-jur-name">{{ isset($jurisdictions[$key]) ? $jurisdictions[$key]['label'] : $key }}</p>
                            @if(isset($jurisdictions[$key]))
                                <p class="fx-jur-hint">{{ $jurisdictions[$key]['hint'] }}</p>
                            @endif
                            <ul class="fx-list">
                                @foreach($items as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                    <p class="fx-hint">
                        Foto de la normativa citada por el catálogo fuente al momento de importarlo.
                        No sustituye la consulta de la norma vigente ni el permiso de la autoridad local.
                    </p>
                </div>
            @endif

            @if($effect->notes)
                <div class="fx-card">
                    <h2 class="fx-card-title">
                        @include('componentes._icon', ['name' => 'pencil']) Notas
                    </h2>
                    <p class="fx-body">{{ $effect->notes }}</p>
                </div>
            @endif

        </div>
    </div>

    {{-- ─────────── Insumos ligados (N:M) + gestión ─────────── --}}
    <div class="fx-card mt-4">
        <h2 class="fx-card-title">
            @include('componentes._icon', ['name' => 'flask-conical']) Insumos de este tipo de efecto
            <span class="cc-chip cc-chip-neutral ms-1">{{ $linked->count() }}</span>
        </h2>
        <p class="fx-hint mb-3" style="margin-top:0">
            Con qué se hace este efecto. El insumo clasifica el <strong>material</strong>; la familia de arriba
            clasifica el <strong>efecto</strong>. Un mismo insumo sirve a varios efectos.
        </p>

        @forelse($linked as $consumable)
            <div class="fx-link-item">
                <div class="fx-link-main">
                    <a class="fx-link-name" href="{{ route('consumables.show', $consumable->id) }}">{{ $consumable->name }}</a>
                    <div class="fx-link-tags">
                        @if($consumable->code)
                            <span class="cc-chip cc-chip-neutral">{{ $consumable->code }}</span>
                        @endif
                        @if($consumable->type_label)
                            <span class="cc-chip cc-chip-neutral">{{ $consumable->type_label }}</span>
                        @endif
                        @if($consumable->material_family)
                            <span class="cc-chip cc-chip-neutral">{{ $consumable->material_family }}</span>
                        @endif
                        @if($consumable->isPendingVerification())
                            {{-- La PROCEDENCIA la manda el modelo: no afirmamos que se capturó en
                                 set algo que vino del import del catálogo. --}}
                            @if($consumable->isFieldCaptured())
                                <span class="cc-chip cc-chip-warn" title="Ficha capturada en set. Sus datos aún no han sido validados por un responsable de SDS.">
                                    @include('componentes._icon', ['name' => 'alert-triangle']) Capturado en set · pendiente de verificación
                                </span>
                            @else
                                <span class="cc-chip cc-chip-warn" title="Ficha importada del catálogo SPFX. Sus datos aún no han sido validados por un responsable de SDS.">
                                    @include('componentes._icon', ['name' => 'alert-triangle']) Importado del catálogo · pendiente de verificación
                                </span>
                            @endif
                        @endif
                    </div>
                </div>
                @can('sds.manage')
                    {{-- data-confirm, NO onsubmit: aquí entran DOS nombres de texto libre
                         ($consumable->name y $effect->name) y en contexto JS cualquiera de los
                         dos rompe el literal con solo llevar un apóstrofo. Verificado en
                         navegador: el «Quitar» del insumo #70 («Tierra de baton (Fuller's
                         earth)») tenía el onsubmit puesto y `form.onsubmit === null`, o sea que
                         el DELETE salía sin preguntar. Ver componentes/_confirm-submit.
                         El atributo conserva los saltos de línea reales del mensaje. --}}
                    <form method="POST" action="{{ route('sfx-effects.consumables.detach', [$effect->id, $consumable->id]) }}"
                          data-confirm="¿Quitar «{{ $consumable->name }}» de los insumos de «{{ $effect->name }}»?

Solo deshace la relación en el catálogo: la ficha del insumo NO se borra.">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="fx-del">
                            @include('componentes._icon', ['name' => 'x']) Quitar
                        </button>
                    </form>
                @endcan
            </div>
        @empty
            <div class="cc-idx-empty" style="padding:2rem 1rem">
                @include('componentes._icon', ['name' => 'flask-conical', 'label' => 'Sin insumos ligados'])
                <div class="fw-semibold">Este tipo de efecto aún no tiene insumos ligados.</div>
                @can('sds.manage')
                    <p>Asócialos abajo para dejar constancia de con qué materiales se ejecuta.</p>
                @endcan
            </div>
        @endforelse

        @can('sds.manage')
            <div class="fx-manage">
                <p class="fx-manage-note">
                    <strong>Gestión de catálogo</strong> — aquí defines la doctrina: con qué insumos se hace este tipo
                    de efecto, en general. <strong>No es la captura de un reporte</strong>: no registra nada en set,
                    no dispara nada y no entra al DSR del día.
                </p>

                @if($candidates->isEmpty())
                    <p class="fx-hint" style="margin-top:0">
                        No hay insumos activos disponibles para asociar: todos los del catálogo ya están ligados a este efecto.
                    </p>
                @else
                    <form method="POST" action="{{ route('sfx-effects.consumables.attach', $effect->id) }}">
                        @csrf
                        <div class="fx-attach-row">
                            <div class="fx-field" style="flex:1 1 220px">
                                <label class="fx-label" for="fx-cand-q">Buscar insumo</label>
                                @include('componentes._icon', ['name' => 'search'])
                                <input type="search" id="fx-cand-q" class="fx-input" autocomplete="off"
                                       placeholder="Nombre, clave o familia…">
                            </div>
                            <div style="flex:1 1 280px;min-width:0">
                                <label class="fx-label" for="fx-attach-select">Insumo a asociar</label>
                                <select class="fx-select" id="fx-attach-select" name="consumable_id" required>
                                    <option value="" selected disabled>— Elige un insumo —</option>
                                    @foreach($candidateGroups as $family => $group)
                                        <optgroup label="{{ $family }}">
                                            @foreach($group as $candidate)
                                                <option value="{{ $candidate->id }}"
                                                        data-fam="{{ $family }}"
                                                        data-code="{{ $candidate->code }}">{{ $candidate->name }}@if($candidate->type_label) ({{ $candidate->type_label }})@endif</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="fx-cta">
                                @include('componentes._icon', ['name' => 'plus']) Asociar
                            </button>
                        </div>
                        <p class="fx-matches" id="fx-matches" aria-live="polite"></p>
                    </form>
                @endif
            </div>
        @endcan
    </div>

    @endif

</div>
@endsection

@push('scripts')
<script>
(function () {
    var sel = document.getElementById('fx-attach-select');
    var box = document.getElementById('fx-cand-q');
    if (!sel || !box) { return; }

    // Copia maestra: se filtra RECONSTRUYENDO el <select> desde ella, no ocultando
    // <option>s — `hidden` en <option> no es fiable en todos los navegadores móviles.
    var master  = sel.cloneNode(true);
    var matches = document.getElementById('fx-matches');

    function fold(s) {
        return (s || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function hit(option, needle) {
        if (!needle) { return true; }
        return fold(option.textContent + ' ' + (option.getAttribute('data-fam') || '') + ' ' + (option.getAttribute('data-code') || ''))
            .indexOf(needle) !== -1;
    }

    function apply() {
        var needle = fold(box.value).trim();
        var keep   = sel.value;
        var shown  = 0;

        while (sel.firstChild) { sel.removeChild(sel.firstChild); }

        Array.prototype.forEach.call(master.children, function (node) {
            if (node.tagName === 'OPTGROUP') {
                var kids = Array.prototype.filter.call(node.children, function (o) { return hit(o, needle); });
                if (!kids.length) { return; }
                var group = document.createElement('optgroup');
                group.label = node.label;
                kids.forEach(function (o) { group.appendChild(o.cloneNode(true)); shown++; });
                sel.appendChild(group);
            } else if (node.value === '') {
                sel.appendChild(node.cloneNode(true)); // el placeholder nunca se filtra
            }
        });

        // Conserva la selección si sobrevivió al filtro; si no, vuelve al placeholder.
        sel.value = keep;
        if (sel.selectedIndex === -1) { sel.selectedIndex = 0; }

        if (matches) {
            matches.textContent = needle
                ? (shown ? shown + (shown === 1 ? ' insumo coincide' : ' insumos coinciden')
                         : 'Ningún insumo coincide con «' + box.value.trim() + '».')
                : '';
        }
    }

    box.addEventListener('input', apply);
})();
</script>
@endpush
@endfeature
