@extends('layouts.app')
@section('content')
{{-- EDITOR DEL CATÁLOGO DE TIPOS (§5 Capa 4). Alta/edición/baja. El perfil PROPONE al crear un
     vehículo; editarlo NO cambia los ya registrados (guardan su attr_values), ni las actas selladas
     (usan su checklist_snapshot). Baja = desactivar (no borra). --}}
<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1040px">

        <div class="crew-header d-flex align-items-center gap-3 mb-3">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'truck', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Tipos de vehículo') }}</h1>
                <p class="text-muted mb-0 small">{{ __('El tipo propone el perfil; los atributos se ajustan por unidad.') }}</p>
            </div>
        </div>

        @if (session('ok'))<div class="alert alert-success py-2">{{ session('ok') }}</div>@endif
        @if ($errors->any())
            <div class="alert alert-danger py-2">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
        @endif

        {{-- Alta --}}
        <div class="border rounded-3 p-3 bg-body-tertiary mb-4">
            <h2 class="h6 mb-2">{{ __('Nuevo tipo') }}</h2>
            @include('transport.types._form', ['type' => null, 'action' => route('transport.type.store'), 'submit' => __('Crear tipo')])
        </div>

        {{-- Listado --}}
        @forelse ($types as $type)
            @php $used = (int) ($counts[$type->id] ?? 0); @endphp
            <div class="border rounded-3 p-3 mb-3 {{ $type->is_active ? '' : 'opacity-75' }}">
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                    <div>
                        <span class="fw-semibold">{{ $type->name_es }}</span>
                        <span class="font-monospace text-muted small ms-1">{{ $type->code }}</span>
                        @if ($type->is_special)<span class="badge bg-info-subtle text-dark border ms-1">{{ __('Especial') }}</span>@endif
                        @if (! $type->is_active)<span class="badge bg-secondary ms-1">{{ __('Desactivado') }}</span>@endif
                        <div class="text-muted small mt-1">
                            {{ $used }} {{ trans_choice('vehículo|vehículos', $used) }}
                            @if (! $type->is_special)
                                · {{ $type->profile()['powertrain'] }}@if($type->profile()['seats']) · {{ $type->profile()['seats'] }} {{ __('plazas') }}@endif
                            @endif
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        @if ($type->is_active)
                            <form method="POST" action="{{ route('transport.type.destroy', $type) }}"
                                  onsubmit="return confirm('{{ $used > 0 ? __('Este tipo tiene vehículos. Se DESACTIVA (deja de proponerse); los vehículos NO cambian. ¿Continuar?') : __('¿Desactivar este tipo?') }}')">
                                @csrf<button class="btn btn-sm btn-outline-danger">{{ __('Desactivar') }}</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('transport.type.restore', $type) }}">
                                @csrf<button class="btn btn-sm btn-outline-success">{{ __('Reactivar') }}</button>
                            </form>
                        @endif
                    </div>
                </div>

                @if ($used > 0)
                    <div class="small text-muted mt-2">
                        @include('componentes._icon', ['name' => 'lock', 'label' => null])
                        {{ __('Editar el perfil no altera estos vehículos: cada uno conserva sus atributos.') }}
                    </div>
                @endif

                <details class="mt-2">
                    <summary class="small text-primary" style="cursor:pointer">{{ __('Editar tipo') }}</summary>
                    <div class="mt-2">
                        @include('transport.types._form', ['type' => $type, 'action' => route('transport.type.update', $type), 'submit' => __('Guardar tipo')])
                    </div>
                </details>
            </div>
        @empty
            <p class="text-muted">{{ __('Sin tipos.') }}</p>
        @endforelse

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush
@endsection
