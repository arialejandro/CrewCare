@extends('layouts.app')
@section('title', 'Editar evento · ' . $event->code)

{{-- NO @feature: el catálogo de eventos posibles es NÚCLEO. --}}
@push('styles')
<style>
    /* _form-kit trae .cc-chip base y los modificadores --danger/--ok/--brand, pero NO una
       variante ámbar. Se define aquí el mismo cc-chip-warn (por tokens) que ya usan el
       index y el show de eventos, para que el chip de "pendiente" se lea igual en claro
       y en oscuro. */
    .cc-chip-warn{color:var(--warn);background:color-mix(in srgb,var(--warn) 16%,transparent);border:1px solid color-mix(in srgb,var(--warn) 32%,transparent)}
</style>
@endpush

@section('content')

{{-- Sistema de estilos de formularios reutilizable. --}}
@include('componentes._form-kit')
{{-- Confirmación de los submits destructivos (verificar / retirar) SIN JS inline. --}}
@include('componentes._confirm-submit')

<div class="container py-4" style="max-width: 960px;">

    {{-- ===== Encabezado ===== --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'shield-alert', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div>
                <h1 class="h4 fw-bold mb-0">Editar evento de peligro</h1>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="cc-muted small">{{ $event->code }}</div>
                    @if($event->isPendingVerification())
                        <span class="cc-chip cc-chip-warn" title="Evento capturado en campo. Aún no lo valida una autoridad de compliance.">
                            @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico-14', 'label' => null])
                            Pendiente de verificación
                        </span>
                    @endif
                </div>
                @if($event->isVerified())
                    @php
                        /* Dos NULL con significados distintos, no se colapsan en un ??:
                             - verified_by_id NULL          → sello "de origen" (catálogo base).
                             - verified_by_id CON valor pero relación NULL → hubo persona y su
                                                              usuario se borró.
                           PHP 7.4: sin nullsafe ni match. */
                        $verifier     = $event->verifiedBy;
                        $verifierName = $verifier !== null ? ($verifier->name ?? '') : '';
                        $verifiedNote = $event->verified_by_id === null
                            ? 'Verificado de origen (catálogo base)'
                            : 'Verificado por ' . ($verifierName !== '' ? $verifierName : 'usuario dado de baja');
                    @endphp
                    <div class="cc-muted small d-inline-flex align-items-center gap-1 mt-1">
                        @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-14', 'label' => null])
                        {{ $verifiedNote }}@if($event->verified_at) · {{ $event->verified_at->format('d/m/Y H:i') }} @endif
                    </div>
                @endif
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            @if($event->isPendingVerification())
                {{-- Form de verificación SEPARADO (fuera del form de actualización para no anidar).
                     data-confirm, NO onsubmit. --}}
                <form method="POST" action="{{ route('hazardevents.verify', $event->id) }}"
                      data-confirm="¿Confirmas que el evento «{{ $event->name_es }}» es correcto? Quedará registrada tu verificación.">
                    @csrf
                    <button type="submit" class="cc-btn-ghost" title="Verificar {{ $event->code }}" aria-label="Verificar {{ $event->code }}">
                        @include('componentes._icon', ['name' => 'check', 'class' => 'cc-ico-16', 'label' => null])
                        Verificar
                    </button>
                </form>
            @endif
            <a href="{{ route('hazardevents.index') }}" class="cc-btn-ghost">
                @include('componentes._icon', ['name' => 'chevron-left', 'class' => 'cc-ico-16', 'label' => null])
                Volver
            </a>
        </div>
    </div>

    {{-- Form principal de actualización (los campos y el norm-picker vienen del partial) --}}
    <form method="POST" action="{{ route('hazardevents.update', $event->id) }}">
        @csrf
        @method('PUT')
        <div class="cc-form-card">
            <div class="cc-form-card__head">
                <span class="cc-form-ico">
                    @include('componentes._icon', ['name' => 'shield-alert', 'class' => 'cc-ico-20', 'label' => null])
                </span>
                <div class="cc-form-card__titles">
                    <h2 class="cc-form-card__title">Datos del evento</h2>
                    <p class="cc-form-card__sub">Actualiza el nombre, la clasificación, la matriz por defecto o las normas ligadas.</p>
                </div>
            </div>
            <div class="cc-form-card__body">
                @include('admin.hazard-events._form', [
                    'event'        => $event,
                    'allStandards' => $allStandards,
                    'linkedIds'    => $linkedIds,
                    'contexts'     => $contexts,
                    'categories'   => $categories,
                ])
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
            <a href="{{ route('hazardevents.index') }}" class="cc-btn-ghost">Cancelar</a>
            <button type="submit" class="btn btn-primary cc-cta">
                @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-18', 'label' => null])
                Guardar cambios
            </button>
        </div>
    </form>

    {{-- Form de RETIRAR / REACTIVAR SEPARADO (fuera del form de actualización para no anidar).
         Retirar = is_active=0 (NO borra): el evento sale de captura pero sigue vivo para el
         histórico. PUT + data-confirm. --}}
    @if($event->isActive())
        <form method="POST" action="{{ route('hazardevents.deactivate', $event->id) }}"
              data-confirm="¿Retirar «{{ $event->name_es }}» del catálogo de captura? El histórico que lo referencia se sigue resolviendo."
              class="mt-3 text-end">
            @csrf
            @method('PUT')
            <button type="submit" class="cc-btn-ghost" style="border-color: var(--danger); color: var(--danger);">
                @include('componentes._icon', ['name' => 'x-circle', 'class' => 'cc-ico-16', 'label' => null])
                Retirar de captura
            </button>
        </form>
    @else
        <form method="POST" action="{{ route('hazardevents.reactivate', $event->id) }}" class="mt-3 text-end">
            @csrf
            @method('PUT')
            <button type="submit" class="cc-btn-ghost">
                @include('componentes._icon', ['name' => 'rotate-ccw', 'class' => 'cc-ico-16', 'label' => null])
                Reactivar
            </button>
        </form>
    @endif

</div>
@endsection
