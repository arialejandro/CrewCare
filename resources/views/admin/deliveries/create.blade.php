@extends('layouts.app')
@section('content')

<div class="container py-4" style="max-width:720px">
    <div class="adm-header d-flex align-items-center gap-2">
        <span class="adm-icon">@include('componentes._icon', ['name' => 'send', 'class' => 'cc-ico', 'label' => null])</span>
        <div>
            <h1 class="adm-title">Nuevo envío</h1>
            <p class="adm-subtitle">Sube el PDF, escribe el mensaje y se enviará con marca de agua por persona.</p>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif

    <form action="{{ route('deliveries.store') }}" method="POST" enctype="multipart/form-data" class="card cc-card">
        @csrf
        <div class="card-body d-flex flex-column gap-3">
            <div>
                <label class="form-label fw-semibold">Asunto</label>
                <input type="text" name="title" class="form-control" maxlength="180" required
                       value="{{ old('title') }}" placeholder="Aviso de producción — cambio de locación">
            </div>

            <div>
                <label class="form-label fw-semibold">Mensaje <span class="text-muted fw-normal">(opcional)</span></label>
                <textarea name="body" class="form-control" rows="4" maxlength="4000"
                          placeholder="Escribe el cuerpo del correo…">{{ old('body') }}</textarea>
            </div>

            <div>
                <label class="form-label fw-semibold">Documento (PDF)</label>
                <input type="file" name="document" accept="application/pdf" class="form-control" required>
                <div class="form-text">Máximo 30 MB. Cada copia sale con el nombre en créditos del destinatario marcado en diagonal.</div>
            </div>

            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="wm" name="watermark" value="1" {{ old('watermark', '1') ? 'checked' : '' }}>
                <label class="form-check-label" for="wm">Marca de agua por persona</label>
            </div>

            <div class="alert alert-info mb-0 py-2">
                Se enviará a <b>{{ $recipientCount }}</b> usuario(s) activo(s) con correo. Los desactivados no reciben.
            </div>
        </div>
        <div class="card-footer d-flex gap-2 justify-content-end">
            <a href="{{ route('deliveries.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button class="btn btn-primary" onclick="return confirm('¿Enviar a los {{ $recipientCount }} usuarios activos?')">
                @include('componentes._icon', ['name' => 'send', 'class' => 'cc-ico me-1', 'label' => null]) Enviar
            </button>
        </div>
    </form>
</div>
@endsection
