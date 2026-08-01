@extends('layouts.app')
@section('title', 'Evento · ' . $event->code)

{{-- NO @feature: el catálogo de eventos posibles es NÚCLEO. --}}

@push('styles')
<style>
    /* Detalle de evento — lenguaje "Cinematic Dark Glass" (tokens de _brand-theme).
       Hermano de admin/standards/show, ampliado con la lista de normas ligadas (N:M). */
    .cc-ev{max-width:860px;margin:0 auto}

    .cc-chip{display:inline-flex;align-items:center;gap:.35rem;font-size:.72rem;font-weight:700;letter-spacing:.02em;padding:.28rem .58rem;border-radius:999px;border:1px solid transparent;line-height:1;white-space:nowrap}
    .cc-chip-ok{color:var(--ok);background:color-mix(in srgb,var(--ok) 15%,transparent);border-color:color-mix(in srgb,var(--ok) 32%,transparent)}
    .cc-chip-warn{color:var(--warn);background:color-mix(in srgb,var(--warn) 16%,transparent);border-color:color-mix(in srgb,var(--warn) 32%,transparent)}
    .cc-chip-neutral{color:var(--text-muted);background:var(--glass-2);border-color:var(--stroke)}
    .cc-chip-pc{color:var(--text);background:var(--glass-2);border-color:var(--stroke);font-variant-numeric:tabular-nums}

    .cc-back{display:inline-flex;align-items:center;gap:.4rem;min-height:44px;padding:.35rem .15rem;color:var(--text-muted);text-decoration:none;font-size:.9rem;font-weight:600}
    .cc-back:hover{color:var(--text)}
    .cc-back .cc-ico{width:16px;height:16px}

    /* Aviso de evento pendiente (ámbar, arriba, visible). */
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
    .cc-row dd{margin:0;color:var(--text);font-size:.92rem;line-height:1.5;max-width:60ch;overflow-wrap:break-word}
    .cc-row + .cc-row{padding-top:.9rem;border-top:1px solid var(--stroke)}
    .cc-muted{color:var(--text-muted)}

    /* Normas ligadas: lista de chips (marco + código) con enlace a la fuente. */
    .cc-norms{display:flex;flex-direction:column;gap:.5rem}
    .cc-norm{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem}
    .cc-norm .badge{white-space:normal;font-weight:700;letter-spacing:.02em}
    .cc-norm__code{font-variant-numeric:tabular-nums;font-weight:600;color:var(--text)}
    .cc-norm__cat{color:var(--text-muted);font-size:.86rem}
    .cc-norm__link{display:inline-flex;align-items:center;gap:.3rem;color:var(--brand-primary);text-decoration:none;font-size:.84rem;font-weight:600}
    .cc-norm__link:hover{text-decoration:underline}
    .cc-norm__link .cc-ico{width:13px;height:13px}

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
    // Enum CERRADO del marco (los 7 colores de _badge-tokens; AMAZON legacy incluido para
    // que una norma que lo traiga no caiga a GENERAL).
    $badgeEnum = ['CSATF', 'OSHA', 'STPS', 'DOT', 'SCT', 'GENERAL', 'AMAZON'];

    $pending = $event->isPendingVerification();
    $pcLike  = $event->default_likelihood ? $event->default_likelihood : null;
    $pcCons  = $event->default_consequence ? $event->default_consequence : null;

    // Rastro de verificación. Dos NULL con significados distintos (no se colapsan en un ??):
    //   - verified_by_id NULL                         → sello "de origen" (catálogo base).
    //   - verified_by_id CON valor pero relación NULL → hubo persona y su usuario se borró.
    // PHP 7.4: sin nullsafe ni match. Mismo criterio que admin/standards/show.
    $verifiedNote = null;
    if ($event->isVerified()) {
        $verifier     = $event->verifiedBy;
        $verifierName = $verifier !== null ? ($verifier->name ?? '') : '';
        $verifiedNote = $event->verified_by_id === null
            ? 'Verificado de origen (catálogo base)'
            : 'Verificado por ' . ($verifierName !== '' ? $verifierName : 'usuario dado de baja');
    }
@endphp

<div class="container-fluid py-4 px-3 px-md-4">
<div class="cc-ev">

    <a href="{{ route('hazardevents.index') }}" class="cc-back">
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

    @if($pending)
        <div class="cc-pending mt-2">
            @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico', 'label' => 'Atención'])
            <div>
                <strong>Pendiente de verificación</strong>
                <span>Una autoridad de compliance todavía no valida este evento. Contrástalo con la fuente oficial antes de citarlo.</span>
            </div>
        </div>
    @endif

    <header class="cc-glass-card cc-hero">
        <div class="cc-hero__eyebrow">
            Evento posible
            <span class="cc-hero__code">· {{ $event->code }}</span>
        </div>

        <h1 class="cc-hero__title">{{ $event->name_localized }}</h1>

        <div class="cc-hero__meta">
            <span class="cc-chip cc-chip-neutral">{{ $event->context_label }}</span>
            @if($event->category_label)
                <span class="cc-chip cc-chip-neutral">{{ $event->category_label }}</span>
            @endif
            @if($pcLike !== null || $pcCons !== null)
                <span class="cc-chip cc-chip-pc">Prob·Cons {{ $pcLike ?? '—' }}·{{ $pcCons ?? '—' }}</span>
            @endif
            @if($event->isActive())
                <span class="cc-chip cc-chip-ok">Vigente</span>
            @else
                <span class="cc-chip cc-chip-neutral">Retirado</span>
            @endif
            @if($pending)
                <span class="cc-chip cc-chip-warn">
                    @include('componentes._icon', ['name' => 'alert-triangle', 'label' => null]) Pendiente
                </span>
            @elseif($event->isVerified())
                <span class="cc-chip cc-chip-ok">
                    @include('componentes._icon', ['name' => 'check', 'label' => null]) Verificado
                </span>
            @endif
        </div>
    </header>

    <dl class="cc-glass-card cc-rows">
        <div class="cc-row">
            <dt>Nombre (ES)</dt>
            <dd>{{ $event->name_es }}</dd>
        </div>
        @if(filled($event->name_en))
            <div class="cc-row">
                <dt>Nombre (EN)</dt>
                <dd>{{ $event->name_en }}</dd>
            </div>
        @endif
        <div class="cc-row">
            <dt>Contexto</dt>
            <dd>{{ $event->context_label }}</dd>
        </div>
        <div class="cc-row">
            <dt>Categoría</dt>
            <dd>{{ $event->category_label ?? '—' }}</dd>
        </div>
        <div class="cc-row">
            <dt>Probabilidad · Consecuencia</dt>
            <dd>
                @if($pcLike !== null || $pcCons !== null)
                    <span class="cc-chip cc-chip-pc">{{ $pcLike ?? '—' }}·{{ $pcCons ?? '—' }}</span>
                    <span class="cc-muted"> (valores por defecto)</span>
                @else
                    <span class="cc-muted">Sin valores por defecto.</span>
                @endif
            </dd>
        </div>
        @if(filled($event->description_es))
            <div class="cc-row">
                <dt>Descripción (ES)</dt>
                <dd>{{ $event->description_es }}</dd>
            </div>
        @endif
        @if(filled($event->description_en))
            <div class="cc-row">
                <dt>Descripción (EN)</dt>
                <dd>{{ $event->description_en }}</dd>
            </div>
        @endif

        <div class="cc-row">
            <dt>Normas ligadas</dt>
            <dd>
                @if($event->standards->count())
                    <div class="cc-norms">
                        @foreach($event->standards as $s)
                            @php
                                $badgeClass = in_array($s->regulation_badge, $badgeEnum, true) ? $s->regulation_badge : 'GENERAL';
                                // reference_url NO es de confianza para pintarse como enlace:
                                // lista blanca de esquema (http/https) vía parse_url.
                                $refHref = null;
                                if (filled($s->reference_url)) {
                                    $refRaw    = trim((string) $s->reference_url);
                                    $refScheme = mb_strtolower((string) parse_url($refRaw, PHP_URL_SCHEME));
                                    if ($refScheme === 'http' || $refScheme === 'https') { $refHref = $refRaw; }
                                }
                            @endphp
                            <div class="cc-norm">
                                <span class="badge badge-{{ $badgeClass }}">{{ $s->regulation_badge }}</span>
                                <span class="cc-norm__code">{{ $s->regulation_code }}</span>
                                <span class="cc-norm__cat">— {{ $s->category_name_localized }}</span>
                                @if($refHref !== null)
                                    <a href="{{ $refHref }}" target="_blank" rel="noopener" class="cc-norm__link">
                                        @include('componentes._icon', ['name' => 'external-link', 'label' => null]) Fuente
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <span class="cc-muted">Sin normas ligadas.</span>
                @endif
            </dd>
        </div>

        <div class="cc-row">
            <dt>Verificación</dt>
            <dd>
                @if($event->isVerified())
                    {{ $verifiedNote }}
                    @if($event->verified_at) · {{ $event->verified_at->format('d/m/Y H:i') }} @endif
                @elseif($pending)
                    <span style="color:var(--warn);font-weight:700;">Pendiente</span> — ninguna autoridad de compliance lo ha validado.
                @else
                    <span class="cc-muted">Sin estado de verificación en esta instancia.</span>
                @endif
            </dd>
        </div>
    </dl>

    <div class="cc-acts">
        @can('hazardevents.manage')
            @if($pending)
                {{-- data-confirm, NO onsubmit: ver componentes/_confirm-submit. --}}
                <form method="POST" action="{{ route('hazardevents.verify', $event->id) }}"
                      data-confirm="¿Confirmas que el evento «{{ $event->name_es }}» es correcto? Quedará registrada tu verificación.">
                    @csrf
                    <button type="submit" class="cc-btn cc-btn--primary">
                        @include('componentes._icon', ['name' => 'check', 'label' => null])
                        Verificar
                    </button>
                </form>
            @endif
            <a href="{{ route('hazardevents.edit', $event->id) }}" class="cc-btn">
                @include('componentes._icon', ['name' => 'pencil', 'label' => null])
                Editar
            </a>
            @if($event->isActive())
                {{-- Retirar = is_active=0 (NO borra): el evento sale de captura pero sigue vivo
                     para el histórico. PUT + data-confirm. --}}
                <form method="POST" action="{{ route('hazardevents.deactivate', $event->id) }}"
                      data-confirm="¿Retirar «{{ $event->name_es }}» del catálogo de captura? El histórico que lo referencia se sigue resolviendo.">
                    @csrf
                    @method('PUT')
                    <button type="submit" class="cc-btn cc-btn--danger">
                        @include('componentes._icon', ['name' => 'x-circle', 'label' => null])
                        Retirar
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('hazardevents.reactivate', $event->id) }}">
                    @csrf
                    @method('PUT')
                    <button type="submit" class="cc-btn">
                        @include('componentes._icon', ['name' => 'rotate-ccw', 'label' => null])
                        Reactivar
                    </button>
                </form>
            @endif
        @endcan
        <a href="{{ route('hazardevents.index') }}" class="cc-btn">
            @include('componentes._icon', ['name' => 'chevron-left', 'label' => null])
            Volver al catálogo
        </a>
    </div>

</div>
</div>
@endsection
