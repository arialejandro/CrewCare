@extends('layouts.app')
@section('content')

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4">

        <a href="{{ route('tools.index') }}" class="text-muted small d-inline-flex align-items-center gap-1 mb-3" style="text-decoration:none;">
            @include('componentes._icon', ['name' => 'chevron-left', 'label' => null]) {{ __('Inspección de herramienta') }}
        </a>

        <div class="crew-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                    @include('componentes._icon', ['name' => 'camera', 'class' => 'cc-ico', 'label' => null])
                </span>
                <div>
                    <h1 class="crew-title mb-0">{{ __('Imágenes de referencia') }}</h1>
                    <p class="text-muted mb-0 small">{{ __('Imagen genérica por TIPO de herramienta. Puedes poblarlas con el tiempo; no bloquea nada.') }}</p>
                </div>
            </div>

            <form method="get" action="{{ route('tools.images') }}" class="crew-search flex-grow-1 flex-lg-grow-0" style="min-width:280px;">
                <div class="input-group">
                    <span class="input-group-text border-end-0">
                        @include('componentes._icon', ['name' => 'search', 'class' => 'cc-ico', 'label' => null])
                    </span>
                    <input class="form-control border-start-0 ps-0" type="search" name="q" value="{{ $q }}"
                           placeholder="{{ __('nombre, código o apodo…') }}">
                </div>
            </form>
        </div>

        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
        @if ($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="row g-3">
            @forelse ($tools as $tool)
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="p-3 d-flex flex-column gap-2 h-100">
                            <div class="d-flex align-items-center justify-content-center rounded-3"
                                 style="height:140px;background:var(--surface-2);overflow:hidden;">
                                @if ($tool->imageUrl())
                                    <img src="{{ $tool->imageUrl() }}" alt="{{ $tool->name }}" style="max-width:100%;max-height:140px;object-fit:contain;">
                                @else
                                    <span class="text-muted d-inline-flex flex-column align-items-center gap-1">
                                        @include('componentes._icon', ['name' => 'camera', 'label' => null])
                                        <span class="small">{{ __('Sin imagen') }}</span>
                                    </span>
                                @endif
                            </div>
                            <div>
                                <strong class="d-block">{{ $tool->name }}</strong>
                                <span class="text-muted small">{{ $tool->code }}@if($tool->family) · {{ $tool->family->name }}@endif</span>
                            </div>
                            <form method="post" action="{{ route('tools.image.store', $tool->id) }}"
                                  enctype="multipart/form-data" class="mt-auto d-flex flex-column gap-2">
                                @csrf
                                <input type="file" name="image" class="form-control form-control-sm"
                                       accept="image/png,image/jpeg,image/webp,image/svg+xml,.svg,.heic,.heif" data-cc-photo required>
                                <button class="btn btn-sm btn-crew-accent d-inline-flex align-items-center justify-content-center gap-1">
                                    @include('componentes._icon', ['name' => 'upload', 'label' => null])
                                    {{ $tool->imageUrl() ? __('Reemplazar') : __('Subir') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="text-center py-5 text-muted">{{ __('No hay tipos de herramienta que coincidan.') }}</div>
                </div>
            @endforelse
        </div>

        @if ($tools->hasPages())
            <div class="mt-3">{!! $tools->links() !!}</div>
        @endif

    </div>
</div>

{{-- La cámara del set convierte HEIC a JPEG en el navegador; para SVG/PNG es passthrough. --}}
<script src="/js/cc-photo.js"></script>
<script src="/js/cc-photo-auto.js"></script>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush

@endsection
