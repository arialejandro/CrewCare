@extends('layouts.app')
@section('content')

{{-- Sistema de estilos de formularios reutilizable (tarjetas, campos, controles, CTA). --}}
@include('componentes._form-kit')

{{--
    providers.blade.php — PROVEEDORES de ambulancias (empresas prestadoras).
    Se califica LA PRIMERA VEZ que se necesita una empresa; luego no se vuelve a
    preguntar cada día. Esta pantalla lista las empresas activas y permite dar de
    alta una nueva. Cada empresa enlaza a su ficha (documentos + padrón).

    Recibe: $providers (Collection App\Models\AmbulanceProvider).
--}}
<div class="crew-page container-fluid px-3 px-md-4 py-4" style="max-width: 1000px;">

    {{-- Encabezado --}}
    <div class="crew-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'ambulance', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Proveedores de ambulancias') }}</h1>
                <p class="text-muted mb-0 small">{{ __('Empresas prestadoras. Se califican una vez; sus documentos y su tripulación viven en la ficha.') }}</p>
            </div>
        </div>
    </div>

    {{-- Feedback de validación + flash (cubre los DOS bloques: lista y alta). --}}
    @include('componentes._form-feedback')

    {{-- Lista de proveedores activos --}}
    <div class="cc-form-card">
        <div class="cc-form-card__head">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'building-2', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div class="cc-form-card__titles">
                <h2 class="cc-form-card__title">{{ __('Empresas registradas') }}</h2>
                <p class="cc-form-card__sub">{{ __('Toca una empresa para ver sus documentos y su padrón de tripulantes.') }}</p>
            </div>
        </div>
        <div class="cc-form-card__body">
            @if($providers->isEmpty())
                <div class="amb-empty">
                    @include('componentes._icon', ['name' => 'truck', 'class' => 'cc-ico-24', 'label' => null])
                    <p class="mb-0">{{ __('Todavía no hay proveedores registrados. Da de alta el primero abajo.') }}</p>
                </div>
            @else
                <div class="amb-provider-list">
                    @foreach($providers as $provider)
                        <a href="{{ route('ambulance.provider.show', ['provider' => $provider->id]) }}" class="amb-provider">
                            <span class="amb-provider__ico">
                                @include('componentes._icon', ['name' => 'building-2', 'class' => 'cc-ico-20', 'label' => null])
                            </span>
                            <span class="amb-provider__info">
                                <span class="amb-provider__name">{{ $provider->name }}</span>
                                <span class="amb-provider__meta">
                                    @if($provider->rfc)<span>{{ __('RFC') }}: {{ $provider->rfc }}</span>@endif
                                    @if($provider->contact_phone)
                                        <span>@include('componentes._icon', ['name' => 'phone', 'class' => 'cc-ico-14', 'label' => null]) {{ $provider->contact_phone }}</span>
                                    @endif
                                    @if($provider->sanitary_manager)<span>{{ __('Resp. sanitario') }}: {{ $provider->sanitary_manager }}</span>@endif
                                </span>
                            </span>
                            <span class="amb-provider__chevron">
                                @include('componentes._icon', ['name' => 'chevron-right', 'class' => 'cc-ico-20', 'label' => null])
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Alta de proveedor (solo quien gestiona; producción con ambulance.view no da de alta) --}}
    @can('ambulance.manage')
    <div class="cc-form-card">
        <div class="cc-form-card__head">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'plus', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div class="cc-form-card__titles">
                <h2 class="cc-form-card__title">{{ __('Dar de alta un proveedor') }}</h2>
                <p class="cc-form-card__sub">{{ __('Solo los datos de la empresa. Los documentos y la tripulación se agregan después, en la ficha.') }}</p>
            </div>
        </div>
        <div class="cc-form-card__body">
            <form action="{{ route('ambulance.provider.store') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="name" class="cc-label">
                                {{ __('Nombre o razón social') }} <span class="cc-req">*</span>
                            </label>
                            <input id="name" type="text" name="name" maxlength="255" required
                                   class="form-control cc-control" value="{{ old('name') }}">
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="rfc" class="cc-label">{{ __('RFC') }}</label>
                            <input id="rfc" type="text" name="rfc" maxlength="20"
                                   class="form-control cc-control" value="{{ old('rfc') }}">
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="contact_phone" class="cc-label">{{ __('Teléfono de contacto') }}</label>
                            <input id="contact_phone" type="tel" name="contact_phone" maxlength="50"
                                   class="form-control cc-control" value="{{ old('contact_phone') }}">
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="cc-field">
                            <label for="sanitary_manager" class="cc-label">{{ __('Responsable sanitario') }}</label>
                            <input id="sanitary_manager" type="text" name="sanitary_manager" maxlength="255"
                                   class="form-control cc-control" value="{{ old('sanitary_manager') }}">
                            <span class="cc-help">{{ __('Responsable sanitario declarado por la empresa.') }}</span>
                        </div>
                    </div>
                </div>

                <div class="d-grid d-md-flex justify-content-md-end mt-3">
                    <button type="submit" class="btn btn-primary cc-cta w-100 w-md-auto">
                        @include('componentes._icon', ['name' => 'plus', 'class' => 'cc-ico-18', 'label' => null])
                        {{ __('Agregar proveedor') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endcan

</div>

@push('styles')
@include('componentes._crew-list-styles')
<style>
    .amb-empty { display:flex; flex-direction:column; align-items:center; gap:.6rem;
        padding:1.75rem 1rem; text-align:center; color:var(--text-muted); }
    .amb-empty svg { color:var(--text-muted); }

    .amb-provider-list { display:flex; flex-direction:column; gap:.6rem; }
    .amb-provider {
        display:flex; align-items:center; gap:.85rem; padding:.85rem 1rem;
        border:1px solid var(--stroke, var(--border)); border-radius:var(--radius-sm, 11px);
        background:var(--surface); color:var(--text); text-decoration:none;
        transition:border-color .15s ease, background-color .15s ease, transform .1s ease;
    }
    .amb-provider:hover { border-color:var(--brand-primary);
        background:rgba(var(--brand-primary-rgb), .05); transform:translateY(-1px); }
    .amb-provider__ico {
        flex:none; width:40px; height:40px; border-radius:11px;
        display:inline-flex; align-items:center; justify-content:center;
        color:var(--brand-primary); background:rgba(var(--brand-primary-rgb), .12);
        border:1px solid rgba(var(--brand-primary-rgb), .22);
    }
    .amb-provider__info { display:flex; flex-direction:column; gap:.15rem; min-width:0; flex:1 1 auto; }
    .amb-provider__name { font-weight:700; color:var(--text); }
    .amb-provider__meta { display:flex; flex-wrap:wrap; gap:.15rem 1rem; font-size:.8rem; color:var(--text-muted); }
    .amb-provider__meta span { display:inline-flex; align-items:center; gap:.25rem; }
    .amb-provider__chevron { flex:none; color:var(--text-muted); }
</style>
@endpush

@endsection
