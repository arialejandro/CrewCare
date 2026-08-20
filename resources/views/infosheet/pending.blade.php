@extends('layouts.app')
@section('content')
@push('styles')
    @include('componentes._crew-list-styles')
@endpush
{{-- BANDEJA "POR AUTORIZAR" — el DISPARADOR de descubrimiento del autorizador: los tratos crew_work
     capturados, sin sobre aún, que este usuario puede autorizar. Espejo de "Contratos por firmar".
     Cada fila lleva a la ficha del payee, donde firma la autorización (que dispara el contrato). --}}
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:960px">

        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Infosheets por autorizar') }}</h1>
                <p class="text-muted mb-0 small">{{ __('Tratos capturados que esperan tu autorización para generar el contrato.') }}</p>
            </div>
        </div>

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        @if($contracts->isEmpty())
            <div class="text-center text-muted py-5">
                @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico', 'label' => null])
                <p class="mb-0 mt-2">{{ __('No tienes tratos pendientes por autorizar.') }}</p>
            </div>
        @else
            <div class="d-flex flex-column gap-2">
                @foreach($contracts as $c)
                    @php $p = $c->payee; @endphp
                    <div class="card">
                        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div>
                                <div class="fw-semibold">{{ optional($p)->name ?: __('(sin nombre)') }}</div>
                                <div class="small text-muted">
                                    {{ $c->title ?: ($c->crew_activity ?: __('Trato de crew')) }}
                                    @if(optional($c->department)->name) · {{ $c->department->name }}@endif
                                    @if($c->fee_amount > 0) · {{ number_format((float) $c->fee_amount, 2) }} {{ $c->fee_currency ?: 'MXN' }}@endif
                                </div>
                            </div>
                            @if($p)
                                <a href="{{ route('payees.show', $p) }}#autorizar-infosheet"
                                   class="btn btn-sm btn-crew d-inline-flex align-items-center gap-1">
                                    @include('componentes._icon', ['name' => 'check-circle', 'label' => null]) {{ __('Revisar y autorizar') }}
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    </div>
</div>
@endsection
