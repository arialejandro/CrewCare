@extends('layouts.app')

@section('title', 'Materialidad - '.($branding['brand_name'] ?? 'CrewCare'))

@section('content')

{{-- Mismo sistema de tarjetas/campos que el conteo. --}}
@include('componentes._form-kit')

@push('styles')
<style>
    .med-report { color: var(--text); }

    .cc-banner {
        display: flex; align-items: center; gap: .6rem;
        padding: .8rem 1.05rem; margin-bottom: 1.25rem;
        border: 1px solid color-mix(in srgb, var(--warn) 35%, transparent);
        border-left: 4px solid var(--warn);
        border-radius: var(--radius-sm, 11px);
        background: color-mix(in srgb, var(--warn) 12%, transparent);
        color: var(--warn); font-weight: 600; font-size: .9rem; line-height: 1.4;
    }
    .cc-banner .cc-ico-18 { flex: none; }

    /* Subnav Conteo / Materialidad. */
    .cc-subnav { display: flex; gap: .5rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
    .cc-subnav a {
        padding: .5rem .95rem; border-radius: 10px; font-size: .9rem; font-weight: 600;
        text-decoration: none; border: 1px solid var(--stroke, var(--border)); color: var(--text-muted);
        transition: color .15s ease, border-color .15s ease, background-color .15s ease;
    }
    .cc-subnav a.is-active { background: var(--brand-primary); color: #fff; border-color: var(--brand-primary); }
    .cc-subnav a:hover:not(.is-active) { color: var(--brand-primary); border-color: var(--brand-primary); }

    .cc-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
    .cc-stat { padding: 1rem 1.15rem; border: 1px solid var(--stroke, var(--border)); border-radius: var(--radius, 16px); background: var(--surface-2); }
    .cc-stat__lbl { font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; color: var(--text-muted); font-weight: 600; }
    .cc-stat__val { font-size: 1.9rem; font-weight: 800; color: var(--brand-primary); line-height: 1.1; margin-top: .25rem; }

    /* Galería de evidencia. */
    .cc-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; }
    .cc-eviq {
        border: 1px solid var(--stroke, var(--border)); border-radius: var(--radius, 14px);
        overflow: hidden; background: var(--surface-2); display: flex; flex-direction: column;
    }
    .cc-eviq__img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; background: #0000000d; display: block; }
    .cc-eviq__body { padding: .7rem .85rem; }
    .cc-eviq__date { font-size: .78rem; font-weight: 700; color: var(--brand-primary); }
    .cc-eviq__note { font-size: .86rem; color: var(--text); margin-top: .2rem; word-break: break-word; }
    .cc-eviq__empty-note { font-size: .82rem; color: var(--text-muted); font-style: italic; margin-top: .2rem; }

    .cc-report-empty { text-align: center; padding: 2.5rem 1rem; color: var(--text-muted); }
    .cc-report-empty .cc-empty-ico { color: var(--brand-primary); margin-bottom: .5rem; }
</style>
@endpush

<div class="med-report container-fluid py-4" style="max-width: 1000px;">

    <div class="cc-banner">
        @include('componentes._icon', ['name' => 'shield-alert', 'class' => 'cc-ico-18', 'label' => null])
        <span>{{ __('MATERIALIDAD — EVIDENCIA FISCAL · la fecha de captura la pone el servidor y no es editable') }}</span>
    </div>

    {{-- Pestañas. --}}
    <nav class="cc-subnav">
        <a href="{{ route('medical.materials') }}">{{ __('Conteo') }}</a>
        <a href="{{ route('medical.materiality') }}" class="is-active">{{ __('Materialidad') }}</a>
    </nav>

    {{-- Encabezado. --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'image', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div>
                <div class="cc-muted small text-uppercase" style="letter-spacing:.08em; font-weight:700;">{{ $branding['brand_name'] ?? 'CrewCare' }}</div>
                <h1 class="h4 fw-bold mb-0">{{ __('Materialidad (evidencia fiscal)') }}</h1>
                <div class="cc-muted small">{{ $rangeLabel }}</div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-3 d-flex align-items-center gap-2" role="alert">
            @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico-18', 'label' => null])
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-warning alert-dismissible fade show shadow-sm border-0 rounded-3 d-flex align-items-center gap-2" role="alert">
            @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico-18', 'label' => null])
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- ===== Subir evidencia ===== --}}
    <div class="cc-form-card">
        <div class="cc-form-card__head">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'plus', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div class="cc-form-card__titles">
                <h2 class="cc-form-card__title">{{ __('Subir evidencia') }}</h2>
                <p class="cc-form-card__sub">{{ __('Una o varias fotos (máx. 12 MB c/u) + un concepto opcional.') }}</p>
            </div>
        </div>
        <div class="cc-form-card__body">
            <form method="POST" action="{{ route('medical.materiality.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="cc-field mb-0">
                            <label for="photos" class="cc-label">{{ __('Fotos') }} <span class="cc-req" aria-hidden="true">*</span></label>
                            <input type="file" name="photos[]" id="photos" accept="image/*,.heic,.heif" multiple required data-cc-photo
                                   class="form-control cc-control @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror">
                            @error('photos')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @error('photos.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="cc-field mb-0">
                            <label for="note" class="cc-label">{{ __('Concepto') }} <span class="cc-muted">({{ __('opcional') }})</span></label>
                            <input type="text" name="note" id="note" maxlength="500" value="{{ old('note') }}"
                                   placeholder="{{ __('p. ej. Compra de botiquín — factura A-123') }}"
                                   class="form-control cc-control @error('note') is-invalid @enderror">
                            @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-primary cc-cta">
                        @include('componentes._icon', ['name' => 'upload', 'class' => 'cc-ico-18', 'label' => null])
                        {{ __('Guardar evidencia') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===== Filtro por rango + PDF ===== --}}
    <div class="cc-form-card no-print">
        <div class="cc-form-card__head">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'filter', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div class="cc-form-card__titles">
                <h2 class="cc-form-card__title">{{ __('Rango de fechas') }}</h2>
                <p class="cc-form-card__sub">{{ __('Filtra la evidencia por periodo y expórtala a PDF para contabilidad.') }}</p>
            </div>
        </div>
        <div class="cc-form-card__body">
            <form method="GET" action="{{ route('medical.materiality') }}" class="row g-3 align-items-end">
                <div class="col-12 col-sm-6 col-md-auto">
                    <div class="cc-field mb-0">
                        <label for="from" class="cc-label">{{ __('Desde') }}</label>
                        <input id="from" type="date" name="from" value="{{ $from }}" class="form-control cc-control">
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-auto">
                    <div class="cc-field mb-0">
                        <label for="to" class="cc-label">{{ __('Hasta') }}</label>
                        <input id="to" type="date" name="to" value="{{ $to }}" class="form-control cc-control">
                    </div>
                </div>
                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-primary cc-cta w-100">
                        @include('componentes._icon', ['name' => 'filter', 'class' => 'cc-ico-18', 'label' => null])
                        {{ __('Filtrar') }}
                    </button>
                </div>
                <div class="col-12 col-md-auto">
                    <a href="{{ route('medical.materiality.pdf', ['from' => $from, 'to' => $to]) }}"
                       target="_blank" class="cc-btn-ghost w-100 justify-content-center">
                        @include('componentes._icon', ['name' => 'download', 'class' => 'cc-ico-16', 'label' => null])
                        {{ __('Exportar PDF') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- ===== Indicador ===== --}}
    <div class="cc-stat-grid">
        <div class="cc-stat">
            <div class="cc-stat__lbl">{{ __('Evidencias en el rango') }}</div>
            <div class="cc-stat__val">{{ $totalPhotos }}</div>
        </div>
    </div>

    {{-- ===== Galería / vacío ===== --}}
    @if(!$tableReady)
        <div class="cc-form-card">
            <div class="cc-form-card__body">
                <div class="cc-report-empty">
                    <div class="cc-empty-ico">@include('componentes._icon', ['name' => 'image', 'class' => 'cc-ico-24', 'label' => null])</div>
                    <h5 class="fw-bold">{{ __('Materialidad no disponible aún') }}</h5>
                    <p class="mb-0">{{ __('La tabla de evidencia se activará cuando se aplique la actualización.') }}</p>
                </div>
            </div>
        </div>
    @elseif($photos->isEmpty())
        <div class="cc-form-card">
            <div class="cc-form-card__body">
                <div class="cc-report-empty">
                    <div class="cc-empty-ico">@include('componentes._icon', ['name' => 'image', 'class' => 'cc-ico-24', 'label' => null])</div>
                    <h5 class="fw-bold">{{ __('Sin evidencia en el rango') }}</h5>
                    <p class="mb-0">{{ __('Sube fotos con el formulario de arriba o cambia el rango de fechas.') }}</p>
                </div>
            </div>
        </div>
    @else
        <div class="cc-gallery">
            @foreach($photos as $photo)
                <figure class="cc-eviq m-0">
                    <a href="{{ $photo->image_path }}" target="_blank" rel="noopener">
                        <img src="{{ $photo->image_path }}" alt="{{ __('Evidencia') }}" class="cc-eviq__img" loading="lazy">
                    </a>
                    <figcaption class="cc-eviq__body">
                        <div class="cc-eviq__date">
                            {{ optional($photo->created_at)->format('d/m/Y H:i') ?? '—' }}
                        </div>
                        @if(!empty($photo->note))
                            <div class="cc-eviq__note">{{ $photo->note }}</div>
                        @else
                            <div class="cc-eviq__empty-note">{{ __('Sin concepto') }}</div>
                        @endif
                    </figcaption>
                </figure>
            @endforeach
        </div>
    @endif

</div>
@endsection

@push('scripts')
{{-- HEIC (iPhone): conversión a JPEG en el navegador antes de subir (el servidor no decodifica HEIC). --}}
<script src="/js/cc-photo.js"></script>
<script src="/js/cc-photo-auto.js"></script>
@endpush
