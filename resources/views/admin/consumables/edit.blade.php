@extends('layouts.app')
@section('title', 'Editar consumible / SDS')

@feature('sds_sfx')
@push('styles')
<style>
    /* _form-kit trae .cc-chip base y los modificadores --danger/--ok/--brand, pero NO
       una variante ámbar. Se define aquí el mismo cc-chip-warn (por tokens) que ya usan
       admin/consumables/index y admin/sfx/index, para que el chip de "pendiente de
       verificación" se lea igual en claro y en oscuro. */
    .cc-chip-warn{color:var(--warn);background:color-mix(in srgb,var(--warn) 16%,transparent);border:1px solid color-mix(in srgb,var(--warn) 32%,transparent)}
</style>
@endpush

@section('content')

{{-- Sistema de estilos de formularios reutilizable (tarjetas, campos, controles, CTA, iconos). --}}
@include('componentes._form-kit')

{{-- Confirmación de los submits destructivos (verificar / eliminar) sin JS inline. --}}
@include('componentes._confirm-submit')

<div class="container py-4" style="max-width: 960px;">

    {{-- ===== Encabezado ===== --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'flask-conical', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div>
                <h1 class="h4 fw-bold mb-0">Editar consumible / SDS</h1>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="cc-muted small">{{ $consumable->name }}</div>
                    @if($consumable->isPendingVerification())
                        <span class="cc-chip cc-chip-warn" title="Ficha capturada en set. Sus datos aún no han sido validados por un responsable de SDS.">
                            @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico-14', 'label' => null])
                            Capturado en set · pendiente de verificación
                        </span>
                    @endif
                </div>
                @if($consumable->isVerified())
                    @php
                        /* Dos NULL con significados distintos, no se pueden colapsar en un ??:
                             - verified_by_id NULL          → sello "de origen": la avala el catálogo
                                                              base sembrado, nunca hubo autor humano.
                             - verified_by_id CON valor pero relación verifiedBy NULL
                                                            → sí hubo persona y su usuario se borró.
                           PHP 7.4: sin nullsafe ni match. */
                        $__verifier     = $consumable->verifiedBy;
                        $__verifierName = $__verifier !== null ? ($__verifier->name ?? '') : '';
                        $__verifiedNote = $consumable->verified_by_id === null
                            ? 'Verificada de origen (catálogo base)'
                            : 'Verificada por ' . ($__verifierName !== '' ? $__verifierName : 'usuario dado de baja');
                    @endphp
                    <div class="cc-muted small d-inline-flex align-items-center gap-1 mt-1">
                        @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-14', 'label' => null])
                        {{ $__verifiedNote }} · {{ $consumable->verified_at->format('d/m/Y H:i') }}
                    </div>
                @endif
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            @can('sds.manage')
                @if($consumable->isPendingVerification())
                    {{-- Form de verificación SEPARADO (fuera del form de actualización para no anidar).
                         data-confirm, NO onsubmit: el `name` es texto libre y en un atributo JS
                         rompe el literal de cadena (apóstrofo → SyntaxError, el diálogo desaparece;
                         comilla cerrada → inyección en la sesión de quien verifica). Ver
                         componentes/_confirm-submit. --}}
                    <form method="POST" action="{{ route('consumables.verify', $consumable->id) }}"
                          data-confirm="¿Confirmas que los datos de «{{ $consumable->name }}» son correctos? Quedará registrada tu verificación.">
                        @csrf
                        <button type="submit" class="cc-btn-ghost" title="Verificar {{ $consumable->name }}" aria-label="Verificar {{ $consumable->name }}">
                            @include('componentes._icon', ['name' => 'check', 'class' => 'cc-ico-16', 'label' => null])
                            Verificar
                        </button>
                    </form>
                @endif
            @endcan
            <a href="{{ route('consumables.index') }}" class="cc-btn-ghost">
                @include('componentes._icon', ['name' => 'chevron-left', 'class' => 'cc-ico-16', 'label' => null])
                Volver
            </a>
        </div>
    </div>

    {{-- Form principal de actualización (los campos vienen del partial) --}}
    <form method="POST" action="{{ route('consumables.update', $consumable->id) }}">
        @csrf
        @method('PUT')
        <div class="cc-form-card">
            <div class="cc-form-card__head">
                <span class="cc-form-ico">
                    @include('componentes._icon', ['name' => 'flask-conical', 'class' => 'cc-ico-20', 'label' => null])
                </span>
                <div class="cc-form-card__titles">
                    <h2 class="cc-form-card__title">Datos del consumible</h2>
                    <p class="cc-form-card__sub">Actualiza la ficha SDS/MSDS de este material.</p>
                </div>
            </div>
            <div class="cc-form-card__body">
                @include('admin.consumables._form', ['consumable' => $consumable])
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
            <a href="{{ route('consumables.index') }}" class="cc-btn-ghost">Cancelar</a>
            <button type="submit" class="btn btn-primary cc-cta">
                @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-18', 'label' => null])
                Guardar cambios
            </button>
        </div>
    </form>

    {{-- Form de borrado SEPARADO (fuera del form de actualización para no anidar).
         Este mensaje NUNCA interpoló datos, así que no estaba roto — pero se homologa a
         data-confirm igual: dejar los dos patrones conviviendo en el mismo archivo es la
         invitación perfecta a que el siguiente que añada un nombre lo haga en el de al lado. --}}
    <form method="POST" action="{{ route('consumables.destroy', $consumable->id) }}"
          data-confirm="¿Eliminar este consumible? Esta acción no se puede deshacer."
          class="mt-3 text-end">
        @csrf
        @method('DELETE')
        <button type="submit" class="cc-btn-ghost" style="border-color: var(--danger); color: var(--danger);">
            @include('componentes._icon', ['name' => 'trash-2', 'class' => 'cc-ico-16', 'label' => null])
            Eliminar consumible
        </button>
    </form>

</div>
@endsection
@endfeature
