@extends('layouts.app')
@section('content')
{{-- COTIZACIÓN · editar la versión en curso (antes de aceptar). --}}
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:920px">
        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Editar cotización') }}</h1>
                <p class="text-muted mb-0 small">{{ $quotation->emitter_name }}</p>
            </div>
            <div class="ms-auto"><a href="{{ route('quotations.show', $quotation) }}" class="btn btn-crew-soft">{{ __('← Volver') }}</a></div>
        </div>

        @include('quotations._form', ['action' => route('quotations.update', $quotation), 'method' => 'PUT', 'quotation' => $quotation, 'version' => $version, 'departments' => $departments])
    </div>
</div>
@endsection
