@extends('layouts.app')
@section('content')
@push('styles')@include('componentes._crew-list-styles')@endpush
{{-- Editar un periodo abierto por error o con la fecha mal. Mismo formulario que el alta (partial
     _fields), con el toggle day-player. No cambia el estado (abierto/cerrado): eso va por sus botones. --}}
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1040px">

        <div class="mb-3">
            <a href="{{ route('periods.index') }}" class="btn btn-sm btn-crew-soft d-inline-flex align-items-center gap-1">
                @include('componentes._icon', ['name' => 'arrow-left', 'label' => null]) {{ __('Volver a periodos') }}
            </a>
        </div>

        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'calendar', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Editar periodo') }}</h1>
                <p class="text-muted mb-0 small">{{ $period->displayLabel() }}</p>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('periods.update', $period) }}" class="row g-3 js-period-form">
                    @csrf
                    @method('PUT')
                    @include('periods._fields', ['period' => $period])
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-crew">{{ __('Guardar cambios') }}</button>
                        <a href="{{ route('periods.index') }}" class="btn btn-crew-soft">{{ __('Cancelar') }}</a>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection
