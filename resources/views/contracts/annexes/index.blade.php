@extends('layouts.app')
@section('content')
{{-- BIBLIOTECA DE ANEXOS (Paso C). PDF byte-intact, nombre libre; el sobre los agrupa con la
     carátula y el clausulado y se firman como paquete. --}}
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:820px">
        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Anexos del contrato') }}</h1>
                <p class="text-muted mb-0 small">{{ __('NDA, anti-acoso, código de conducta, aviso de privacidad… Cada productora usa los suyos.') }}</p>
            </div>
        </div>

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
        @unless($prodId)<div class="alert alert-warning">{{ __('No hay una producción activa.') }}</div>@endunless

        <div class="card mb-4">
            <div class="card-header fw-semibold">{{ __('Subir anexo') }}</div>
            <div class="card-body">
                <form method="POST" action="{{ route('contracts.annexes.store') }}" enctype="multipart/form-data" class="row g-3">
                    @csrf
                    <div class="col-md-7">
                        <label class="form-label small text-muted">{{ __('Nombre') }}</label>
                        <input type="text" name="name" value="{{ old('name') }}" class="form-control" placeholder="{{ __('NDA, Código de conducta…') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted">{{ __('PDF') }}</label>
                        <input type="file" name="file" accept="application/pdf" class="form-control" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-crew w-100">{{ __('Subir') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                @if($annexes->isEmpty())
                    <p class="text-muted mb-0 p-3">{{ __('Aún no hay anexos.') }}</p>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($annexes as $a)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    <span class="fw-semibold">{{ $a->name }}</span>
                                    @if($a->is_active)<span class="badge text-bg-success ms-1">{{ __('Activo') }}</span>
                                    @else<span class="badge text-bg-secondary ms-1">{{ __('Inactivo') }}</span>@endif
                                </span>
                                <span class="d-flex gap-2">
                                    <a href="{{ route('contracts.annexes.download', $a) }}" target="_blank" rel="noopener" class="btn btn-sm btn-crew-soft">{{ __('Descargar') }}</a>
                                    <form method="POST" action="{{ route('contracts.annexes.toggle', $a) }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $a->is_active ? __('Desactivar') : __('Reactivar') }}</button></form>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
