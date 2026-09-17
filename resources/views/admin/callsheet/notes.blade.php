@extends('layouts.app')
@section('content')
@push('styles')@include('admin.callsheet._styles')@endpush

<div class="container py-4 cs-wrap" style="max-width:760px">
    <div class="adm-header mb-4">
        <span class="adm-icon">@include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico', 'label' => null])</span>
        <div>
            <h1 class="adm-title">Notas del llamado</h1>
            <p class="adm-subtitle">Globales de la producción — se escriben una vez y salen en todos los backs.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico me-1', 'label' => null]) {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form action="{{ route('callsheet.notes.save') }}" method="POST">
        @csrf

        <div class="card cs-card">
            <div class="card-header">Línea de seguridad (encabezado)</div>
            <div class="card-body">
                <input type="text" name="safety_bar" class="form-control" maxlength="500"
                       value="{{ old('safety_bar', $safetyBar) }}"
                       placeholder="Tu seguridad es primero  ·  No hay llamado forzado sin aprobación del UPM  ·  Los pick ups salen a la hora marcada">
                <div class="form-text">Aparece como banda bajo el encabezado del back. Sepáralas con “·” si quieres varias.</div>
            </div>
        </div>

        <div class="card cs-card">
            <div class="card-header">Notas generales (pie del back)</div>
            <div class="card-body">
                <textarea name="notes" class="form-control" rows="9" placeholder="Producción no se hace responsable de autos particulares.&#10;Hospital más cercano: …&#10;Aviso anti-acoso: …&#10;Revisar orden de transportación …&#10;Números de emergencia: …">{{ old('notes', $notes) }}</textarea>
                <div class="form-text">
                    Disclaimer de autos, hospital, anti-acoso, orden de transpo, emergencias, nomenclaturas de pick up.
                    Los <b>canales de radio</b> y la <b>leyenda de claves de lugar</b> se arman solos (departamentos + catálogo de lugares).
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mb-4">
            <button type="submit" class="btn btn-primary">
                @include('componentes._icon', ['name' => 'save', 'class' => 'cc-ico me-1', 'label' => null]) Guardar
            </button>
        </div>
    </form>
</div>
@endsection
