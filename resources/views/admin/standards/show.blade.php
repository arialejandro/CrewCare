@extends('layouts.app')
@section('title', 'Norma · ' . $standard->regulation_code)

{{-- NO @feature: el catálogo normativo es NÚCLEO. --}}

@push('styles')
<style>
    /* Detalle de norma — lenguaje "Cinematic Dark Glass" (tokens de _brand-theme).
       Hermano de admin/consumables/show, reducido a lo que una norma necesita. */
    .cc-std{max-width:820px;margin:0 auto}

    .cc-chip{display:inline-flex;align-items:center;gap:.35rem;font-size:.72rem;font-weight:700;letter-spacing:.02em;padding:.28rem .58rem;border-radius:999px;border:1px solid transparent;line-height:1;white-space:nowrap}
    .cc-chip-ok{color:var(--ok);background:color-mix(in srgb,var(--ok) 15%,transparent);border-color:color-mix(in srgb,var(--ok) 32%,transparent)}
    .cc-chip-warn{color:var(--warn);background:color-mix(in srgb,var(--warn) 16%,transparent);border-color:color-mix(in srgb,var(--warn) 32%,transparent)}
    .cc-chip-neutral{color:var(--text-muted);background:var(--glass-2);border-color:var(--stroke)}

    .cc-back{display:inline-flex;align-items:center;gap:.4rem;min-height:44px;padding:.35rem .15rem;color:var(--text-muted);text-decoration:none;font-size:.9rem;font-weight:600}
    .cc-back:hover{color:var(--text)}
    .cc-back .cc-ico{width:16px;height:16px}

    /* Aviso de norma pendiente (ámbar, arriba, visible). */
    .cc-pending{display:flex;align-items:flex-start;gap:.7rem;padding:.85rem 1rem;margin-bottom:1rem;border-radius:var(--radius-sm);color:var(--warn);
        background:color-mix(in srgb,var(--warn) 14%,transparent);border:1px solid color-mix(in srgb,var(--warn) 34%,transparent)}
    .cc-pending svg{width:20px;height:20px;flex:none;margin-top:.1rem}
    .cc-pending strong{display:block;font-size:.95rem}
    .cc-pending span{display:block;margin-top:.2rem;color:var(--text);font-size:.85rem;font-weight:500}

    .cc-hero{padding:1.15rem;margin-bottom:1.15rem}
    .cc-hero__eyebrow{font-size:.66rem;letter-spacing:.2em;text-transform:uppercase;color:var(--brand-primary);font-weight:700}
    .cc-hero__code{color:var(--text-muted);font-variant-numeric:tabular-nums}
    .cc-hero__title{margin:.25rem 0 .5rem;font-family:'Poppins',sans-serif;font-weight:800;letter-spacing:-.02em;font-size:1.55rem;line-height:1.1;color:var(--text);overflow-wrap:break-word}
    .cc-hero__meta{display:flex;flex-wrap:wrap;gap:.4rem}
    .cc-hero__meta .badge{white-space:normal;font-weight:700;letter-spacing:.02em}

    /* Ficha de datos (etiqueta / valor). */
    .cc-rows{padding:1.15rem;margin-bottom:1.15rem;display:grid;gap:.9rem}
    .cc-row{display:flex;flex-wrap:wrap;gap:.15rem 1rem}
    .cc-row dt{flex:none;min-width:10rem;color:var(--text-muted);font-size:.8rem;font-weight:700}
    .cc-row dd{margin:0;color:var(--text);font-size:.92rem;line-height:1.5;max-width:56ch;overflow-wrap:break-word}
    .cc-row + .cc-row{padding-top:.9rem;border-top:1px solid var(--stroke)}
    .cc-src__link{display:inline-flex;align-items:center;gap:.5rem;min-height:32px;color:var(--brand-primary);text-decoration:none;font-weight:700;font-size:.9rem;overflow-wrap:anywhere}
    .cc-src__link:hover{text-decoration:underline;color:var(--brand-primary)}
    .cc-src__link .cc-ico{width:15px;height:15px;flex:none}
    .cc-muted{color:var(--text-muted)}

    .cc-acts{display:flex;flex-wrap:wrap;gap:.6rem;margin-top:1.25rem}
    .cc-btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;min-height:44px;padding:.62rem 1.05rem;border-radius:var(--radius-sm);text-decoration:none;font-weight:700;font-size:.9rem;border:1px solid var(--stroke);background:var(--glass);color:var(--text);cursor:pointer;transition:background .18s,border-color .18s,transform .18s var(--ease,cubic-bezier(.16,1,.3,1))}
    .cc-btn:hover{background:var(--glass-2);border-color:var(--stroke-2);color:var(--text)}
    .cc-btn .cc-ico{width:16px;height:16px}
    .cc-btn--primary{background:var(--brand-primary);border-color:var(--brand-primary);color:var(--brand-on-primary);box-shadow:0 10px 26px -12px var(--brand-glow)}
    .cc-btn--primary:hover{background:var(--brand-primary);color:var(--brand-on-primary);filter:brightness(1.04);transform:translateY(-1px)}
    .cc-btn--danger{color:var(--danger);border-color:color-mix(in srgb,var(--danger) 45%,transparent)}
    .cc-btn--danger:hover{color:var(--danger);border-color:var(--danger)}
    @media (max-width:479px){.cc-acts .cc-btn,.cc-acts form{width:100%}.cc-acts form .cc-btn{width:100%}}
    @media (prefers-reduced-motion:reduce){.cc-btn{transition:none}.cc-btn--primary:hover{transform:none}}
</style>
@endpush

@section('content')
{{-- DENTRO de @section: _badge-tokens emite un <style> por ECHO directo (no @push); a nivel
     superior de un @extends saldría ANTES del <!doctype> y dispararía el modo Quirks. --}}
@include('componentes._badge-tokens')
@include('componentes._confirm-submit')
@php
    $badgeEnum  = ['CSATF', 'OSHA', 'STPS', 'DOT', 'SCT', 'GENERAL'];
    $badgeClass = in_array($standard->regulation_badge, $badgeEnum, true) ? $standard->regulation_badge : 'GENERAL';

    // reference_url NO es de confianza para pintarse como enlace: lista blanca http/https.
    $refHref = null;
    if (filled($standard->reference_url)) {
        $refRaw    = trim((string) $standard->reference_url);
        $refScheme = mb_strtolower((string) parse_url($refRaw, PHP_URL_SCHEME));
        if ($refScheme === 'http' || $refScheme === 'https') { $refHref = $refRaw; }
    }

    // Rastro de verificación. Dos NULL con significados distintos (no se colapsan en un ??):
    //   - verified_by_id NULL                       → sello "de origen" (catálogo base).
    //   - verified_by_id CON valor pero relación NULL → hubo persona y su usuario se borró.
    // PHP 7.4: sin nullsafe ni match. Mismo criterio que admin/consumables/show.
    $verifiedNote = null;
    if ($standard->isVerified()) {
        $verifier     = $standard->verifiedBy;
        $verifierName = $verifier !== null ? ($verifier->name ?? '') : '';
        $verifiedNote = $standard->verified_by_id === null
            ? 'Verificada de origen (catálogo base)'
            : 'Verificada por ' . ($verifierName !== '' ? $verifierName : 'usuario dado de baja');
    }
@endphp

<div class="container-fluid py-4 px-3 px-md-4">
<div class="cc-std">

    <a href="{{ route('standards.index') }}" class="cc-back">
        @include('componentes._icon', ['name' => 'chevron-left', 'label' => null])
        Volver al catálogo
    </a>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-3 mt-2" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 rounded-3 mt-2" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif

    @if($standard->isPendingVerification())
        <div class="cc-pending mt-2">
            @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico', 'label' => 'Atención'])
            <div>
                <strong>Pendiente de verificación</strong>
                <span>Una autoridad de compliance todavía no valida esta norma. Contrástala con la fuente oficial antes de citarla.</span>
            </div>
        </div>
    @endif

    <header class="cc-glass-card cc-hero">
        <div class="cc-hero__eyebrow">
            Norma de compliance
            <span class="cc-hero__code">· {{ $standard->regulation_code }}</span>
        </div>

        <h1 class="cc-hero__title">{{ $standard->category_name_localized }}</h1>

        <div class="cc-hero__meta">
            <span class="badge badge-{{ $badgeClass }}">{{ $standard->regulation_badge }}</span>
            @if($standard->isActive())
                <span class="cc-chip cc-chip-ok">Vigente</span>
            @else
                <span class="cc-chip cc-chip-neutral">Retirada</span>
            @endif
            @if($standard->isPendingVerification())
                <span class="cc-chip cc-chip-warn">
                    @include('componentes._icon', ['name' => 'alert-triangle', 'label' => null]) Pendiente
                </span>
            @elseif($standard->isVerified())
                <span class="cc-chip cc-chip-ok">
                    @include('componentes._icon', ['name' => 'check', 'label' => null]) Verificada
                </span>
            @endif
        </div>
    </header>

    <dl class="cc-glass-card cc-rows">
        <div class="cc-row">
            <dt>Marco normativo</dt>
            <dd><span class="badge badge-{{ $badgeClass }}">{{ $standard->regulation_badge }}</span></dd>
        </div>
        <div class="cc-row">
            <dt>Código</dt>
            <dd style="font-variant-numeric:tabular-nums;">{{ $standard->regulation_code }}</dd>
        </div>
        <div class="cc-row">
            <dt>Categoría</dt>
            <dd>{{ $standard->category_name }}</dd>
        </div>
        @if(filled($standard->category_name_en))
            <div class="cc-row">
                <dt>Categoría (EN)</dt>
                <dd>{{ $standard->category_name_en }}</dd>
            </div>
        @endif
        <div class="cc-row">
            <dt>Fuente oficial</dt>
            <dd>
                @if($refHref !== null)
                    <a href="{{ $refHref }}" target="_blank" rel="noopener" class="cc-src__link">
                        @include('componentes._icon', ['name' => 'external-link', 'label' => null])
                        Ver fuente oficial
                    </a>
                @else
                    <span class="cc-muted">Sin enlace de referencia.</span>
                @endif
            </dd>
        </div>
        <div class="cc-row">
            <dt>Verificación</dt>
            <dd>
                @if($standard->isVerified())
                    {{ $verifiedNote }}
                    @if($standard->verified_at) · {{ $standard->verified_at->format('d/m/Y H:i') }} @endif
                @elseif($standard->isPendingVerification())
                    <span style="color:var(--warn);font-weight:700;">Pendiente</span> — ninguna autoridad de compliance la ha validado.
                @else
                    <span class="cc-muted">Sin estado de verificación en esta instancia.</span>
                @endif
            </dd>
        </div>
    </dl>

    <div class="cc-acts">
        @can('standards.manage')
            @if($standard->isPendingVerification())
                {{-- data-confirm, NO onsubmit: ver componentes/_confirm-submit. --}}
                <form method="POST" action="{{ route('standards.verify', $standard->id) }}"
                      data-confirm="¿Confirmas que la norma «{{ $standard->regulation_code }}» es correcta? Quedará registrada tu verificación.">
                    @csrf
                    <button type="submit" class="cc-btn cc-btn--primary">
                        @include('componentes._icon', ['name' => 'check', 'label' => null])
                        Verificar
                    </button>
                </form>
            @endif
            <a href="{{ route('standards.edit', $standard->id) }}" class="cc-btn">
                @include('componentes._icon', ['name' => 'pencil', 'label' => null])
                Editar
            </a>
            @if($standard->isActive())
                {{-- Retirar = is_active=0 (NO borra): la norma sale de captura pero sigue viva
                     para el histórico. PUT + data-confirm. --}}
                <form method="POST" action="{{ route('standards.deactivate', $standard->id) }}"
                      data-confirm="¿Retirar «{{ $standard->regulation_code }}» del catálogo de captura? El histórico que la referencia se sigue resolviendo.">
                    @csrf
                    @method('PUT')
                    <button type="submit" class="cc-btn cc-btn--danger">
                        @include('componentes._icon', ['name' => 'x-circle', 'label' => null])
                        Retirar
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('standards.reactivate', $standard->id) }}">
                    @csrf
                    @method('PUT')
                    <button type="submit" class="cc-btn">
                        @include('componentes._icon', ['name' => 'rotate-ccw', 'label' => null])
                        Reactivar
                    </button>
                </form>
            @endif
        @endcan
        <a href="{{ route('standards.index') }}" class="cc-btn">
            @include('componentes._icon', ['name' => 'chevron-left', 'label' => null])
            Volver al catálogo
        </a>
    </div>

</div>
</div>
@endsection
