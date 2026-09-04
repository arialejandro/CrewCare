@extends('layouts.app')
@include('componentes._confirm-submit')
@section('content')
{{-- DIRECCIONES PRIVADAS + allowlist (§3 Capa 4). Transpo presetea; la allowlist decide quién ve la
     CALLE REAL en la vista de la orden. El driver de una corrida ve la calle por asignación (aparte).
     Quien no cae en ninguna ve 'CASA' (igual que el PDF). --}}
<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:960px">

        <div class="crew-header d-flex align-items-center gap-3 mb-3">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Direcciones privadas') }}</h1>
                <p class="text-muted mb-0 small">{{ __('El documento imprime “CASA”; la calle real sólo la ve la lista y el driver asignado.') }}</p>
            </div>
        </div>

        @if (session('ok'))<div class="alert alert-success py-2">{{ session('ok') }}</div>@endif
        @if ($errors->any())
            <div class="alert alert-danger py-2">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
        @endif

        {{-- Alta --}}
        <div class="border rounded-3 p-3 bg-body-tertiary mb-4">
            <h2 class="h6 mb-2">{{ __('Nueva dirección') }}</h2>
            <form method="POST" action="{{ route('transport.address.store') }}" class="row g-2">
                @csrf
                <div class="col-md-4">
                    <label class="form-label small mb-0">{{ __('Etiqueta interna') }}</label>
                    <input type="text" name="label" value="{{ old('label') }}" class="form-control form-control-sm" maxlength="120" placeholder="{{ __('Casa de Juan') }}">
                </div>
                <div class="col-md-5">
                    <label class="form-label small mb-0">{{ __('Calle real') }}</label>
                    <input type="text" name="address" value="{{ old('address') }}" class="form-control form-control-sm" maxlength="255" placeholder="{{ __('Av. Siempre Viva 742, Col. …') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0">{{ __('Rótulo público') }}</label>
                    <input type="text" name="public_label" value="{{ old('public_label') }}" class="form-control form-control-sm" maxlength="60" placeholder="CASA">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="hidden" name="is_private" value="0">
                        <input class="form-check-input" type="checkbox" name="is_private" value="1" id="isp_new" checked>
                        <label class="form-check-label small" for="isp_new">{{ __('Privada (enmascarar la calle a quien no esté autorizado)') }}</label>
                    </div>
                </div>
                <div class="col-12"><button class="btn btn-primary btn-sm">{{ __('Guardar dirección') }}</button></div>
            </form>
        </div>

        {{-- Listado --}}
        @forelse ($addresses as $ad)
            <div class="border rounded-3 p-3 mb-3">
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                    <div>
                        <span class="fw-semibold">{{ $ad->label }}</span>
                        @if ($ad->is_private)<span class="badge bg-secondary ms-1">{{ __('Privada') }}</span>@else<span class="badge bg-success-subtle text-dark border ms-1">{{ __('Pública') }}</span>@endif
                        <div class="small text-muted mt-1">
                            @if ($ad->address)@include('componentes._icon', ['name' => 'map-pin', 'label' => null]) {{ $ad->address }}@else <span class="fst-italic">{{ __('Sin calle capturada') }}</span>@endif
                        </div>
                        <div class="small text-muted">{{ __('Público') }}: <span class="fw-semibold">{{ $ad->publicLabel() }}</span></div>
                    </div>
                    <form method="POST" action="{{ route('transport.address.destroy', $ad) }}" data-confirm="{{ __('¿Dar de baja esta dirección?') }}">
                        @csrf<button class="btn btn-sm btn-outline-danger">{{ __('Baja') }}</button>
                    </form>
                </div>

                {{-- Allowlist --}}
                <div class="mt-2 pt-2 border-top">
                    <div class="small text-uppercase text-muted mb-1">{{ __('Ven la calle real') }} ({{ $ad->viewers->count() }})</div>
                    @forelse ($ad->viewers as $v)
                        <span class="badge bg-light text-dark border me-1 mb-1">
                            {{ \App\Models\User::displayName($v) }}
                            <form method="POST" action="{{ route('transport.address.viewer.remove', [$ad, $v]) }}" class="d-inline">
                                @csrf<button class="btn btn-sm btn-link text-danger p-0 ms-1" style="text-decoration:none">×</button>
                            </form>
                        </span>
                    @empty
                        <span class="small text-muted">{{ __('Nadie: sólo el driver asignado ve la calle.') }}</span>
                    @endforelse

                    <form method="POST" action="{{ route('transport.address.viewer.add', $ad) }}" class="row g-1 mt-1 align-items-end">
                        @csrf
                        <div class="col-auto">
                            <select name="user_id" class="form-select form-select-sm js-typeahead" data-ta-placeholder="{{ __('Agregar a la lista…') }}">
                                <option value="">{{ __('Agregar persona…') }}</option>
                                @foreach ($people as $p)<option value="{{ $p['id'] }}">{{ $p['name'] }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-auto"><button class="btn btn-sm btn-outline-primary">{{ __('+ agregar') }}</button></div>
                    </form>
                </div>

                {{-- Editar --}}
                <details class="mt-2">
                    <summary class="small text-primary" style="cursor:pointer">{{ __('Editar dirección') }}</summary>
                    <form method="POST" action="{{ route('transport.address.update', $ad) }}" class="row g-2 mt-1">
                        @csrf
                        <div class="col-md-4"><label class="form-label small mb-0">{{ __('Etiqueta interna') }}</label><input type="text" name="label" value="{{ $ad->label }}" class="form-control form-control-sm" maxlength="120"></div>
                        <div class="col-md-5"><label class="form-label small mb-0">{{ __('Calle real') }}</label><input type="text" name="address" value="{{ $ad->address }}" class="form-control form-control-sm" maxlength="255"></div>
                        <div class="col-md-3"><label class="form-label small mb-0">{{ __('Rótulo público') }}</label><input type="text" name="public_label" value="{{ $ad->public_label }}" class="form-control form-control-sm" maxlength="60" placeholder="CASA"></div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="hidden" name="is_private" value="0">
                                <input class="form-check-input" type="checkbox" name="is_private" value="1" id="isp_{{ $ad->id }}" @checked($ad->is_private)>
                                <label class="form-check-label small" for="isp_{{ $ad->id }}">{{ __('Privada') }}</label>
                            </div>
                        </div>
                        <div class="col-12"><button class="btn btn-primary btn-sm">{{ __('Guardar cambios') }}</button></div>
                    </form>
                </details>
            </div>
        @empty
            <p class="text-muted">{{ __('Sin direcciones privadas todavía.') }}</p>
        @endforelse

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush
@push('scripts')
    @include('componentes._typeahead')
@endpush
@endsection
