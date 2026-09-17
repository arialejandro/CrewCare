@extends('layouts.app')
@section('title', 'Nuevo recorrido - ' . ($branding['brand_name'] ?? 'CrewCare'))

@section('content')
<div class="container-fluid px-3 px-md-4 py-4" style="max-width:640px">

    <h1 class="h4 mb-1" style="font-family:'Poppins',sans-serif;font-weight:800">Nuevo recorrido</h1>
    {{-- DELIBERADAMENTE MÍNIMO: sólo dónde estamos. El trabajo real son las notas, y pedir un
         formulario largo antes de dejar capturar es lo que hace que la gente vuelva a la libreta. --}}
    <p class="cc-muted mb-4" style="font-size:.9rem">Sólo dónde estás. Lo demás se captura caminando.</p>

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('techscout.store') }}" method="POST" class="card p-3 p-md-4">
        @csrf

        <div class="mb-3">
            <label class="form-label fw-semibold" for="location_name">Locación <span class="text-danger">*</span></label>
            <input type="text" name="location_name" id="location_name" class="form-control"
                   value="{{ old('location_name') }}" maxlength="255" required
                   placeholder="Como la conocen en producción (ej. «Bodega Vallejo»)">
        </div>

        {{-- Mismo componente de ubicación que el scouting: escribe la dirección y las coordenadas
             se guardan solas, o toca 📍 y se llena desde el GPS. --}}
        <div class="mb-3">
            @include('componentes._geo-capture', [
                'mode'  => 'address',
                'label' => 'Dirección',
                'auto'  => true,
            ])
        </div>

        <div class="d-flex gap-2 mt-2">
            <button type="submit" class="btn btn-crew-accent">Empezar recorrido</button>
            <a href="{{ route('techscout.index') }}" class="btn btn-crew-soft">Cancelar</a>
        </div>
    </form>

</div>
@endsection
