@extends('layouts.app')
@section('content')
{{-- COTIZACIÓN · captura adentro (nueva). --}}
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:920px">
        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Nueva cotización') }}</h1>
                <p class="text-muted mb-0 small">{{ __('El paso previo al contrato. Sube el PDF tal cual llegó o captura sus partidas.') }}</p>
            </div>
            <div class="ms-auto"><a href="{{ route('quotations.index') }}" class="btn btn-crew-soft">{{ __('← Cotizaciones') }}</a></div>
        </div>

        @include('quotations._form', ['action' => route('quotations.store'), 'method' => 'POST', 'quotation' => null, 'version' => null, 'departments' => $departments])
    </div>
</div>
@endsection
