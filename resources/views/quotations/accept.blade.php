@extends('layouts.app')
@section('content')
{{-- COTIZACIÓN · aceptación (Line Producer). Firma con el pad → sella la hoja de aceptación.
     El PDF/partidas originales NO se tocan. Vencida advierte, no bloquea. --}}
@php $money = fn ($n) => '$'.number_format((float) $n, 2).' MXN'; @endphp
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:720px">
        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Aceptar cotización') }}</h1>
                <p class="text-muted mb-0 small">{{ $quotation->emitter_name }}</p>
            </div>
            <div class="ms-auto"><a href="{{ route('quotations.show', $quotation) }}" class="btn btn-crew-soft">{{ __('← Volver') }}</a></div>
        </div>

        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        @if($quotation->isExpired())
            <div class="alert alert-warning small">{{ __('⚠ La vigencia de esta cotización ya venció. Puedes aceptarla igual (en la práctica se renegocia y se acepta).') }}</div>
        @endif

        <div class="card mb-3"><div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-4">{{ __('Emisor') }}</dt><dd class="col-sm-8">{{ $quotation->emitter_name }}</dd>
                @if($quotation->emitter_email)<dt class="col-sm-4">{{ __('Correo') }}</dt><dd class="col-sm-8">{{ $quotation->emitter_email }}</dd>@endif
                @if(optional($version)->quotation_number)<dt class="col-sm-4">{{ __('Número') }}</dt><dd class="col-sm-8">{{ $version->quotation_number }}</dd>@endif
                <dt class="col-sm-4">{{ __('Total') }}</dt><dd class="col-sm-8 fw-bold">{{ $money(optional($version)->total) }}</dd>
            </dl>
        </div></div>

        <form method="POST" action="{{ route('quotations.accept', $quotation) }}">
            @csrf
            <div class="card mb-3"><div class="card-body">
                <h2 class="h6 mb-2">{{ __('Tu firma de aceptación') }}</h2>
                <p class="text-muted small">{{ __('Al firmar, se genera y sella una hoja de aceptación con el hash del documento. El documento cotizado no se modifica.') }}</p>
                @include('componentes._signature-pad', ['adopted' => auth()->user()->adopted_signature ?? null, 'label' => null])
            </div></div>
            <button type="submit" class="btn btn-crew">{{ __('Aceptar y sellar') }}</button>
        </form>
    </div>
</div>
@endsection
