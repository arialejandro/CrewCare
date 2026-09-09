@extends('layouts.app')
@section('content')

{{-- Sistema de estilos de formularios reutilizable (tarjetas, campos, controles, CTA). --}}
@include('componentes._form-kit')

{{--
    provider-show.blade.php — FICHA de un proveedor de ambulancias.
    Recibe: $provider (App\Models\AmbulanceProvider).

    Secciones:
      (a) Datos de la empresa.
      (b) Documentos de EMPRESA (nivel empresa): estado de validación MANUAL por
          documento (chip verde si validado / banner ámbar + form de validación si
          pendiente). El «en trámite» sin folio ni fecha compromiso se marca «no lo tiene».
      (c) Alta de documento de empresa.
      (d) Padrón de tripulantes: cada persona con su foto de credencial y sus documentos
          (TAMP, cédula del médico, CONOCER), alta de tripulante EN EL MOMENTO y alta de
          documento por persona.

    Se apoya en dos parciales NUEVOS reutilizados para empresa y persona:
      ambulance/partials/_doc-row  (una fila de documento + su form de validación)
      ambulance/partials/_doc-form (form de captura de documento)
--}}
@php
    $companyDocs = $provider->authorizations;   // Collection (nivel empresa)
    $crewMembers = $provider->crew;             // Collection AmbulanceCrew
@endphp

<div class="crew-page container-fluid px-3 px-md-4 py-4" style="max-width: 1000px;">

    {{-- Volver --}}
    <a href="{{ route('ambulance.providers') }}" class="cc-btn-ghost mb-3">
        @include('componentes._icon', ['name' => 'arrow-left', 'class' => 'cc-ico-16', 'label' => null])
        {{ __('Todos los proveedores') }}
    </a>

    {{-- Encabezado --}}
    <div class="crew-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'ambulance', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ $provider->name }}</h1>
                <p class="text-muted mb-0 small">{{ __('Documentos de la empresa y padrón de tripulantes.') }}</p>
            </div>
        </div>
    </div>

    {{-- Feedback de validación + flash (cubre TODOS los formularios de la ficha). --}}
    @include('componentes._form-feedback')

    {{-- (a) DATOS DE LA EMPRESA ------------------------------------------------ --}}
    <div class="cc-form-card">
        <div class="cc-form-card__head">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'building-2', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div class="cc-form-card__titles">
                <h2 class="cc-form-card__title">{{ __('Datos de la empresa') }}</h2>
                <p class="cc-form-card__sub">{{ __('La empresa se califica una vez; sus datos identifican al prestador.') }}</p>
            </div>
        </div>
        <div class="cc-form-card__body">
            <div class="cc-info">
                <div class="cc-info-item">
                    <span class="cc-info-lbl">{{ __('Nombre / razón social') }}</span>
                    <span class="cc-info-val">{{ $provider->name }}</span>
                </div>
                <div class="cc-info-item">
                    <span class="cc-info-lbl">{{ __('RFC') }}</span>
                    <span class="cc-info-val">{{ $provider->rfc ?: '—' }}</span>
                </div>
                <div class="cc-info-item">
                    <span class="cc-info-lbl">{{ __('Teléfono de contacto') }}</span>
                    <span class="cc-info-val">{{ $provider->contact_phone ?: '—' }}</span>
                </div>
                <div class="cc-info-item">
                    <span class="cc-info-lbl">{{ __('Responsable sanitario') }}</span>
                    <span class="cc-info-val">{{ $provider->sanitary_manager ?: '—' }}</span>
                </div>
                @if($provider->notes)
                    <div class="cc-info-item cc-info-item--wide">
                        <span class="cc-info-lbl">{{ __('Notas') }}</span>
                        <span class="cc-info-val">{{ $provider->notes }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- (b) DOCUMENTOS DE EMPRESA ---------------------------------------------- --}}
    <div class="cc-form-card">
        <div class="cc-form-card__head">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'file-check', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div class="cc-form-card__titles">
                <h2 class="cc-form-card__title">{{ __('Documentos de la empresa') }}</h2>
                <p class="cc-form-card__sub">{{ __('Cada documento se captura y luego OTRA persona lo valida bajo su responsabilidad.') }}</p>
            </div>
        </div>
        <div class="cc-form-card__body">
            @if($companyDocs->isEmpty())
                <p class="amb-docs-empty mb-0">{{ __('Sin documentos de empresa capturados todavía.') }}</p>
            @else
                @foreach($companyDocs as $doc)
                    @include('ambulance.partials._doc-row', ['doc' => $doc])
                @endforeach
            @endif
        </div>
    </div>

    {{-- (c) ALTA DE DOCUMENTO DE EMPRESA (solo quien gestiona) ------------------- --}}
    @can('ambulance.manage')
    @include('ambulance.partials._doc-form', [
        'holderType' => 'empresa',
        'holderId'   => $provider->id,
        'idPrefix'   => 'emp',
        'title'      => __('Agregar documento de empresa'),
        'sub'        => __('Aviso de funcionamiento, dictamen, póliza, permisos… Se valida después.'),
        'icon'       => 'file-plus',
    ])
    @endcan

    {{-- (d) PADRÓN DE TRIPULANTES ---------------------------------------------- --}}
    <div class="cc-form-card">
        <div class="cc-form-card__head">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'id-card', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div class="cc-form-card__titles">
                <h2 class="cc-form-card__title">{{ __('Padrón de tripulantes') }}</h2>
                <p class="cc-form-card__sub">{{ __('El roster no es estable: si quien se presenta no está, se da de alta en el momento.') }}</p>
            </div>
        </div>
        <div class="cc-form-card__body">

            @if($crewMembers->isEmpty())
                <p class="amb-docs-empty">{{ __('Sin tripulantes en el padrón. Da de alta al primero abajo.') }}</p>
            @else
                @foreach($crewMembers as $crew)
                    <div class="amb-crew">
                        <div class="amb-crew__head">
                            <span class="amb-crew__photo">
                                @if($crew->idPhotoUrl())
                                    <img src="{{ $crew->idPhotoUrl() }}" alt="{{ __('Credencial de') }} {{ $crew->full_name }}">
                                @else
                                    @include('componentes._icon', ['name' => 'user', 'class' => 'cc-ico-24', 'label' => null])
                                @endif
                            </span>
                            <div class="amb-crew__info">
                                <p class="amb-crew__name">{{ $crew->full_name }}</p>
                                @if($crew->crew_role)
                                    <span class="amb-crew__role">{{ $crew->crew_role }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="amb-crew__docs">
                            <p class="amb-subhead">{{ __('Documentos de esta persona') }}</p>
                            @php $personDocs = $crew->authorizations; @endphp
                            @if($personDocs->isEmpty())
                                <p class="amb-docs-empty">{{ __('Sin documentos capturados para esta persona.') }}</p>
                            @else
                                @foreach($personDocs as $doc)
                                    @include('ambulance.partials._doc-row', ['doc' => $doc])
                                @endforeach
                            @endif

                            @can('ambulance.manage')
                            @include('ambulance.partials._doc-form', [
                                'holderType' => 'persona',
                                'holderId'   => $crew->id,
                                'idPrefix'   => 'crew' . $crew->id,
                                'title'      => __('Agregar documento de esta persona'),
                                'sub'        => __('TAMP, cédula del médico o certificado CONOCER. Se valida después.'),
                                'icon'       => 'file-plus',
                                'conocer'    => true,
                            ])
                            @endcan
                        </div>
                    </div>
                @endforeach
            @endif

            {{-- Alta de tripulante EN EL MOMENTO (foto de credencial). Solo quien gestiona. --}}
            @can('ambulance.manage')
            <div class="amb-crew amb-crew--add">
                <p class="amb-subhead">{{ __('Dar de alta un tripulante') }}</p>
                <form action="{{ route('ambulance.crew.store', ['provider' => $provider->id]) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="cc-field mb-0">
                                <label for="full_name" class="cc-label">
                                    {{ __('Nombre completo') }} <span class="cc-req">*</span>
                                </label>
                                <input id="full_name" type="text" name="full_name" maxlength="255" required
                                       class="form-control cc-control" value="{{ old('full_name') }}">
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="cc-field mb-0">
                                <label for="crew_role" class="cc-label">{{ __('Rol') }}</label>
                                <select id="crew_role" name="crew_role" class="form-select cc-select">
                                    <option value="TAMP"     {{ old('crew_role') === 'TAMP' ? 'selected' : '' }}>{{ __('TAMP') }}</option>
                                    <option value="médico"   {{ old('crew_role') === 'médico' ? 'selected' : '' }}>{{ __('Médico') }}</option>
                                    <option value="operador" {{ old('crew_role') === 'operador' ? 'selected' : '' }}>{{ __('Operador') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="cc-field mb-0">
                                <label for="id_photo_path" class="cc-label">
                                    @include('componentes._icon', ['name' => 'camera', 'class' => 'cc-ico-16', 'label' => null])
                                    {{ __('Foto de credencial') }}
                                </label>
                                <input id="id_photo_path" type="file" name="id_photo_path"
                                       accept="image/*,.heic,.heif" capture="environment" data-cc-photo
                                       class="form-control cc-control">
                                <span class="cc-help">{{ __('Alta en el momento: la foto de la credencial permite cotejar a quien se presenta.') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="d-grid d-md-flex justify-content-md-end mt-3">
                        <button type="submit" class="btn btn-primary cc-cta w-100 w-md-auto">
                            @include('componentes._icon', ['name' => 'plus', 'class' => 'cc-ico-18', 'label' => null])
                            {{ __('Agregar tripulante') }}
                        </button>
                    </div>
                </form>
            </div>
            @endcan

        </div>
    </div>

</div>

@push('styles')
@include('componentes._crew-list-styles')
<style>
    @media (min-width: 768px) { .w-md-auto { width: auto !important; } }

    .amb-docs-empty { font-size:.85rem; color:var(--text-muted); }

    /* Banner ámbar de "pendiente de validar" (calca cc-pending de la cédula;
       aquí no está en una hoja compartida, así que se define en la vista). */
    .amb-pending { display:flex; align-items:flex-start; gap:.7rem; margin-top:.6rem;
        padding:.75rem .9rem; border-radius:var(--radius-sm, 11px); color:var(--warn);
        background:color-mix(in srgb, var(--warn) 14%, transparent);
        border:1px solid color-mix(in srgb, var(--warn) 34%, transparent); }
    .amb-pending svg { width:20px; height:20px; flex:none; margin-top:.1rem; }
    .amb-pending strong { display:block; }
    .amb-pending span { font-size:.82rem; line-height:1.4; }

    /* Chip neutro para "condicionante" (cc-chip base ya da forma; esto solo tinta). */
    .amb-chip-muted { color:var(--text-muted); background:var(--surface-2);
        border:1px solid var(--stroke-2, var(--border)); }

    /* ── Fila de documento externo ── */
    .amb-doc { display:flex; flex-wrap:wrap; gap:.75rem 1rem; padding:1rem;
        border:1px solid var(--stroke, var(--border)); border-radius:var(--radius-sm, 11px);
        background:var(--surface-2); margin-bottom:.75rem; }
    .amb-doc:last-child { margin-bottom:0; }
    .amb-doc__body { flex:1 1 260px; min-width:0; }
    .amb-doc__head { display:flex; flex-wrap:wrap; align-items:center; gap:.5rem; justify-content:space-between; }
    .amb-doc__title { font-size:.95rem; font-weight:700; margin:0; display:flex; align-items:center; gap:.4rem; color:var(--text); }
    .amb-doc__flags { display:flex; flex-wrap:wrap; gap:.35rem; }
    .amb-doc__meta { display:flex; flex-wrap:wrap; gap:.15rem 1rem; font-size:.82rem; color:var(--text-muted); margin-top:.45rem; }
    .amb-doc__meta strong { color:var(--text); font-weight:600; }
    .amb-doc__state { display:flex; align-items:center; gap:.4rem; margin-top:.55rem; font-size:.85rem; color:var(--text-muted); }
    .amb-doc__state--bad { color:var(--danger); }
    .amb-doc__state--warn { color:var(--warn); }
    .amb-doc__std { display:flex; align-items:center; gap:.4rem; margin-top:.55rem; font-size:.82rem; color:var(--text); }
    .amb-doc__validated { margin-top:.6rem; }
    .amb-doc__photo { flex:0 0 auto; display:block; width:120px; height:88px; border-radius:10px;
        overflow:hidden; border:1px solid var(--stroke, var(--border)); background:var(--surface); }
    .amb-doc__photo img { width:100%; height:100%; object-fit:cover; display:block; }
    .amb-doc__validate { flex:1 1 100%; border-top:1px dashed var(--stroke-2, var(--border));
        padding-top:.75rem; margin-top:.25rem; }

    /* ── Bloque CONOCER dentro del form de captura ── */
    .amb-conocer { border:1px dashed var(--stroke-2, var(--border)); border-radius:var(--radius-sm, 11px);
        padding:.85rem; background:var(--surface-2); }
    .amb-conocer--hi { border-color:rgba(var(--brand-primary-rgb), .35);
        background:rgba(var(--brand-primary-rgb), .05); }

    /* ── Tripulante del padrón ── */
    .amb-crew { border:1px solid var(--stroke, var(--border)); border-radius:var(--radius, 16px);
        background:var(--surface); padding:1rem; margin-bottom:1rem; }
    .amb-crew--add { border-style:dashed; }
    .amb-crew__head { display:flex; gap:.85rem; align-items:flex-start; }
    .amb-crew__photo { flex:none; width:64px; height:64px; border-radius:12px; overflow:hidden;
        border:1px solid var(--stroke, var(--border)); background:var(--surface-2);
        display:flex; align-items:center; justify-content:center; color:var(--text-muted); }
    .amb-crew__photo img { width:100%; height:100%; object-fit:cover; display:block; }
    .amb-crew__info { min-width:0; flex:1 1 auto; }
    .amb-crew__name { font-weight:700; color:var(--text); margin:0; }
    .amb-crew__role { font-size:.8rem; color:var(--text-muted); }
    .amb-crew__docs { margin-top:.85rem; }
    .amb-subhead { font-size:.75rem; text-transform:uppercase; letter-spacing:.04em;
        color:var(--text-muted); font-weight:700; margin:0 0 .6rem; }
    .amb-crew__docs .amb-docform { margin-top:.85rem; }
</style>
@endpush

@push('scripts')
{{-- Cámara del set: convierte HEIC (iPhone/iPad) a JPEG y comprime EN EL NAVEGADOR antes de subir. --}}
<script src="/js/cc-photo.js"></script>
<script src="/js/cc-photo-auto.js"></script>
@endpush

@endsection
