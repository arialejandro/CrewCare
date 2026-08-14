@extends('layouts.app')
@section('content')
{{-- CONFIG de la RUTA de firma (Paso C). El puesto DEFINE quién aparece; el sobre CONGELA a la
     persona al crear. El puesto NO otorga accesos: solo determina quién firma. --}}
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:720px">
        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Ruta de firma') }}</h1>
                <p class="text-muted mb-0 small">{{ __('Qué PUESTO prepara/valida y qué puesto obliga a la empresa. El contratado se resuelve solo.') }}</p>
            </div>
        </div>

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('contracts.route.config.update') }}" class="row g-3">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">{{ __('Prepara y valida') }}</label>
                        <select name="preparer_position_id" class="form-select js-typeahead">
                            <option value="">{{ __('— Puesto —') }}</option>
                            @foreach($positions as $p)
                                <option value="{{ $p->id }}" @selected((int) $preparerId === (int) $p->id)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">{{ __('Obliga a la empresa') }}</label>
                        <select name="binder_position_id" class="form-select js-typeahead">
                            <option value="">{{ __('— Puesto —') }}</option>
                            @foreach($positions as $p)
                                <option value="{{ $p->id }}" @selected((int) $binderId === (int) $p->id)>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">{{ __('Orden de la ruta') }}</label>
                        <input type="text" name="route_order" value="{{ old('route_order', $routeOrder) }}" class="form-control font-monospace" placeholder="preparer,contracted,binder">
                        <div class="form-text">{{ __('Papeles separados por coma. Por defecto: preparer, contracted, binder.') }}</div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-crew">{{ __('Guardar') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
