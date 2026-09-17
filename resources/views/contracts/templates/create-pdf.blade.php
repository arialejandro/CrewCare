@extends('layouts.app')
@section('content')
{{-- PDF FILLABLE · alta — subir el PDF ya redactado por la productora. Al guardar se abre el editor
     de etiquetas (colocar firmas y datos encima). CrewCare no redacta: solo coloca campos y estampa. --}}
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:720px">

        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'file-up', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Subir contrato en PDF') }}</h1>
                <p class="text-muted mb-0 small">{{ __('Sube el PDF que ya redactó tu área legal. Después colocarás las etiquetas de firma y datos sobre el documento.') }}</p>
            </div>
            <a href="{{ route('contracts.templates.index') }}" class="btn btn-crew-soft ms-auto">{{ __('Volver') }}</a>
        </div>

        <div class="alert alert-warning small mb-3" role="note">
            <strong>{{ __('Responsabilidad legal de la productora.') }}</strong>
            {{ __('El contenido jurídico lo define y respalda la productora. CrewCare solo ubica las firmas y datos sobre tu PDF y los estampa: no redacta contratos ni brinda asesoría legal.') }}
        </div>

        @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('contracts.templates.store_pdf') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('Nombre de la plantilla') }}</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                               placeholder="{{ __('Ej.: Contrato de servicios — crew') }}" required maxlength="191">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('Aplica a') }}</label>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach($subtypes as $val => $label)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="applies_to[]"
                                           id="ap_{{ $val }}" value="{{ $val }}"
                                           @checked(in_array($val, old('applies_to', ['crew_work'])))>
                                    <label class="form-check-label" for="ap_{{ $val }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('Tipo de documento') }}</label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="category" id="cat_contrato" value="contrato"
                                       @checked(old('category', 'contrato') === 'contrato')>
                                <label class="form-check-label" for="cat_contrato">{{ __('Contrato principal') }}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="category" id="cat_anexo" value="anexo"
                                       @checked(old('category') === 'anexo')>
                                <label class="form-check-label" for="cat_anexo">{{ __('Anexo (documento adicional)') }}</label>
                            </div>
                        </div>
                        <div class="mt-2" style="max-width:180px">
                            <label class="form-label small mb-1">{{ __('Orden (anexos)') }}</label>
                            <input type="number" name="sort_order" min="0" max="9999" class="form-control form-control-sm" value="{{ old('sort_order', 0) }}">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('Archivo PDF') }}</label>
                        <input type="file" name="pdf" class="form-control" accept="application/pdf,.pdf" required>
                        <div class="form-text">{{ __('PDF de hasta 20 MB. Se conserva el texto original: las etiquetas se estampan encima.') }}</div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-crew">{{ __('Subir y colocar etiquetas') }}</button>
                        <a href="{{ route('contracts.templates.index') }}" class="btn btn-crew-soft">{{ __('Cancelar') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
