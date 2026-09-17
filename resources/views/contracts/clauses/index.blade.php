@extends('layouts.app')
@section('content')
{{-- BIBLIOTECA DE CLAUSULADOS (Paso B). La productora sube su clausulado (PDF, byte-intact),
     declara subtipos + idioma, y lo versiona sin alterar lo ya emitido. Se desactiva sin borrar. --}}
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:960px">

        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Clausulados de contrato') }}</h1>
                <p class="text-muted mb-0 small">{{ __('El PDF se conserva tal cual se sube. CrewCare genera la carátula aparte; ambos se firman como paquete.') }}</p>
            </div>
        </div>

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
        @unless($prodId)<div class="alert alert-warning">{{ __('No hay una producción activa.') }}</div>@endunless

        {{-- Subir --}}
        <div class="card mb-4">
            <div class="card-header fw-semibold">{{ __('Subir clausulado') }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('contracts.clauses.store') }}" enctype="multipart/form-data" class="row g-3">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label small text-muted">{{ __('Nombre') }}</label>
                        <input type="text" name="name" value="{{ old('name') }}" class="form-control" placeholder="{{ __('Contrato Crew, Vendor Agreement…') }}">
                        <div class="form-text">{{ __('Se ignora si eliges "nueva versión de".') }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">{{ __('Nueva versión de') }} <span class="text-muted">({{ __('opcional') }})</span></label>
                        <select name="replaces_id" class="form-select">
                            <option value="">{{ __('— Clausulado nuevo —') }}</option>
                            @foreach($families as $rootId => $versions)
                                @php $latest = $versions->first(); @endphp
                                <option value="{{ $latest->id }}">{{ $latest->name }} (v{{ $latest->version }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted d-block">{{ __('Aplica a') }}</label>
                        @foreach($subtypes as $val => $label)
                            <label class="me-3"><input type="checkbox" name="applies_to[]" value="{{ $val }}"> {{ $label }}</label>
                        @endforeach
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">{{ __('Idioma') }}</label>
                        <select name="language" class="form-select">
                            @foreach($languages as $val => $label)<option value="{{ $val }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <input type="file" name="file" accept="application/pdf" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-crew">{{ __('Subir') }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Biblioteca --}}
        @forelse($families as $rootId => $versions)
            @php $head = $versions->first(); @endphp
            <div class="card mb-3">
                <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                    <span>{{ $head->name }}</span>
                    <span class="badge text-bg-secondary">{{ __('v') }}{{ $head->version }} · {{ $versions->count() }} {{ __('versiones') }}</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table cc-stack align-middle mb-0">
                            <thead><tr>
                                <th>{{ __('Versión') }}</th><th>{{ __('Aplica a') }}</th><th>{{ __('Idioma') }}</th>
                                <th>{{ __('Estado') }}</th><th class="text-end">{{ __('Acciones') }}</th>
                            </tr></thead>
                            <tbody>
                                @foreach($versions as $c)
                                    <tr>
                                        <td data-label="{{ __('Versión') }}">v{{ $c->version }}</td>
                                        <td data-label="{{ __('Aplica a') }}">
                                            @foreach((array) $c->applies_to as $s)<span class="badge text-bg-light border">{{ $subtypes[$s] ?? $s }}</span> @endforeach
                                        </td>
                                        <td data-label="{{ __('Idioma') }}">{{ $languages[$c->language] ?? $c->language }}</td>
                                        <td data-label="{{ __('Estado') }}">
                                            @if($c->is_active)<span class="badge text-bg-success">{{ __('Activo') }}</span>
                                            @else<span class="badge text-bg-secondary">{{ __('Inactivo') }}</span>@endif
                                        </td>
                                        <td data-label="{{ __('Acciones') }}" class="text-end">
                                            <a href="{{ route('contracts.clauses.download', $c) }}" class="btn btn-sm btn-crew-soft" target="_blank" rel="noopener">{{ __('Descargar') }}</a>
                                            <form method="POST" action="{{ route('contracts.clauses.toggle', $c) }}" class="d-inline">
                                                @csrf<button class="btn btn-sm btn-outline-secondary">{{ $c->is_active ? __('Desactivar') : __('Reactivar') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @empty
            <p class="text-muted">{{ __('Aún no hay clausulados.') }}</p>
        @endforelse

    </div>
</div>
@endsection
