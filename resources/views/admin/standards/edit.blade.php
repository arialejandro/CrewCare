@extends('layouts.app')
@section('title', 'Editar norma · ' . $standard->regulation_code)

{{-- NO @feature: el catálogo normativo es NÚCLEO. --}}
@push('styles')
<style>
    /* _form-kit trae .cc-chip base y los modificadores --danger/--ok/--brand, pero NO una
       variante ámbar. Se define aquí el mismo cc-chip-warn (por tokens) que ya usan el
       index y el show de normas, para que el chip de "pendiente" se lea igual en claro
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
                @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div>
                <h1 class="h4 fw-bold mb-0">Editar norma / Compliance</h1>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="cc-muted small">{{ $standard->regulation_code }}</div>
                    @if($standard->isPendingVerification())
                        <span class="cc-chip cc-chip-warn" title="Norma capturada en campo. Aún no la valida una autoridad de compliance.">
                            @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico-14', 'label' => null])
                            Pendiente de verificación
                        </span>
                    @endif
                </div>
                @if($standard->isVerified())
                    @php
                        /* Dos NULL con significados distintos, no se colapsan en un ??:
                             - verified_by_id NULL          → sello "de origen" (catálogo base).
                             - verified_by_id CON valor pero relación NULL → hubo persona y su
                                                              usuario se borró.
                           PHP 7.4: sin nullsafe ni match. */
                        $verifier     = $standard->verifiedBy;
                        $verifierName = $verifier !== null ? ($verifier->name ?? '') : '';
                        $verifiedNote = $standard->verified_by_id === null
                            ? 'Verificada de origen (catálogo base)'
                            : 'Verificada por ' . ($verifierName !== '' ? $verifierName : 'usuario dado de baja');
                    @endphp
                    <div class="cc-muted small d-inline-flex align-items-center gap-1 mt-1">
                        @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-14', 'label' => null])
                        {{ $verifiedNote }}@if($standard->verified_at) · {{ $standard->verified_at->format('d/m/Y H:i') }} @endif
                    </div>
                @endif
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            @if($standard->isPendingVerification())
                {{-- Form de verificación SEPARADO (fuera del form de actualización para no anidar).
                     data-confirm, NO onsubmit. --}}
                <form method="POST" action="{{ route('standards.verify', $standard->id) }}"
                      data-confirm="¿Confirmas que la norma «{{ $standard->regulation_code }}» es correcta? Quedará registrada tu verificación.">
                    @csrf
                    <button type="submit" class="cc-btn-ghost" title="Verificar {{ $standard->regulation_code }}" aria-label="Verificar {{ $standard->regulation_code }}">
                        @include('componentes._icon', ['name' => 'check', 'class' => 'cc-ico-16', 'label' => null])
                        Verificar
                    </button>
                </form>
            @endif
            <a href="{{ route('standards.index') }}" class="cc-btn-ghost">
                @include('componentes._icon', ['name' => 'chevron-left', 'class' => 'cc-ico-16', 'label' => null])
                Volver
            </a>
        </div>
    </div>

    {{-- Form principal de actualización (los campos vienen del partial) --}}
    <form method="POST" action="{{ route('standards.update', $standard->id) }}">
        @csrf
        @method('PUT')
        <div class="cc-form-card">
            <div class="cc-form-card__head">
                <span class="cc-form-ico">
                    @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico-20', 'label' => null])
                </span>
                <div class="cc-form-card__titles">
                    <h2 class="cc-form-card__title">Datos de la norma</h2>
                    <p class="cc-form-card__sub">Actualiza el marco, el código, la categoría o la fuente.</p>
                </div>
            </div>
            <div class="cc-form-card__body">
                @include('admin.standards._form', ['standard' => $standard])
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
            <a href="{{ route('standards.index') }}" class="cc-btn-ghost">Cancelar</a>
            <button type="submit" class="btn btn-primary cc-cta">
                @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-18', 'label' => null])
                Guardar cambios
            </button>
        </div>
    </form>

    {{-- Form de RETIRAR / REACTIVAR SEPARADO (fuera del form de actualización para no anidar).
         Retirar = is_active=0 (NO borra): la norma sale de captura pero sigue viva para el
         histórico. PUT + data-confirm. --}}
    @if($standard->isActive())
        <form method="POST" action="{{ route('standards.deactivate', $standard->id) }}"
              data-confirm="¿Retirar «{{ $standard->regulation_code }}» del catálogo de captura? El histórico que la referencia se sigue resolviendo."
              class="mt-3 text-end">
            @csrf
            @method('PUT')
            <button type="submit" class="cc-btn-ghost" style="border-color: var(--danger); color: var(--danger);">
                @include('componentes._icon', ['name' => 'x-circle', 'class' => 'cc-ico-16', 'label' => null])
                Retirar de captura
            </button>
        </form>
    @else
        <form method="POST" action="{{ route('standards.reactivate', $standard->id) }}" class="mt-3 text-end">
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
